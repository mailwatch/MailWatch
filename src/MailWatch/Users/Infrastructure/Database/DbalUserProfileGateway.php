<?php

declare(strict_types=1);

namespace MailWatch\Users\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use MailWatch\Users\Application\UserProfileGateway;
use MailWatch\Users\Domain\LocalUserProfile;
use MailWatch\Users\Domain\ProfilePreferences;

final readonly class DbalUserProfileGateway implements UserProfileGateway
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

    public function profileByUsername(string $username): ?LocalUserProfile
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, username, fullname, type, quarantine_report, spamscore, highspamscore, noscan, quarantine_rcpt FROM users WHERE username = ?',
            [$username],
        );

        if (false === $row) {
            return null;
        }

        return new LocalUserProfile(
            (int)($row['id'] ?? 0),
            (string)($row['username'] ?? ''),
            (string)($row['fullname'] ?? ''),
            (string)($row['type'] ?? ''),
            (bool)($row['quarantine_report'] ?? false),
            (float)($row['spamscore'] ?? 0),
            (float)($row['highspamscore'] ?? 0),
            !(bool)($row['noscan'] ?? false),
            (string)($row['quarantine_rcpt'] ?? ''),
        );
    }

    public function update(
        string $username,
        ProfilePreferences $preferences,
        ?string $passwordHash,
    ): void {
        $values = [
            'quarantine_report' => $preferences->quarantineReport,
            'spamscore' => $preferences->spamScore,
            'highspamscore' => $preferences->highSpamScore,
            'noscan' => !$preferences->scanForSpam,
            'quarantine_rcpt' => $preferences->quarantineRecipient,
        ];
        if (null !== $passwordHash) {
            $values['password'] = $passwordHash;
        }

        $this->connection->update('users', $values, ['username' => $username], self::VALUE_TYPES);
    }
}
