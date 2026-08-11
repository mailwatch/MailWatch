<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\Database;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Canonical schema for the four remaining REST API persistence tables.
 *
 * Message ingestion already owns the separate canonical maillog migration.
 * This is deliberately expressed through DBAL types and semantic constraints.
 * MySQL prefix lengths remain index options, so platforms that do not need
 * them can index the complete value without an application-level branch.
 */
final class RestApiSchema
{
    /** @return list<string> */
    public static function tableNames(): array
    {
        return ['allowlist', 'blocklist', 'user_filters', 'users'];
    }

    public static function define(Schema $schema): void
    {
        self::defineList($schema, 'allowlist', 16777215);
        self::defineList($schema, 'blocklist', 65535);
        self::defineUserFilters($schema);
        self::defineUsers($schema);
    }

    private static function defineList(Schema $schema, string $name, int $textLength): void
    {
        if ($schema->hasTable($name)) {
            return;
        }

        $table = $schema->createTable($name);
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        foreach (['to_address', 'to_domain', 'from_address'] as $column) {
            $table->addColumn($column, Types::TEXT, ['length' => $textLength, 'notnull' => false]);
        }
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(
            ['to_address', 'from_address'],
            $name . '_uniq',
            ['lengths' => [100, 100]],
        );
    }

    private static function defineUserFilters(Schema $schema): void
    {
        if ($schema->hasTable('user_filters')) {
            return;
        }

        $table = $schema->createTable('user_filters');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('username', Types::STRING, ['length' => 191, 'default' => '']);
        $table->addColumn('filter', Types::TEXT, ['length' => 16777215, 'notnull' => false]);
        $table->addColumn('verify_key', Types::STRING, ['length' => 32, 'default' => '']);
        $table->addColumn('active', Types::STRING, ['length' => 1, 'notnull' => false, 'default' => 'N']);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['username'], 'user_filters_username_idx');
    }

    private static function defineUsers(Schema $schema): void
    {
        if ($schema->hasTable('users')) {
            return;
        }

        $table = $schema->createTable('users');
        self::defineUserIdentity($table);
        $table->addColumn('password', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('fullname', Types::STRING, ['length' => 255, 'default' => '']);
        $table->addColumn('type', Types::STRING, ['length' => 1, 'default' => 'U']);
        $table->addColumn('quarantine_report', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
        $table->addColumn('spamscore', Types::SMALLFLOAT, ['notnull' => false, 'default' => 0]);
        $table->addColumn('highspamscore', Types::SMALLFLOAT, ['notnull' => false, 'default' => 0]);
        $table->addColumn('noscan', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
        $table->addColumn('quarantine_rcpt', Types::STRING, ['length' => 60, 'notnull' => false]);
        $table->addColumn('resetid', Types::STRING, ['length' => 32, 'notnull' => false]);
        $table->addColumn('resetexpire', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('lastreset', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('login_expiry', Types::BIGINT, ['notnull' => false, 'default' => -1]);
        $table->addColumn('last_login', Types::BIGINT, ['notnull' => false, 'default' => -1]);
        $table->addColumn('login_timeout', Types::SMALLINT, ['notnull' => false, 'default' => -1]);
    }

    private static function defineUserIdentity(Table $table): void
    {
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('username', Types::STRING, ['length' => 191, 'default' => '']);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['username'], 'users_username_uniq');
    }
}
