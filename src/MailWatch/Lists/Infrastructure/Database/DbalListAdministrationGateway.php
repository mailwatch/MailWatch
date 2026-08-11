<?php

declare(strict_types=1);

namespace MailWatch\Lists\Infrastructure\Database;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use MailWatch\Lists\Application\ListAdministrationGateway;
use MailWatch\Lists\Domain\ListAccess;
use MailWatch\Lists\Domain\ListEntry;
use MailWatch\Lists\Domain\ListKind;

final readonly class DbalListAdministrationGateway implements ListAdministrationGateway
{
    public function __construct(private Connection $connection)
    {
    }

    public function activeFilters(string $username): array
    {
        return array_map(
            'strval',
            $this->connection->fetchFirstColumn(
                "SELECT filter FROM user_filters WHERE username = ? AND active = 'Y' ORDER BY filter",
                [$username],
            ),
        );
    }

    public function entries(ListKind $kind, ListAccess $access): array
    {
        [$predicate, $parameters, $types] = $this->accessPredicate($access);

        return array_map(
            $this->entry(...),
            $this->connection->fetchAllAssociative(
                "SELECT id, from_address, to_address, to_domain FROM {$kind->value} WHERE {$predicate} ORDER BY from_address",
                $parameters,
                $types,
            ),
        );
    }

    public function replace(ListKind $kind, string $fromAddress, string $toAddress, string $toDomain): void
    {
        $this->connection->transactional(function(Connection $connection) use (
            $kind,
            $fromAddress,
            $toAddress,
            $toDomain,
        ): void {
            $id = $connection->fetchOne(
                "SELECT id FROM {$kind->value} WHERE LOWER(to_address) = ? AND LOWER(from_address) = ?",
                [$toAddress, $fromAddress],
            );
            if (false !== $id) {
                $connection->update($kind->value, ['to_domain' => $toDomain], ['id' => $id]);

                return;
            }

            try {
                $connection->insert($kind->value, [
                    'to_address' => $toAddress,
                    'to_domain' => $toDomain,
                    'from_address' => $fromAddress,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                $id = $connection->fetchOne(
                    "SELECT id FROM {$kind->value} WHERE LOWER(to_address) = ? AND LOWER(from_address) = ?",
                    [$toAddress, $fromAddress],
                );
                if (false === $id) {
                    throw $exception;
                }
                $connection->update($kind->value, ['to_domain' => $toDomain], ['id' => $id]);
            }
        });
    }

    public function delete(ListKind $kind, int $id, ListAccess $access): ?ListEntry
    {
        return $this->connection->transactional(function(Connection $connection) use ($kind, $id, $access): ?ListEntry {
            $row = $connection->fetchAssociative(
                "SELECT id, from_address, to_address, to_domain FROM {$kind->value} WHERE id = ?",
                [$id],
                [ParameterType::INTEGER],
            );
            if (false === $row) {
                return null;
            }

            $entry = $this->entry($row);
            if (!$access->canView($entry)) {
                return null;
            }

            [$predicate, $parameters, $types] = $this->accessPredicate($access);
            $deleted = $connection->executeStatement(
                "DELETE FROM {$kind->value} WHERE id = ? AND {$predicate}",
                [$id, ...$parameters],
                [ParameterType::INTEGER, ...$types],
            );

            return 1 === $deleted ? $entry : null;
        });
    }

    /** @param array<string, mixed> $row */
    private function entry(array $row): ListEntry
    {
        return new ListEntry(
            (int)($row['id'] ?? 0),
            (string)($row['from_address'] ?? ''),
            (string)($row['to_address'] ?? ''),
            (string)($row['to_domain'] ?? ''),
        );
    }

    /** @return array{string, list<list<string>>, list<ArrayParameterType>} */
    private function accessPredicate(ListAccess $access): array
    {
        return match ($access->role()) {
            'A' => ['1 = 1', [], []],
            'U' => $this->scopePredicate('to_address', $access->visibleAddresses()),
            'D' => $this->scopePredicate('to_domain', $access->domains()),
            default => ['1 = 0', [], []],
        };
    }

    /**
     * @param list<string> $values
     *
     * @return array{string, list<list<string>>, list<ArrayParameterType>}
     */
    private function scopePredicate(string $column, array $values): array
    {
        if ([] === $values) {
            return ['1 = 0', [], []];
        }

        return ["LOWER({$column}) IN (?)", [$values], [ArrayParameterType::STRING]];
    }
}
