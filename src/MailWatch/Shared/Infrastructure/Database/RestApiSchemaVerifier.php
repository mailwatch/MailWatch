<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\Database;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

final class RestApiSchemaVerifier
{
    /** @return list<string> */
    public function verify(Schema $actual): array
    {
        $expected = new Schema();
        RestApiSchema::define($expected);
        $errors = [];

        foreach (RestApiSchema::tableNames() as $tableName) {
            if (!$actual->hasTable($tableName)) {
                $errors[] = "Missing table: {$tableName}";
                continue;
            }

            $this->verifyTable($actual->getTable($tableName), $expected->getTable($tableName), $errors);
        }

        return $errors;
    }

    /** @param list<string> $errors */
    private function verifyTable(Table $actual, Table $expected, array &$errors): void
    {
        $tableName = $expected->getName();
        foreach ($expected->getColumns() as $expectedColumn) {
            $columnName = $expectedColumn->getName();
            if (!$actual->hasColumn($columnName)) {
                $errors[] = "Missing column: {$tableName}.{$columnName}";
                continue;
            }

            $actualColumn = $actual->getColumn($columnName);
            $expectedType = Type::lookupName($expectedColumn->getType());
            $actualType = Type::lookupName($actualColumn->getType());
            if (!$this->typesAreCompatible($expectedType, $actualType)) {
                $errors[] = "Wrong type for {$tableName}.{$columnName}: expected {$expectedType}, got {$actualType}";
            }
            if ($expectedColumn->getNotnull() !== $actualColumn->getNotnull()) {
                $expectedNullability = $expectedColumn->getNotnull() ? 'NOT NULL' : 'NULL';
                $errors[] = "Wrong nullability for {$tableName}.{$columnName}: expected {$expectedNullability}";
            }
            if ($expectedColumn->getAutoincrement() && !$actualColumn->getAutoincrement()) {
                $errors[] = "Missing auto increment: {$tableName}.{$columnName}";
            }
            if (
                null !== $expectedColumn->getLength()
                && null !== $actualColumn->getLength()
                && $actualColumn->getLength() < $expectedColumn->getLength()
            ) {
                $errors[] = "Column too short: {$tableName}.{$columnName}";
            }
        }

        $expectedPrimary = $expected->getPrimaryKey();
        $actualPrimary = $actual->getPrimaryKey();
        if (null === $actualPrimary || null === $expectedPrimary || !$this->sameColumns($actualPrimary, $expectedPrimary)) {
            $errors[] = "Wrong primary key: {$tableName}";
        }

        foreach ($expected->getIndexes() as $expectedIndex) {
            if ($expectedIndex->isPrimary() || $this->hasEquivalentIndex($actual, $expectedIndex)) {
                continue;
            }

            $kind = $expectedIndex->isUnique() ? 'unique index' : 'index';
            $errors[] = sprintf(
                'Missing %s on %s(%s)',
                $kind,
                $tableName,
                implode(', ', $expectedIndex->getColumns()),
            );
        }
    }

    private function typesAreCompatible(string $expected, string $actual): bool
    {
        if ($expected === $actual) {
            return true;
        }

        $families = [
            [Types::SMALLINT, Types::INTEGER, Types::BIGINT],
            [Types::STRING, Types::ASCII_STRING],
            [Types::TEXT, Types::STRING, Types::ASCII_STRING],
            [Types::FLOAT, Types::DECIMAL],
            [Types::BOOLEAN, Types::SMALLINT, Types::INTEGER],
        ];
        foreach ($families as $family) {
            if (\in_array($expected, $family, true) && \in_array($actual, $family, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasEquivalentIndex(Table $actual, Index $expected): bool
    {
        foreach ($actual->getIndexes() as $candidate) {
            if ($candidate->isPrimary()) {
                continue;
            }
            if ($expected->isUnique() !== $candidate->isUnique()) {
                continue;
            }
            if ($this->sameColumns($candidate, $expected)) {
                return true;
            }
        }

        return false;
    }

    private function sameColumns(Index $left, Index $right): bool
    {
        return array_map('strtolower', $left->getColumns())
            === array_map('strtolower', $right->getColumns());
    }
}
