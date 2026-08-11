<?php

declare(strict_types=1);

namespace MailWatch\Users\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use MailWatch\Users\Application\UserProfileGateway;
use MailWatch\Users\Domain\LocalUserProfile;
use MailWatch\Users\Domain\ProfilePreferences;

final readonly class DbalUserProfileGateway implements UserProfileGateway
{
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

        $this->connection->update('users', $values, ['username' => $username]);
    }
}
