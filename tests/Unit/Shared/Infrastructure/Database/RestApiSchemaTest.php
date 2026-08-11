<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Database;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use MailWatch\Shared\Infrastructure\Database\RestApiSchema;
use PHPUnit\Framework\TestCase;

final class RestApiSchemaTest extends TestCase
{
    public function testItDefinesTheCompletePortableRestApiSchema(): void
    {
        $schema = new Schema();
        RestApiSchema::define($schema);

        self::assertSame(
            RestApiSchema::tableNames(),
            array_map(static fn (Table $table): string => $table->getName(), $schema->getTables()),
        );

        $users = $schema->getTable('users');
        self::assertSame(['id'], $users->getPrimaryKey()?->getColumns());
        self::assertTrue($users->getColumn('id')->getAutoincrement());
        self::assertTrue($users->getColumn('id')->getUnsigned());
        self::assertSame(Types::STRING, Type::lookupName($users->getColumn('type')->getType()));
        self::assertTrue($this->hasIndex($users->getIndexes(), ['username'], true));

        $filters = $schema->getTable('user_filters');
        self::assertSame(Types::STRING, Type::lookupName($filters->getColumn('active')->getType()));
        self::assertTrue($this->hasIndex($filters->getIndexes(), ['username'], false));
    }

    /**
     * @param array<string, Index> $indexes
     * @param list<string>         $columns
     */
    private function hasIndex(array $indexes, array $columns, bool $unique): bool
    {
        foreach ($indexes as $index) {
            if (!$index->isPrimary() && $unique === $index->isUnique() && $columns === $index->getColumns()) {
                return true;
            }
        }

        return false;
    }
}
