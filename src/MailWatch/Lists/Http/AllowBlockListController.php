<?php

declare(strict_types=1);

namespace MailWatch\Lists\Http;

use MailWatch\Shared\Http\JsonResponse;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;
use MailWatch\Shared\Infrastructure\Security\ApiKeyAuthenticator;

final readonly class AllowBlockListController
{
    private const CONTRACT = 'mailwatch.allow-block-list.v1';

    /**
     * @param \Closure(): object $connectDatabase
     */
    public function __construct(
        private ApiConfiguration $configuration,
        private ApiKeyAuthenticator $authenticator,
        private \Closure $connectDatabase,
    ) {
    }

    public function handle(): never
    {
        $headers = [
            'Cache-Control: private, no-cache',
            'X-MailWatch-Contract: ' . self::CONTRACT,
        ];

        if ('GET' !== ($_SERVER['REQUEST_METHOD'] ?? null)) {
            JsonResponse::error(405, 'method_not_allowed', 'Method Not Allowed', [...$headers, 'Allow: GET']);
        }
        if (!$this->authenticator->isAuthorized($_SERVER['HTTP_X_MAILWATCH_API_KEY'] ?? null)) {
            JsonResponse::error(401, 'unauthorized', 'Unauthorized', $headers);
        }
        if ('1' !== ($_SERVER['HTTP_X_MAILWATCH_CONTRACT_VERSION'] ?? '1')) {
            JsonResponse::error(406, 'unsupported_contract_version', 'Unsupported contract version', $headers);
        }

        try {
            $database = ($this->connectDatabase)();
            $allowlist = $this->loadSnapshot($database, 'allowlist');
            $blocklist = $this->loadSnapshot($database, 'blocklist');
            $database->close();
        } catch (\Throwable) {
            JsonResponse::error(500, 'snapshot_unavailable', 'Snapshot unavailable', $headers);
        }

        $snapshotData = [
            'allowlist' => $allowlist,
            'blocklist' => $blocklist,
        ];
        $snapshotVersion = hash(
            'sha256',
            json_encode($snapshotData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        $etag = '"' . $snapshotVersion . '"';
        $headers[] = 'ETag: ' . $etag;

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        if (is_string($ifNoneMatch)) {
            $validators = array_map('trim', explode(',', $ifNoneMatch));
            if (in_array('*', $validators, true) || in_array($etag, $validators, true)) {
                JsonResponse::empty(304, $headers);
            }
        }

        $response = [
            'contract' => self::CONTRACT,
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'snapshot_version' => $snapshotVersion,
            ...$snapshotData,
        ];
        if (strlen(json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) > $this->configuration->maxSnapshotBytes()) {
            JsonResponse::error(
                500,
                'snapshot_too_large',
                'Snapshot exceeds the configured response limit',
                $headers,
            );
        }

        JsonResponse::send(200, $response, $headers);
    }

    /**
     * @return list<array{to_address: string, from_address: string}>
     */
    private function loadSnapshot(object $database, string $table): array
    {
        $query = "SELECT to_address, from_address FROM {$table}
                  UNION ALL
                  SELECT user_filters.filter AS to_address, {$table}.from_address
                  FROM {$table}
                  INNER JOIN user_filters ON {$table}.to_address = user_filters.username";
        $result = $database->query($query);
        if (false === $result) {
            throw new \RuntimeException('Unable to read list snapshot');
        }

        $entries = [];
        while ($row = $result->fetch_assoc()) {
            $entries[] = [
                'to_address' => strtolower((string)($row['to_address'] ?? '')),
                'from_address' => strtolower((string)($row['from_address'] ?? '')),
            ];
        }
        $result->free();

        usort(
            $entries,
            static fn(array $left, array $right): int => [$left['to_address'], $left['from_address']]
                <=> [$right['to_address'], $right['from_address']],
        );

        return $entries;
    }
}
