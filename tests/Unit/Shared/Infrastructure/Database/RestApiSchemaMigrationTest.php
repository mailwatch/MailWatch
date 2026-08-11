<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use MailWatch\Migrations\Version20260811180000;
use MailWatch\Migrations\Version20260811181000;
use MailWatch\Shared\Infrastructure\Database\RestApiSchema;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RestApiSchemaMigrationTest extends TestCase
{
    public function testItNormalisesTheHistoricalKeysAndEnums(): void
    {
        $schema = new Schema();
        RestApiSchema::define($schema);
        $users = $schema->getTable('users');
        $users->dropIndex('users_username_uniq');
        $users->dropPrimaryKey();
        $users->setPrimaryKey(['username']);
        $users->addUniqueIndex(['id'], 'users_id_uniq');
        $users->modifyColumn('id', ['unsigned' => false]);
        $users->modifyColumn('type', ['type' => Type::getType(Types::ENUM)]);
        $schema->getTable('user_filters')->modifyColumn('active', ['type' => Type::getType(Types::ENUM)]);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $logger = new NullLogger();
            (new Version20260811180000($connection, $logger))->up($schema);
            self::assertTrue($this->hasUniqueIndex($users->getIndexes(), ['id']));
            (new Version20260811181000($connection, $logger))->up($schema);
        } finally {
            $connection->close();
        }

        self::assertSame(['id'], $users->getPrimaryKey()?->getColumns());
        self::assertTrue($users->getColumn('id')->getUnsigned());
        self::assertTrue($this->hasUniqueIndex($users->getIndexes(), ['username']));
        self::assertFalse($this->hasUniqueIndex($users->getIndexes(), ['id']));
        self::assertSame(Types::STRING, Type::lookupName($users->getColumn('type')->getType()));
        self::assertSame(
            Types::STRING,
            Type::lookupName($schema->getTable('user_filters')->getColumn('active')->getType()),
        );
    }

    /**
     * @param array<string, Index> $indexes
     * @param list<string>         $columns
     */
    private function hasUniqueIndex(array $indexes, array $columns): bool
    {
        foreach ($indexes as $index) {
            if (!$index->isPrimary() && $index->isUnique() && $columns === $index->getColumns()) {
                return true;
            }
        }

        return false;
    }
}
