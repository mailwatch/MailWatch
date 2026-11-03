<?php

declare(strict_types=1);

namespace MailWatch\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * The failed login counters per client IP address and username.
 *
 * Login keeps working without this table, only without the limit, so an
 * installation that has not migrated yet is not locked out. create.sql already
 * installs the table, hence the skip.
 */
final class Version20261103120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the login_failures table that limits failed logins';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf($schema->hasTable('login_failures'), 'The login_failures table already exists');

        $table = $schema->createTable('login_failures');
        $table->addColumn('ip', Types::STRING, ['length' => 45]);
        $table->addColumn('username_hash', Types::STRING, ['length' => 40, 'fixed' => true]);
        $table->addColumn('failures', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $table->addColumn('last_failure', Types::BIGINT, ['default' => 0]);
        $table->setPrimaryKey(['ip', 'username_hash']);
        $table->addIndex(['last_failure'], 'login_failures_last_failure');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('login_failures');
    }
}
