<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Database;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use MailWatch\Shared\Infrastructure\Database\RestApiSchema;
use MailWatch\Shared\Infrastructure\Database\RestApiSchemaVerifier;
use PHPUnit\Framework\TestCase;

final class RestApiSchemaVerifierTest extends TestCase
{
    public function testItAcceptsTheCanonicalSchema(): void
    {
        $schema = new Schema();
        RestApiSchema::define($schema);

        self::assertSame([], (new RestApiSchemaVerifier())->verify($schema));
    }

    public function testItReportsMissingConstraintsAndHistoricalEnums(): void
    {
        $schema = new Schema();
        RestApiSchema::define($schema);
        $schema->dropTable('blocklist');
        $schema->getTable('allowlist')->dropIndex('allowlist_uniq');
        $schema->getTable('users')->modifyColumn('type', ['type' => Type::getType(Types::ENUM)]);
        $schema->getTable('user_filters')->modifyColumn('active', ['type' => Type::getType(Types::ENUM)]);

        self::assertSame([
            'Missing unique index on allowlist(to_address, from_address)',
            'Missing table: blocklist',
            'Wrong type for user_filters.active: expected string, got enum',
            'Wrong type for users.type: expected string, got enum',
        ], (new RestApiSchemaVerifier())->verify($schema));
    }
}
