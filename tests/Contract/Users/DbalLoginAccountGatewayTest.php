<?php

declare(strict_types=1);

namespace App\Tests\Contract\Users;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use MailWatch\Users\Infrastructure\Database\DbalLoginAccountGateway;
use PHPUnit\Framework\TestCase;

final class DbalLoginAccountGatewayTest extends TestCase
{
    private Connection $connection;
    private DbalLoginAccountGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        ContractStore::createRestApiSchema($this->connection);
        $this->gateway = new DbalLoginAccountGateway($this->connection);
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        $this->connection->close();
    }

    public function testItLoadsTheLoginAccountAndOnlyItsActiveFilters(): void
    {
        $this->connection->insert('users', [
            'username' => 'user@example.test',
            'password' => 'password-hash',
            'fullname' => 'User Name',
            'type' => 'D',
            'login_timeout' => 300,
        ]);
        $this->connection->insert('user_filters', [
            'username' => 'user@example.test',
            'filter' => 'active.test',
            'active' => 'Y',
        ]);
        $this->connection->insert('user_filters', [
            'username' => 'user@example.test',
            'filter' => 'inactive.test',
            'active' => 'N',
        ]);

        $account = $this->gateway->accountByUsername('user@example.test');

        self::assertNotNull($account);
        self::assertSame('User Name', $account->fullName);
        self::assertSame('D', $account->role);
        self::assertSame('password-hash', $account->passwordHash);
        self::assertSame(300, $account->loginTimeout);
        self::assertSame(['active.test'], $account->activeFilters);
    }

    public function testExternalProvisioningIsIdempotentAndDoesNotOverwriteExistingAccounts(): void
    {
        $this->gateway->provisionExternalAccount('user@example.test', 'First Name');
        $this->gateway->provisionExternalAccount('user@example.test', 'Changed Name');

        $account = $this->gateway->accountByUsername('user@example.test');

        self::assertNotNull($account);
        self::assertSame('First Name', $account->fullName);
        self::assertSame('U', $account->role);
        self::assertNull($account->passwordHash);
        self::assertSame(1, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM users'));
    }

    public function testItUpdatesThePasswordAndRecordsTheSuccessfulLogin(): void
    {
        $this->gateway->provisionExternalAccount('user@example.test', 'User');

        $this->gateway->updatePasswordHash('user@example.test', 'new-hash');
        $this->gateway->recordSuccessfulLogin('user@example.test', 1900, 1000);

        $row = $this->connection->fetchAssociative(
            'SELECT password, login_expiry, last_login FROM users WHERE username = ?',
            ['user@example.test'],
        );
        self::assertIsArray($row);
        self::assertSame('new-hash', $row['password']);
        self::assertSame(1900, (int)$row['login_expiry']);
        self::assertSame(1000, (int)$row['last_login']);
    }
}
