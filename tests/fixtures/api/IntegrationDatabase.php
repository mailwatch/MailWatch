<?php

final class Database
{
    public static function connect(...$arguments): MailWatchIntegrationDatabase
    {
        return new MailWatchIntegrationDatabase();
    }
}

final class MailWatchIntegrationDatabase
{
    public function prepare(string $query): MailWatchIntegrationStatement
    {
        return new MailWatchIntegrationStatement();
    }

    public function query(string $query): MailWatchIntegrationResult
    {
        $fixture = json_decode(
            (string)file_get_contents(TEST_FIXTURE_PATH),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (str_contains($query, 'FROM users')) {
            return new MailWatchIntegrationResult($fixture['users']);
        }

        $table = str_contains($query, 'FROM allowlist') ? 'allowlist' : 'blocklist';
        $rows = $fixture[$table];
        foreach ($fixture['user_filters'] as $filter) {
            foreach ($fixture[$table] as $entry) {
                if (strtolower($entry['to_address']) === strtolower($filter['username'])) {
                    $rows[] = [
                        'to_address' => $filter['filter'],
                        'from_address' => $entry['from_address'],
                    ];
                }
            }
        }

        return new MailWatchIntegrationResult($rows);
    }

    public function close(): void
    {
    }
}

final class MailWatchIntegrationResult
{
    private int $offset = 0;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(private readonly array $rows)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch_assoc(): ?array
    {
        return $this->rows[$this->offset++] ?? null;
    }

    public function free(): void
    {
    }
}

final class MailWatchIntegrationStatement
{
    /** @var list<mixed> */
    private array $values = [];

    public function bind_param(string $types, &...$values): void
    {
        if (strlen($types) !== count($values)) {
            throw new LogicException('Bind parameter count does not match its type declaration');
        }

        $this->values = &$values;
    }

    public function execute(): bool
    {
        $fields = [
            'timestamp', 'id', 'size', 'from', 'from_domain', 'to', 'to_domain', 'subject',
            'clientip', 'archiveplaces', 'isspam', 'ishigh', 'issaspam', 'isrblspam',
            'spamallowlisted', 'spamblocklisted', 'sascore', 'spamreport', 'virusinfected',
            'nameinfected', 'otherinfected', 'reports', 'ismcp', 'ishighmcp', 'issamcp',
            'mcpallowlisted', 'mcpblocklisted', 'mcpsascore', 'mcpreport', 'hostname',
            'date', 'time', 'headers', 'quarantined', 'rblspamreport', 'token', 'messageid',
            'ingestion_id',
        ];
        $insert = array_combine($fields, $this->values);
        if (false === $insert) {
            throw new LogicException('Unable to capture the MailWatch insert');
        }

        file_put_contents(TEST_CAPTURE_PATH, json_encode($insert, JSON_THROW_ON_ERROR));

        return true;
    }

    public function close(): void
    {
    }
}
