<?php

declare(strict_types=1);

namespace App\Tests\Contract\Users;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use MailWatch\Migrations\Version20261103120000;
use MailWatch\Users\Application\ThrottleLogins;
use MailWatch\Users\Domain\LoginThrottlePolicy;
use MailWatch\Users\Infrastructure\Database\DbalLoginFailureGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DbalLoginFailureGatewayTest extends TestCase
{
    private Connection $connection;
    private ThrottleLogins $throttle;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['login_failures']);
        $this->throttle = new ThrottleLogins(
            new DbalLoginFailureGateway($this->connection),
            new LoginThrottlePolicy(3, 900),
        );
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['login_failures']);
        $this->connection->close();
    }

    public function testWithoutTheTableNothingIsCountedAndNobodyIsLocked(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->throttle->recordFailure('192.0.2.1', 'user@example.test', 1000 + $i);
        }
        $this->throttle->clear('192.0.2.1', 'user@example.test');

        self::assertFalse($this->throttle->isLocked('192.0.2.1', 'user@example.test', 1010));
    }

    public function testTheLimitLocksOnlyThatClientAndUsernameUntilTheLockoutEnds(): void
    {
        $this->createTable();

        $this->throttle->recordFailure('192.0.2.1', 'user@example.test', 1000);
        $this->throttle->recordFailure('192.0.2.1', 'USER@example.test', 1001);
        self::assertFalse($this->throttle->isLocked('192.0.2.1', 'user@example.test', 1002));

        $this->throttle->recordFailure('192.0.2.1', 'user@example.test', 1002);
        self::assertTrue($this->throttle->isLocked('192.0.2.1', 'user@example.test', 1003));
        self::assertFalse($this->throttle->isLocked('192.0.2.2', 'user@example.test', 1003));
        self::assertFalse($this->throttle->isLocked('192.0.2.1', 'other@example.test', 1003));
        self::assertFalse($this->throttle->isLocked('192.0.2.1', 'user@example.test', 1902));
    }

    public function testAFailureAfterTheLockoutStartsANewCount(): void
    {
        $this->createTable();
        foreach ([1000, 1001, 1002] as $now) {
            $this->throttle->recordFailure('192.0.2.1', 'user@example.test', $now);
        }

        $this->throttle->recordFailure('192.0.2.1', 'user@example.test', 2000);

        self::assertFalse($this->throttle->isLocked('192.0.2.1', 'user@example.test', 2001));
        self::assertSame(1, (int)$this->connection->fetchOne('SELECT failures FROM login_failures'));
    }

    public function testASuccessfulLoginClearsTheCount(): void
    {
        $this->createTable();
        foreach ([1000, 1001, 1002] as $now) {
            $this->throttle->recordFailure('192.0.2.1', 'user@example.test', $now);
        }

        $this->throttle->clear('192.0.2.1', 'user@example.test');

        self::assertFalse($this->throttle->isLocked('192.0.2.1', 'user@example.test', 1003));
        self::assertSame(0, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM login_failures'));
    }

    private function createTable(): void
    {
        $schema = new Schema();
        (new Version20261103120000($this->connection, new NullLogger()))->up($schema);
        $this->connection->createSchemaManager()->createSchemaObjects($schema);
    }
}
