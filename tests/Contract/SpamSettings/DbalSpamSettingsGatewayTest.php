<?php

declare(strict_types=1);

namespace App\Tests\Contract\SpamSettings;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use MailWatch\SpamSettings\Infrastructure\Database\DbalSpamSettingsGateway;
use PHPUnit\Framework\TestCase;

final class DbalSpamSettingsGatewayTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        ContractStore::createRestApiSchema($this->connection);
        $fixture = json_decode(
            (string)file_get_contents(dirname(__DIR__, 2) . '/fixtures/api/spam-settings-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        foreach ($fixture['users'] as $user) {
            $this->connection->insert('users', $user);
        }
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        $this->connection->close();
    }

    public function testItReturnsOnlyRowsWithAnEffectiveSetting(): void
    {
        $settings = (new DbalSpamSettingsGateway($this->connection))->settings();
        $usernames = array_column($settings, 'username');

        self::assertCount(5, $settings);
        self::assertContains('recipient@example.com', $usernames);
        self::assertNotContains('zero@example.net', $usernames);
        self::assertNotContains('negative@example.net', $usernames);
        self::assertContains([
            'username' => 'recipient@example.com',
            'spam_score' => 3.5,
            'high_spam_score' => 7.0,
            'no_scan' => 1,
        ], $settings);
    }
}
