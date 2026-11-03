<?php

declare(strict_types=1);

namespace MailWatch\Users\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use MailWatch\Users\Application\LoginFailureGateway;

/**
 * Until the login_failures migration has run the table is missing, and the
 * gateway behaves as if nothing had ever failed: logins work as before.
 */
final readonly class DbalLoginFailureGateway implements LoginFailureGateway
{
    public function __construct(private Connection $connection)
    {
    }

    public function failures(string $ip, string $key): ?array
    {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT failures, last_failure FROM login_failures WHERE ip = ? AND username_hash = ?',
                [$ip, $key],
            );
        } catch (TableNotFoundException) {
            return null;
        }
        if (false === $row) {
            return null;
        }

        return ['failures' => (int)$row['failures'], 'lastFailure' => (int)$row['last_failure']];
    }

    public function recordFailure(string $ip, string $key, int $now, int $restartBefore): void
    {
        try {
            if ($this->increment($ip, $key, $now, $restartBefore)) {
                return;
            }
            try {
                $this->connection->insert('login_failures', [
                    'ip' => $ip,
                    'username_hash' => $key,
                    'failures' => 1,
                    'last_failure' => $now,
                ]);
            } catch (UniqueConstraintViolationException) {
                // a concurrent failure inserted the row first
                $this->increment($ip, $key, $now, $restartBefore);
            }
        } catch (TableNotFoundException) {
        }
    }

    public function clear(string $ip, string $key): void
    {
        try {
            $this->connection->delete('login_failures', ['ip' => $ip, 'username_hash' => $key]);
        } catch (TableNotFoundException) {
        }
    }

    private function increment(string $ip, string $key, int $now, int $restartBefore): bool
    {
        return 0 < $this->connection->executeStatement(
            'UPDATE login_failures
             SET failures = CASE WHEN last_failure <= ? THEN 1 ELSE failures + 1 END, last_failure = ?
             WHERE ip = ? AND username_hash = ?',
            [$restartBefore, $now, $ip, $key],
        );
    }
}
