<?php

declare(strict_types=1);

namespace MailWatch\MailLog\Http;

use MailWatch\MailLog\Application\IngestionResult;
use MailWatch\MailLog\Application\IngestMailLog;
use MailWatch\MailLog\Domain\MailLogEntry;
use MailWatch\Shared\Http\ApiRequestContext;
use MailWatch\Shared\Http\ApiTelemetry;
use MailWatch\Shared\Http\JsonResponse;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;
use MailWatch\Shared\Infrastructure\Security\ApiKeyAuthenticator;

final readonly class MessageController
{
    private const ENDPOINT = 'messages';

    /** @var array<string, string> */
    private const RENAMED_FIELDS = [
        'spamwhitelisted' => 'spamallowlisted',
        'spamblacklisted' => 'spamblocklisted',
        'mcpwhitelisted' => 'mcpallowlisted',
        'mcpblacklisted' => 'mcpblocklisted',
    ];

    public function __construct(
        private ApiConfiguration $configuration,
        private ApiKeyAuthenticator $authenticator,
        private IngestMailLog $ingestMailLog,
        private ApiRequestContext $request,
        private ApiTelemetry $telemetry,
    ) {
    }

    public function handle(): never
    {
        $startedAt = hrtime(true);
        $headers = $this->request->responseHeaders();

        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? null)) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'method_not_allowed', 405);
            JsonResponse::send(405, ['error' => 'Method Not Allowed'], $headers);
        }
        if (!$this->authenticator->isAuthorized($_SERVER['HTTP_X_MAILWATCH_API_KEY'] ?? null)) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'unauthorized', 401);
            JsonResponse::send(401, ['error' => 'Unauthorized'], $headers);
        }

        $data = $this->readPayload($headers);
        foreach (self::RENAMED_FIELDS as $previousField => $currentField) {
            if (array_key_exists($previousField, $data) && !array_key_exists($currentField, $data)) {
                $data[$currentField] = $data[$previousField];
            }
        }

        try {
            $mailLogEntry = new MailLogEntry($data);
        } catch (\InvalidArgumentException) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'invalid_data', 400);
            JsonResponse::send(400, ['error' => 'Invalid data'], $headers);
        }
        if (!$mailLogEntry->isValid()) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'invalid_data', 400);
            JsonResponse::send(400, ['error' => 'Invalid data'], $headers);
        }
        foreach ($mailLogEntry->observations() as $observation) {
            $this->telemetry->observation($this->request, self::ENDPOINT, $observation);
        }

        $idempotencyKey = $this->idempotencyKey($mailLogEntry, $headers);
        try {
            $result = $this->ingestMailLog->ingest($mailLogEntry, $idempotencyKey);
        } catch (\Throwable $exception) {
            $this->telemetry->failed($this->request, self::ENDPOINT, 'ingestion_failed', 500, $exception);
            JsonResponse::send(500, ['error' => 'Failed to insert data'], $headers);
        }

        if (IngestionResult::Duplicate === $result) {
            $this->telemetry->completed($this->request, self::ENDPOINT, 'duplicate', 200, $startedAt);
            JsonResponse::send(200, ['success' => 'Data already inserted', 'duplicate' => true], $headers);
        }

        $this->telemetry->completed($this->request, self::ENDPOINT, 'inserted', 201, $startedAt);
        JsonResponse::send(201, ['success' => 'Data inserted successfully'], $headers);
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, mixed>
     */
    private function readPayload(array $headers): array
    {
        $maxPayloadBytes = $this->configuration->maxPayloadBytes();
        if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > $maxPayloadBytes) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'payload_too_large', 413);
            JsonResponse::send(413, ['error' => 'Payload Too Large'], $headers);
        }

        $json = file_get_contents('php://input', false, null, 0, $maxPayloadBytes + 1);
        if (false === $json || strlen($json) > $maxPayloadBytes) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'payload_too_large', 413);
            JsonResponse::send(413, ['error' => 'Payload Too Large'], $headers);
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'invalid_json', 400);
            JsonResponse::send(400, ['error' => 'Invalid JSON'], $headers);
        }
        if (!is_array($data) || array_is_list($data)) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'invalid_data', 400);
            JsonResponse::send(400, ['error' => 'Invalid data'], $headers);
        }

        return $data;
    }

    /**
     * @param list<string> $headers
     */
    private function idempotencyKey(MailLogEntry $entry, array $headers): ?string
    {
        $receivedKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;
        if (!is_string($receivedKey)) {
            return null;
        }

        $idempotencyKey = strtolower(trim($receivedKey));
        $expectedKey = hash('sha256', $entry->hostname . "\0" . $entry->id . "\0" . $entry->token);
        if (
            1 !== preg_match('/^[a-f0-9]{64}$/', $idempotencyKey)
            || '' === $entry->hostname
            || '' === $entry->token
            || !hash_equals($expectedKey, $idempotencyKey)
        ) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'invalid_idempotency_key', 400);
            JsonResponse::send(400, ['error' => 'Invalid idempotency key'], $headers);
        }

        return $idempotencyKey;
    }
}
