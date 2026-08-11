<?php

declare(strict_types=1);

namespace App\Tests\Contract\Users;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use MailWatch\Users\Domain\ProfilePreferences;
use MailWatch\Users\Infrastructure\Database\DbalUserProfileGateway;
use PHPUnit\Framework\TestCase;

final class DbalUserProfileGatewayTest extends TestCase
{
    private Connection $connection;
    private DbalUserProfileGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        ContractStore::createRestApiSchema($this->connection);
        $this->gateway = new DbalUserProfileGateway($this->connection);
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, ['user_filters', 'users', 'allowlist', 'blocklist']);
        $this->connection->close();
    }

    public function testItReadsTheCanonicalProfileAndMapsNoScanToAPositiveDomainFlag(): void
    {
        $this->insertProfile();

        $profile = $this->gateway->profileByUsername('user@example.test');

        self::assertNotNull($profile);
        self::assertSame('User Name', $profile->fullName);
        self::assertSame('U', $profile->role);
        self::assertTrue($profile->quarantineReport);
        self::assertSame(3.5, $profile->spamScore);
        self::assertSame(7.25, $profile->highSpamScore);
        self::assertFalse($profile->scanForSpam);
        self::assertSame('reports@example.test', $profile->quarantineRecipient);
        self::assertNull($this->gateway->profileByUsername('missing@example.test'));
    }

    public function testItUpdatesPreferencesWithoutReplacingThePassword(): void
    {
        $this->insertProfile();

        $this->gateway->update(
            'user@example.test',
            new ProfilePreferences(false, 4.5, 9.75, true, 'other@example.test'),
            null,
        );

        $row = $this->profileRow();
        self::assertSame('existing-hash', $row['password']);
        self::assertSame(0, (int)$row['quarantine_report']);
        self::assertSame(4.5, (float)$row['spamscore']);
        self::assertSame(9.75, (float)$row['highspamscore']);
        self::assertSame(0, (int)$row['noscan']);
        self::assertSame('other@example.test', $row['quarantine_rcpt']);
    }

    public function testItReplacesThePasswordOnlyWhenAHashIsSupplied(): void
    {
        $this->insertProfile();

        $this->gateway->update(
            'user@example.test',
            new ProfilePreferences(true, 3.5, 7.25, false, 'reports@example.test'),
            'replacement-hash',
        );

        self::assertSame('replacement-hash', $this->profileRow()['password']);
    }

    private function insertProfile(): void
    {
        $this->connection->insert('users', [
            'username' => 'user@example.test',
            'password' => 'existing-hash',
            'fullname' => 'User Name',
            'type' => 'U',
            'quarantine_report' => true,
            'spamscore' => 3.5,
            'highspamscore' => 7.25,
            'noscan' => true,
            'quarantine_rcpt' => 'reports@example.test',
        ]);
    }

    /** @return array<string, mixed> */
    private function profileRow(): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT password, quarantine_report, spamscore, highspamscore, noscan, quarantine_rcpt FROM users WHERE username = ?',
            ['user@example.test'],
        );
        self::assertIsArray($row);

        return $row;
    }
}
