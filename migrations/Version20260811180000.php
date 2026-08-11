<?php

declare(strict_types=1);

namespace MailWatch\Migrations;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use MailWatch\Shared\Infrastructure\Database\RestApiSchema;

/**
 * Add the canonical schema behind the two REST snapshot APIs.
 *
 * Existing 1.2 installations already have these tables. Their data is kept;
 * only the users identity constraint is made portable and the two historical
 * enums become one-character strings. Missing tables are created from the same
 * definition used by the contract tests.
 */
final class Version20260811180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create and normalise the canonical schema behind the REST snapshot APIs';
    }

    public function up(Schema $schema): void
    {
        RestApiSchema::define($schema);
        $this->normaliseUsers($schema->getTable('users'));
        $this->normaliseActiveFlag($schema->getTable('user_filters'));
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'The migration may adopt populated tables and cannot know which tables are safe to remove',
        );
    }

    private function normaliseUsers(Table $users): void
    {
        $users->modifyColumn('id', [
            'type' => Type::getType(Types::BIGINT),
            'autoincrement' => true,
            'unsigned' => true,
            'notnull' => true,
        ]);

        $primary = $users->getPrimaryKey();
        if (null === $primary || ['id'] !== array_map('strtolower', $primary->getColumns())) {
            if (null !== $primary) {
                $users->dropPrimaryKey();
            }
            $users->setPrimaryKey(['id']);
        }
        if (!$this->hasUniqueIndex($users, ['username'])) {
            $users->addUniqueIndex(['username'], 'users_username_uniq');
        }

        $users->modifyColumn('username', [
            'type' => Type::getType(Types::STRING),
            'length' => 191,
            'notnull' => true,
            'default' => '',
        ]);
        $users->modifyColumn('type', [
            'type' => Type::getType(Types::STRING),
            'length' => 1,
            'notnull' => true,
            'default' => 'U',
        ]);
    }

    private function normaliseActiveFlag(Table $userFilters): void
    {
        $userFilters->modifyColumn('active', [
            'type' => Type::getType(Types::STRING),
            'length' => 1,
            'notnull' => false,
            'default' => 'N',
        ]);
    }

    /** @param list<string> $columns */
    private function hasUniqueIndex(Table $table, array $columns): bool
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->isUnique() && $this->sameColumns($index, $columns)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $columns */
    private function sameColumns(Index $index, array $columns): bool
    {
        return array_map('strtolower', $index->getColumns()) === array_map('strtolower', $columns);
    }
}
