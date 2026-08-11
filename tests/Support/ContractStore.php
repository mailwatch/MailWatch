<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use MailWatch\Shared\Infrastructure\Database\DatabaseConfiguration;
use MailWatch\Shared\Infrastructure\Database\RestApiSchema;

final class ContractStore
{
    public static function connection(): Connection
    {
        $dsn = getenv('MAILWATCH_CONTRACT_DSN');
        if (false === $dsn || '' === $dsn) {
            return DriverManager::getConnection([
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ]);
        }

        return DriverManager::getConnection(DatabaseConfiguration::fromDsn($dsn)->parameters());
    }

    /**
     * @param list<string> $tables
     */
    public static function dropTables(Connection $connection, array $tables): void
    {
        $schemaManager = $connection->createSchemaManager();
        foreach ($tables as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $schemaManager->dropTable($table);
            }
        }
    }

    public static function createRestApiSchema(Connection $connection): void
    {
        $schema = new Schema();
        RestApiSchema::define($schema);
        $connection->createSchemaManager()->createSchemaObjects($schema);
    }
}
