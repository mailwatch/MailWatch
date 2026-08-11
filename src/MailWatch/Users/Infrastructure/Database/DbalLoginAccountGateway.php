<?php

declare(strict_types=1);

namespace MailWatch\Users\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use MailWatch\Users\Application\LoginAccountGateway;
use MailWatch\Users\Domain\LoginAccount;

final readonly class DbalLoginAccountGateway implements LoginAccountGateway
{
    public function __construct(private Connection $connection)
    {
    }

    public function accountByUsername(string $username): ?LoginAccount
    {
        $row = $this->connection->fetchAssociative(
            'SELECT username, fullname, type, password, login_timeout FROM users WHERE username = ?',
            [$username],
        );
        if (false === $row) {
            return null;
        }

        return new LoginAccount(
            (string)($row['username'] ?? ''),
            (string)($row['fullname'] ?? ''),
            (string)($row['type'] ?? ''),
            null === ($row['password'] ?? null) ? null : (string)$row['password'],
            (int)($row['login_timeout'] ?? -1),
            array_map(
                static fn(mixed $filter): string => (string)$filter,
                $this->connection->fetchFirstColumn(
                    "SELECT filter FROM user_filters WHERE username = ? AND active = 'Y' ORDER BY id",
                    [$username],
                ),
            ),
        );
    }

    public function provisionExternalAccount(string $username, string $fullName): void
    {
        $this->connection->transactional(function(Connection $connection) use ($username, $fullName): void {
            if (false !== $connection->fetchOne('SELECT 1 FROM users WHERE username = ?', [$username])) {
                return;
            }

            $connection->insert('users', [
                'username' => $username,
                'fullname' => $fullName,
                'type' => 'U',
                'password' => null,
            ]);
        });
    }

    public function updatePasswordHash(string $username, string $passwordHash): void
    {
        $this->connection->update('users', ['password' => $passwordHash], ['username' => $username]);
    }

    public function recordSuccessfulLogin(string $username, int $loginExpiry, int $loginTime): void
    {
        $this->connection->update(
            'users',
            ['login_expiry' => $loginExpiry, 'last_login' => $loginTime],
            ['username' => $username],
        );
    }
}
