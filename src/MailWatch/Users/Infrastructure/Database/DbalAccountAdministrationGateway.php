<?php

declare(strict_types=1);

namespace MailWatch\Users\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use MailWatch\Users\Application\AccountAdministrationGateway;
use MailWatch\Users\Domain\AccountProfile;
use MailWatch\Users\Domain\AccountSummary;
use MailWatch\Users\Domain\ManagedAccount;

final readonly class DbalAccountAdministrationGateway implements AccountAdministrationGateway
{
    /**
     * The boolean columns name their type so that DBAL converts them for the
     * platform. Left undeclared, a false is bound as a string and reaches
     * MySQL and MariaDB as '', which their strict mode rejects for the
     * TINYINT(1) these columns are.
     *
     * @var array<string, string>
     */
    private const VALUE_TYPES = [
        'quarantine_report' => Types::BOOLEAN,
        'noscan' => Types::BOOLEAN,
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function accountById(int $id): ?ManagedAccount
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, username, fullname, type, quarantine_report, spamscore, highspamscore, noscan, quarantine_rcpt, login_timeout, last_login FROM users WHERE id = ?',
            [$id],
            [ParameterType::INTEGER],
        );
        if (false === $row) {
            return null;
        }

        return new ManagedAccount(
            (int)($row['id'] ?? 0),
            (string)($row['username'] ?? ''),
            (string)($row['fullname'] ?? ''),
            (string)($row['type'] ?? ''),
            (bool)($row['quarantine_report'] ?? false),
            (float)($row['spamscore'] ?? 0),
            (float)($row['highspamscore'] ?? 0),
            !(bool)($row['noscan'] ?? false),
            (string)($row['quarantine_rcpt'] ?? ''),
            (int)($row['login_timeout'] ?? -1),
            (int)($row['last_login'] ?? -1),
        );
    }

    public function accountSummaries(): array
    {
        return array_map(
            static fn(array $row): AccountSummary => new AccountSummary(
                (int)($row['id'] ?? 0),
                (string)($row['username'] ?? ''),
                (string)($row['fullname'] ?? ''),
                (string)($row['type'] ?? ''),
                !(bool)($row['noscan'] ?? false),
                (float)($row['spamscore'] ?? 0),
                (float)($row['highspamscore'] ?? 0),
                (int)($row['login_expiry'] ?? -1),
            ),
            $this->connection->fetchAllAssociative(
                'SELECT id, username, fullname, type, noscan, spamscore, highspamscore, login_expiry FROM users ORDER BY username',
            ),
        );
    }

    public function delegatedDomainsFor(string $username): array
    {
        return array_map(
            static fn(mixed $domain): string => (string)$domain,
            $this->connection->fetchFirstColumn(
                'SELECT filter FROM user_filters WHERE username = ? ORDER BY id',
                [$username],
            ),
        );
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = ?';
        $parameters = [$username];
        $types = [];
        if (null !== $exceptId) {
            $sql .= ' AND id <> ?';
            $parameters[] = $exceptId;
            $types[] = ParameterType::STRING;
            $types[] = ParameterType::INTEGER;
        }

        return 0 < (int)$this->connection->fetchOne($sql, $parameters, $types);
    }

    public function create(AccountProfile $profile, string $passwordHash): void
    {
        $this->connection->insert(
            'users',
            $this->values($profile) + ['password' => $passwordHash],
            self::VALUE_TYPES,
        );
    }

    public function update(
        ManagedAccount $target,
        AccountProfile $profile,
        ?string $passwordHash,
    ): void {
        $this->connection->transactional(function(Connection $connection) use ($target, $profile, $passwordHash): void {
            $values = $this->values($profile);
            if (null !== $passwordHash) {
                $values['password'] = $passwordHash;
            }
            $connection->update('users', $values, ['id' => $target->id], self::VALUE_TYPES);

            if ($target->username !== $profile->username) {
                $connection->update(
                    'user_filters',
                    ['username' => $profile->username],
                    ['username' => $target->username],
                );
            }
        });
    }

    public function delete(ManagedAccount $target): void
    {
        $this->connection->transactional(function(Connection $connection) use ($target): void {
            $connection->delete('user_filters', ['username' => $target->username]);
            $connection->delete('users', ['id' => $target->id]);
        });
    }

    public function forceLogout(ManagedAccount $target): void
    {
        $this->connection->update('users', ['login_expiry' => -1], ['id' => $target->id]);
    }

    /** @return array<string, bool|float|int|string> */
    private function values(AccountProfile $profile): array
    {
        return [
            'username' => $profile->username,
            'fullname' => $profile->fullName,
            'type' => $profile->role,
            'quarantine_report' => $profile->quarantineReport,
            'login_timeout' => $profile->loginTimeout,
            'spamscore' => $profile->spamScore,
            'highspamscore' => $profile->highSpamScore,
            'noscan' => !$profile->scanForSpam,
            'quarantine_rcpt' => $profile->quarantineRecipient,
        ];
    }
}
