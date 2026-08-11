<?php

declare(strict_types=1);

namespace App\Tests\Contract\Lists;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use MailWatch\Lists\Domain\ListAccess;
use MailWatch\Lists\Domain\ListEntry;
use MailWatch\Lists\Domain\ListKind;
use MailWatch\Lists\Infrastructure\Database\DbalListAdministrationGateway;
use PHPUnit\Framework\TestCase;

final class DbalListAdministrationGatewayTest extends TestCase
{
    private Connection $connection;
    private DbalListAdministrationGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        ContractStore::createRestApiSchema($this->connection);
        $this->gateway = new DbalListAdministrationGateway($this->connection);
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        $this->connection->close();
    }

    public function testItLoadsOnlyActiveFiltersInStableOrder(): void
    {
        $this->connection->insert('user_filters', [
            'username' => 'owner@example.test',
            'filter' => 'zulu@example.test',
            'active' => 'Y',
        ]);
        $this->connection->insert('user_filters', [
            'username' => 'owner@example.test',
            'filter' => 'inactive@example.test',
            'active' => 'N',
        ]);
        $this->connection->insert('user_filters', [
            'username' => 'owner@example.test',
            'filter' => 'alpha@example.test',
            'active' => 'Y',
        ]);

        self::assertSame(
            ['alpha@example.test', 'zulu@example.test'],
            $this->gateway->activeFilters('owner@example.test'),
        );
    }

    public function testReplaceCreatesAndThenUpdatesOneEntry(): void
    {
        $this->gateway->replace(
            ListKind::Allowlist,
            'sender@example.test',
            'recipient@example.test',
            'example.test',
        );
        $this->gateway->replace(
            ListKind::Allowlist,
            'sender@example.test',
            'recipient@example.test',
            'changed.example.test',
        );

        $entries = $this->gateway->entries(
            ListKind::Allowlist,
            ListAccess::forAccount('root', 'A', []),
        );
        self::assertCount(1, $entries);
        self::assertSame('changed.example.test', $entries[0]->toDomain);
    }

    public function testEntriesAreScopedWithPreparedAddressAndDomainFilters(): void
    {
        foreach ([
            ['first@example.test', 'OWNER@Example.test', 'Example.test'],
            ['second@example.test', 'alias@example.test', 'example.test'],
            ['third@example.test', 'other@delegated.test', 'delegated.test'],
            ['fourth@example.test', 'outside@other.test', 'other.test'],
        ] as [$fromAddress, $toAddress, $toDomain]) {
            $this->gateway->replace(ListKind::Allowlist, $fromAddress, $toAddress, $toDomain);
        }

        $userEntries = $this->gateway->entries(
            ListKind::Allowlist,
            ListAccess::forAccount('owner@example.test', 'U', ['alias@example.test']),
        );
        self::assertSame(
            ['first@example.test', 'second@example.test'],
            array_map(static fn(ListEntry $entry): string => $entry->fromAddress, $userEntries),
        );

        $domainEntries = $this->gateway->entries(
            ListKind::Allowlist,
            ListAccess::forAccount('admin@example.test', 'D', ['delegated.test']),
        );
        self::assertSame(
            ['first@example.test', 'second@example.test', 'third@example.test'],
            array_map(static fn(ListEntry $entry): string => $entry->fromAddress, $domainEntries),
        );
    }

    public function testDeleteIsTransactionalAndEnforcesTheSuppliedScope(): void
    {
        $this->gateway->replace(
            ListKind::Blocklist,
            'blocked@example.test',
            'recipient@example.test',
            'example.test',
        );
        $administrator = ListAccess::forAccount('root', 'A', []);
        $id = $this->gateway->entries(ListKind::Blocklist, $administrator)[0]->id;

        $denied = ListAccess::forAccount('other@example.test', 'U', []);
        self::assertNull($this->gateway->delete(ListKind::Blocklist, $id, $denied));
        self::assertCount(1, $this->gateway->entries(ListKind::Blocklist, $administrator));

        $allowed = ListAccess::forAccount('admin@example.test', 'D', []);
        $removed = $this->gateway->delete(ListKind::Blocklist, $id, $allowed);
        self::assertNotNull($removed);
        self::assertSame('blocked@example.test', $removed->fromAddress);
        self::assertSame([], $this->gateway->entries(ListKind::Blocklist, $administrator));
    }
}
