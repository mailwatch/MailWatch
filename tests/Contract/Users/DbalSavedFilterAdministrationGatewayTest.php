<?php

declare(strict_types=1);

namespace App\Tests\Contract\Users;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use MailWatch\Users\Domain\SavedFilter;
use MailWatch\Users\Infrastructure\Database\DbalSavedFilterAdministrationGateway;
use PHPUnit\Framework\TestCase;

final class DbalSavedFilterAdministrationGatewayTest extends TestCase
{
    private Connection $connection;
    private DbalSavedFilterAdministrationGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        ContractStore::createRestApiSchema($this->connection);
        $this->gateway = new DbalSavedFilterAdministrationGateway($this->connection);
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        $this->connection->close();
    }

    public function testItLoadsAnAccountByItsCanonicalIdentifier(): void
    {
        $this->connection->insert('users', ['username' => 'user@example.test', 'type' => 'U']);
        $id = (int)$this->connection->lastInsertId();

        $account = $this->gateway->accountById($id);

        self::assertNotNull($account);
        self::assertSame('user@example.test', $account->username);
        self::assertSame('U', $account->role);
        self::assertNull($this->gateway->accountById($id + 1));
    }

    public function testItAddsAndListsFiltersInStableOrder(): void
    {
        $this->gateway->add('user@example.test', new SavedFilter('zulu.test', false));
        $this->gateway->add('user@example.test', new SavedFilter('alpha@example.test', true));

        $filters = $this->gateway->filtersFor('user@example.test');

        self::assertSame(['alpha@example.test', 'zulu.test'], array_map(
            static fn(SavedFilter $filter): string => $filter->value,
            $filters,
        ));
        self::assertTrue($filters[0]->active);
        self::assertFalse($filters[1]->active);
    }

    public function testToggleAndDeletePreserveLegacyDuplicateSemantics(): void
    {
        $this->gateway->add('user@example.test', new SavedFilter('duplicate.test', true));
        $this->gateway->add('user@example.test', new SavedFilter('duplicate.test', false));

        self::assertTrue($this->gateway->toggle('user@example.test', 'duplicate.test'));
        self::assertSame([false, false], array_map(
            static fn(SavedFilter $filter): bool => $filter->active,
            $this->gateway->filtersFor('user@example.test'),
        ));

        self::assertTrue($this->gateway->delete('user@example.test', 'duplicate.test'));
        self::assertSame([], $this->gateway->filtersFor('user@example.test'));
        self::assertFalse($this->gateway->toggle('user@example.test', 'missing.test'));
        self::assertFalse($this->gateway->delete('user@example.test', 'missing.test'));
    }
}
