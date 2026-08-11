<?php

declare(strict_types=1);

namespace MailWatch\Users\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use MailWatch\Users\Application\SavedFilterAdministrationGateway;
use MailWatch\Users\Domain\LocalAccount;
use MailWatch\Users\Domain\SavedFilter;

final readonly class DbalSavedFilterAdministrationGateway implements SavedFilterAdministrationGateway
{
    public function __construct(private Connection $connection)
    {
    }

    public function accountById(int $id): ?LocalAccount
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, username, type FROM users WHERE id = ?',
            [$id],
            [ParameterType::INTEGER],
        );

        if (false === $row) {
            return null;
        }

        return new LocalAccount(
            (int)($row['id'] ?? 0),
            (string)($row['username'] ?? ''),
            (string)($row['type'] ?? ''),
        );
    }

    public function filtersFor(string $username): array
    {
        return array_map(
            static fn(array $row): SavedFilter => new SavedFilter(
                (string)($row['filter'] ?? ''),
                'Y' === ($row['active'] ?? null),
            ),
            $this->connection->fetchAllAssociative(
                'SELECT filter, active FROM user_filters WHERE username = ? ORDER BY filter, id',
                [$username],
            ),
        );
    }

    public function add(string $username, SavedFilter $filter): void
    {
        $this->connection->insert('user_filters', [
            'username' => $username,
            'filter' => $filter->value,
            'active' => $filter->active ? 'Y' : 'N',
        ]);
    }

    public function delete(string $username, string $filter): bool
    {
        return 0 < $this->connection->delete('user_filters', [
            'username' => $username,
            'filter' => $filter,
        ]);
    }

    public function toggle(string $username, string $filter): bool
    {
        return $this->connection->transactional(function(Connection $connection) use ($username, $filter): bool {
            $active = $connection->fetchOne(
                'SELECT active FROM user_filters WHERE username = ? AND filter = ? ORDER BY id',
                [$username, $filter],
            );
            if (false === $active) {
                return false;
            }

            return 0 < $connection->update(
                'user_filters',
                ['active' => 'Y' === $active ? 'N' : 'Y'],
                ['username' => $username, 'filter' => $filter],
            );
        });
    }
}
