<?php

declare(strict_types=1);

namespace App\Tests\Contract\Lists;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use MailWatch\Lists\Infrastructure\Database\DbalAllowBlockListGateway;
use PHPUnit\Framework\TestCase;

final class DbalAllowBlockListGatewayTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        ContractStore::createRestApiSchema($this->connection);

        $fixture = json_decode(
            (string)file_get_contents(dirname(__DIR__, 2) . '/fixtures/api/allow-block-list-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        foreach (['allowlist', 'blocklist'] as $table) {
            foreach ($fixture[$table] as $entry) {
                $this->connection->insert($table, $entry);
            }
        }
        foreach ($fixture['user_filters'] as $filter) {
            $this->connection->insert('user_filters', $filter);
        }
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        $this->connection->close();
    }

    public function testItReturnsTheDirectAndExpandedEntriesForBothLists(): void
    {
        $entries = (new DbalAllowBlockListGateway($this->connection))->entries();

        self::assertCount(9, $entries['allowlist']);
        self::assertContains(
            ['to_address' => 'filtered.example.net', 'from_address' => 'filtered-sender@example.com'],
            $entries['allowlist']
        );
        self::assertContains(
            ['to_address' => 'historically-inactive.example.net', 'from_address' => 'filtered-sender@example.com'],
            $entries['allowlist'],
            'the gateway preserves the historical join semantics, including inactive filters'
        );
        self::assertCount(2, $entries['blocklist']);
        self::assertContains(
            ['to_address' => 'default', 'from_address' => 'blocked@example.com'],
            $entries['blocklist']
        );
        self::assertContains(
            ['to_address' => 'recipient@example.net', 'from_address' => '198.51.100.23'],
            $entries['blocklist']
        );
    }
}
