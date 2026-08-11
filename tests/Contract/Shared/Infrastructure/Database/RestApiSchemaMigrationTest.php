<?php

declare(strict_types=1);

namespace App\Tests\Contract\Shared\Infrastructure\Database;

use App\Tests\Support\ContractStore;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Schema;
use MailWatch\Migrations\Version20260811180000;
use MailWatch\Migrations\Version20260811181000;
use MailWatch\Shared\Infrastructure\Database\RestApiSchema;
use MailWatch\Shared\Infrastructure\Database\RestApiSchemaVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RestApiSchemaMigrationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = ContractStore::connection();
        ContractStore::dropTables($this->connection, RestApiSchema::tableNames());
    }

    protected function tearDown(): void
    {
        ContractStore::dropTables($this->connection, RestApiSchema::tableNames());
        $this->connection->close();
    }

    public function testItCreatesOneCanonicalSchemaOnEverySupportedEngine(): void
    {
        $schema = new Schema();
        (new Version20260811180000($this->connection, new NullLogger()))->up($schema);
        $this->connection->createSchemaManager()->createSchemaObjects($schema);

        $this->connection->insert('allowlist', [
            'to_address' => 'recipient@example.test',
            'from_address' => 'sender@example.test',
        ]);
        $this->connection->insert('users', [
            'username' => 'recipient@example.test',
            'type' => 'U',
            'spamscore' => 5.0,
        ]);

        self::assertSame([], $this->verifyInstalledSchema());
        self::assertSame(1, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM allowlist'));
        self::assertSame(1, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM users'));
    }

    public function testItAdoptsPopulatedHistoricalMySqlTablesWithoutLosingData(): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('The historical schema uses MySQL-only ENUM and AUTO_INCREMENT semantics');
        }

        $this->createHistoricalMySqlSchema();
        $this->connection->insert('allowlist', [
            'to_address' => 'recipient@example.test',
            'from_address' => 'sender@example.test',
        ]);
        $this->connection->insert('blocklist', [
            'to_address' => 'default',
            'from_address' => 'blocked@example.test',
        ]);
        $this->connection->insert('user_filters', [
            'username' => 'recipient@example.test',
            'filter' => 'filtered.example.test',
            'active' => 'Y',
        ]);
        $this->connection->insert('users', [
            'username' => 'recipient@example.test',
            'type' => 'A',
            'spamscore' => 5.0,
            'highspamscore' => 8.0,
            'noscan' => 1,
        ]);

        $target = $this->connection->createSchemaManager()->introspectSchema();
        (new Version20260811180000($this->connection, new NullLogger()))->up($target);
        $this->migrateSchemaLikeDoctrineMigrations($target);
        $target = $this->connection->createSchemaManager()->introspectSchema();
        (new Version20260811181000($this->connection, new NullLogger()))->up($target);
        $this->migrateSchemaLikeDoctrineMigrations($target);

        self::assertSame([], $this->verifyInstalledSchema());
        self::assertSame('recipient@example.test', $this->connection->fetchOne('SELECT username FROM users'));
        self::assertSame('A', $this->connection->fetchOne('SELECT type FROM users'));
        self::assertSame('Y', $this->connection->fetchOne('SELECT active FROM user_filters'));
        self::assertSame(1, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM allowlist'));
        self::assertSame(1, (int)$this->connection->fetchOne('SELECT COUNT(*) FROM blocklist'));
    }

    /** @return list<string> */
    private function verifyInstalledSchema(): array
    {
        return (new RestApiSchemaVerifier())->verify(
            $this->connection->createSchemaManager()->introspectSchema(),
        );
    }

    private function createHistoricalMySqlSchema(): void
    {
        $listSchema = new Schema();
        RestApiSchema::define($listSchema);
        $listSchema->dropTable('users');
        $listSchema->dropTable('user_filters');
        $this->connection->createSchemaManager()->createSchemaObjects($listSchema);

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE users (
                id BIGINT NOT NULL AUTO_INCREMENT,
                username VARCHAR(191) NOT NULL DEFAULT '',
                password VARCHAR(255) DEFAULT NULL,
                fullname VARCHAR(255) NOT NULL DEFAULT '',
                type ENUM('A', 'D', 'U', 'R', 'H') NOT NULL DEFAULT 'U',
                quarantine_report TINYINT(1) DEFAULT 0,
                spamscore FLOAT DEFAULT 0,
                highspamscore FLOAT DEFAULT 0,
                noscan TINYINT(1) DEFAULT 0,
                quarantine_rcpt VARCHAR(60) DEFAULT NULL,
                resetid VARCHAR(32) DEFAULT NULL,
                resetexpire BIGINT DEFAULT NULL,
                lastreset BIGINT DEFAULT NULL,
                login_expiry BIGINT DEFAULT -1,
                last_login BIGINT DEFAULT -1,
                login_timeout SMALLINT DEFAULT -1,
                PRIMARY KEY (username),
                UNIQUE KEY users_id_uniq (id)
            )
            SQL);
        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE user_filters (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(191) NOT NULL DEFAULT '',
                filter MEDIUMTEXT DEFAULT NULL,
                verify_key VARCHAR(32) NOT NULL DEFAULT '',
                active ENUM('N', 'Y') DEFAULT 'N',
                PRIMARY KEY (id),
                KEY user_filters_username_idx (username)
            )
            SQL);
    }

    private function migrateSchemaLikeDoctrineMigrations(Schema $target): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $comparator = $schemaManager->createComparator(
            (new ComparatorConfig())->withReportModifiedIndexes(false),
        );
        $schemaManager->alterSchema($comparator->compareSchemas($schemaManager->introspectSchema(), $target));
    }
}
