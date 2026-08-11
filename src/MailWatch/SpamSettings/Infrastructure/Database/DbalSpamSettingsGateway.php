<?php

declare(strict_types=1);

namespace MailWatch\SpamSettings\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use MailWatch\SpamSettings\Application\SpamSettingsGateway;

final readonly class DbalSpamSettingsGateway implements SpamSettingsGateway
{
    private const SETTINGS = <<<'SQL'
        SELECT username, spamscore, highspamscore, noscan
        FROM users
        WHERE spamscore > 0 OR highspamscore > 0 OR noscan > 0
        SQL;

    public function __construct(private Connection $connection)
    {
    }

    public function settings(): array
    {
        return array_map(
            static fn(array $row): array => [
                'username' => (string)($row['username'] ?? ''),
                'spam_score' => (float)($row['spamscore'] ?? 0),
                'high_spam_score' => (float)($row['highspamscore'] ?? 0),
                'no_scan' => (int)($row['noscan'] ?? 0),
            ],
            $this->connection->fetchAllAssociative(self::SETTINGS),
        );
    }
}
