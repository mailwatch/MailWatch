<?php

declare(strict_types=1);

namespace MailWatch\Lists\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use MailWatch\Lists\Application\AllowBlockListGateway;

final readonly class DbalAllowBlockListGateway implements AllowBlockListGateway
{
    private const ALLOWLIST = <<<'SQL'
        SELECT to_address, from_address FROM allowlist
        UNION ALL
        SELECT user_filters.filter AS to_address, allowlist.from_address
        FROM allowlist
        INNER JOIN user_filters ON allowlist.to_address = user_filters.username
        SQL;

    private const BLOCKLIST = <<<'SQL'
        SELECT to_address, from_address FROM blocklist
        UNION ALL
        SELECT user_filters.filter AS to_address, blocklist.from_address
        FROM blocklist
        INNER JOIN user_filters ON blocklist.to_address = user_filters.username
        SQL;

    public function __construct(private Connection $connection)
    {
    }

    public function entries(): array
    {
        return [
            'allowlist' => $this->load(self::ALLOWLIST),
            'blocklist' => $this->load(self::BLOCKLIST),
        ];
    }

    /**
     * @return list<array{to_address: string, from_address: string}>
     */
    private function load(string $query): array
    {
        return array_map(
            static fn(array $row): array => [
                'to_address' => (string)($row['to_address'] ?? ''),
                'from_address' => (string)($row['from_address'] ?? ''),
            ],
            $this->connection->fetchAllAssociative($query),
        );
    }
}
