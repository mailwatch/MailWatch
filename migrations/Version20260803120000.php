<?php

declare(strict_types=1);

namespace MailWatch\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convert the remaining MySQL TIMESTAMP columns to DATETIME holding UTC.
 *
 * Only installations that came from create.sql or upgrade.php have these
 * columns as TIMESTAMP; a database created by the previous migration already
 * has DATETIME, and no other engine has the type at all. The migration
 * therefore inspects what is actually there and converts what needs it.
 *
 * This has to be explicit SQL. DBAL maps MySQL TIMESTAMP and DATETIME onto the
 * same type, so a declarative migration asking for DATETIME emits nothing at
 * all against a TIMESTAMP column: it would be recorded as executed while
 * changing nothing. See section 8.6 of the programme document.
 *
 * The session time zone is pinned to UTC for the conversion, and that is not
 * merely for determinism. TIMESTAMP already holds UTC internally, so converting
 * under UTC writes the same instant into the DATETIME; under any other zone
 * every row would shift by that offset, silently. The pin makes the migration
 * produce the intended semantics rather than the semantics of whoever ran it.
 *
 * On a large maillog this rebuilds the table and blocks writes for the
 * duration: MySQL cannot change a column type in place. UPGRADING.md states
 * the cost, and docs/ carries the pt-online-schema-change and gh-ost
 * invocations for installations that cannot take a write outage.
 */
final class Version20260803120000 extends AbstractMigration
{
    /** @var array<string, array{string, string}> */
    private const COLUMNS = [
        'maillog.timestamp' => ['maillog', 'DATETIME NULL'],
        'maillog.last_update' => ['maillog', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        'audit_log.timestamp' => ['audit_log', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ];

    public function getDescription(): string
    {
        return 'Convert TIMESTAMP columns to DATETIME holding UTC';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->platformIsMySQL(),
            'Only MySQL and MariaDB installations can hold TIMESTAMP columns'
        );

        $converted = false;
        foreach (self::COLUMNS as $qualified => [$table, $definition]) {
            $column = substr($qualified, strlen($table) + 1);
            if ('timestamp' !== $this->columnType($table, $column)) {
                continue;
            }

            if (!$converted) {
                $this->addSql("SET time_zone = '+00:00'");
                $converted = true;
            }

            $this->addSql("ALTER TABLE {$table} MODIFY {$column} {$definition}");
        }

        $this->skipIf(!$converted, 'Every timestamp column already holds DATETIME');
    }

    /**
     * Reversing restores the type but not its reach: rows after 2038 cannot be
     * represented by TIMESTAMP and the statement fails on them.
     */
    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->platformIsMySQL(),
            'Only MySQL and MariaDB installations can hold TIMESTAMP columns'
        );

        $this->addSql("SET time_zone = '+00:00'");
        $this->addSql('ALTER TABLE maillog MODIFY timestamp TIMESTAMP NULL');
        $this->addSql('ALTER TABLE maillog MODIFY last_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE audit_log MODIFY timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    private function platformIsMySQL(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }

    /**
     * Read from information_schema rather than from the DBAL schema, which
     * reports both types identically and cannot answer this question.
     */
    private function columnType(string $table, string $column): ?string
    {
        $type = $this->connection->fetchOne(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return \is_string($type) ? strtolower($type) : null;
    }
}
