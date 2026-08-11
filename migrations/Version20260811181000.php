<?php

declare(strict_types=1);

namespace MailWatch\Migrations;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove the historical unique index on users.id after id is the primary key.
 *
 * This intentionally follows the primary-key migration in a separate step.
 * MySQL requires an indexed AUTO_INCREMENT column, so dropping the old unique
 * index before the new primary key exists would fail on a populated 1.2 table.
 */
final class Version20260811181000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the redundant users.id unique index after primary-key normalisation';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $users = $schema->getTable('users');
        foreach ($users->getIndexes() as $index) {
            if (!$index->isPrimary() && $index->isUnique() && $this->sameColumns($index, ['id'])) {
                $users->dropIndex($index->getName());
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'The migration cannot know whether the redundant index existed before the REST schema was adopted',
        );
    }

    /** @param list<string> $columns */
    private function sameColumns(Index $index, array $columns): bool
    {
        return array_map('strtolower', $index->getColumns()) === array_map('strtolower', $columns);
    }
}
