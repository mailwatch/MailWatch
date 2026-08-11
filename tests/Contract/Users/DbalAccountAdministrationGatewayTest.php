<?php

declare(strict_types=1);

namespace App\Tests\Contract\Users;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use MailWatch\Users\Domain\AccountProfile;
use MailWatch\Users\Infrastructure\Database\DbalAccountAdministrationGateway;
use PHPUnit\Framework\TestCase;

final class DbalAccountAdministrationGatewayTest extends TestCase
{
    private Connection $connection;
    private DbalAccountAdministrationGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        ContractStore::createRestApiSchema($this->connection);
        $this->gateway = new DbalAccountAdministrationGateway($this->connection);
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        $this->connection->close();
    }

    public function testItCreatesAndReadsAnAccountUsingTheCanonicalSchema(): void
    {
        $this->gateway->create(self::profile('user@example.test'), 'password-hash');
        $id = (int)$this->connection->lastInsertId();

        $account = $this->gateway->accountById($id);

        self::assertNotNull($account);
        self::assertSame('user@example.test', $account->username);
        self::assertSame('User Name', $account->fullName);
        self::assertTrue($account->quarantineReport);
        self::assertTrue($account->scanForSpam);
        self::assertSame(-1, $account->loginTimeout);
        self::assertSame('password-hash', $this->passwordFor($id));
    }

    public function testItFindsDuplicatesWhileExcludingTheCurrentAccount(): void
    {
        $this->gateway->create(self::profile('user@example.test'), 'hash');
        $id = (int)$this->connection->lastInsertId();

        self::assertTrue($this->gateway->usernameExists('user@example.test'));
        self::assertFalse($this->gateway->usernameExists('user@example.test', $id));
        self::assertFalse($this->gateway->usernameExists('missing@example.test'));
    }

    public function testUpdateRenamesAssociatedFiltersAndPreservesAnUnchangedPassword(): void
    {
        $this->gateway->create(self::profile('old@example.test'), 'existing-hash');
        $id = (int)$this->connection->lastInsertId();
        $this->connection->insert('user_filters', [
            'username' => 'old@example.test',
            'filter' => 'delegated.test',
            'active' => 'N',
        ]);
        $target = $this->gateway->accountById($id);
        self::assertNotNull($target);

        $this->gateway->update($target, self::profile('new@example.test'), null);

        self::assertSame('existing-hash', $this->passwordFor($id));
        self::assertSame('new@example.test', $this->connection->fetchOne('SELECT username FROM user_filters'));
        self::assertSame(['delegated.test'], $this->gateway->delegatedDomainsFor('new@example.test'));
    }

    public function testItUpdatesAPasswordAndDeletesTheAccountWithItsFilters(): void
    {
        $this->gateway->create(self::profile('user@example.test'), 'old-hash');
        $id = (int)$this->connection->lastInsertId();
        $this->connection->insert('user_filters', [
            'username' => 'user@example.test',
            'filter' => 'delegated.test',
            'active' => 'Y',
        ]);
        $target = $this->gateway->accountById($id);
        self::assertNotNull($target);

        $this->gateway->update($target, self::profile('user@example.test'), 'new-hash');
        self::assertSame('new-hash', $this->passwordFor($id));

        $this->gateway->delete($target);
        self::assertNull($this->gateway->accountById($id));
        self::assertSame(0, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM user_filters'));
    }

    public function testItListsAccountSummariesInUsernameOrder(): void
    {
        $this->gateway->create(self::profile('zeta@example.test'), 'hash');
        $zetaId = (int)$this->connection->lastInsertId();
        $this->connection->update('users', ['noscan' => 1, 'login_expiry' => 1234], ['id' => $zetaId]);
        $this->gateway->create(self::profile('alpha@example.test'), 'hash');

        $summaries = $this->gateway->accountSummaries();

        self::assertSame(['alpha@example.test', 'zeta@example.test'], array_column($summaries, 'username'));
        self::assertTrue($summaries[0]->scanForSpam);
        self::assertFalse($summaries[1]->scanForSpam);
        self::assertSame(1234, $summaries[1]->loginExpiry);
    }

    public function testItForcesLogoutByExpiringTheAccountSession(): void
    {
        $this->gateway->create(self::profile('user@example.test'), 'hash');
        $id = (int)$this->connection->lastInsertId();
        $this->connection->update('users', ['login_expiry' => 1234], ['id' => $id]);
        $target = $this->gateway->accountById($id);
        self::assertNotNull($target);

        $this->gateway->forceLogout($target);

        self::assertSame(-1, (int)$this->connection->fetchOne('SELECT login_expiry FROM users WHERE id = ?', [$id]));
    }

    private static function profile(string $username): AccountProfile
    {
        return new AccountProfile($username, 'User Name', 'U', true, 3.5, 7.25, true, 'reports@example.test', -1);
    }

    private function passwordFor(int $id): string
    {
        return (string)$this->connection->fetchOne('SELECT password FROM users WHERE id = ?', [$id]);
    }
}
