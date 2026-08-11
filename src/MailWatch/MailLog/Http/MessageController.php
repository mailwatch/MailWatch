<?php

declare(strict_types=1);

namespace MailWatch\MailLog\Http;

use MailWatch\MailLog\Application\IngestionResult;
use MailWatch\MailLog\Application\IngestMailLog;
use MailWatch\MailLog\Domain\MailLogEntry;
use MailWatch\Shared\Http\JsonResponse;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;
use MailWatch\Shared\Infrastructure\Security\ApiKeyAuthenticator;

final readonly class MessageController
{
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
    ) {
    }

    public function handle(): never
    {
        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? null)) {
            JsonResponse::send(405, ['error' => 'Method Not Allowed']);
        }
        if (!$this->authenticator->isAuthorized($_SERVER['HTTP_X_MAILWATCH_API_KEY'] ?? null)) {
            JsonResponse::send(401, ['error' => 'Unauthorized']);
        }

        $data = $this->readPayload();
        foreach (self::RENAMED_FIELDS as $previousField => $currentField) {
            if (array_key_exists($previousField, $data) && !array_key_exists($currentField, $data)) {
                $data[$currentField] = $data[$previousField];
            }
        }

        try {
            $mailLogEntry = new MailLogEntry($data);
        } catch (\InvalidArgumentException) {
            JsonResponse::send(400, ['error' => 'Invalid data']);
        }
        if (!$mailLogEntry->isValid()) {
            JsonResponse::send(400, ['error' => 'Invalid data']);
        }
        foreach ($mailLogEntry->observations() as $observation) {
            error_log('MailWatch logmail compatibility observation: ' . $observation);
        }

        $idempotencyKey = $this->idempotencyKey($mailLogEntry);
        try {
            $result = $this->ingestMailLog->ingest($mailLogEntry, $idempotencyKey);
        } catch (\Throwable) {
            JsonResponse::send(500, ['error' => 'Failed to insert data']);
        }

        if (IngestionResult::Duplicate === $result) {
            JsonResponse::send(200, ['success' => 'Data already inserted', 'duplicate' => true]);
        }

        JsonResponse::send(201, ['success' => 'Data inserted successfully']);
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(): array
    {
        $maxPayloadBytes = $this->configuration->maxPayloadBytes();
        if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > $maxPayloadBytes) {
            JsonResponse::send(413, ['error' => 'Payload Too Large']);
        }

        $json = file_get_contents('php://input', false, null, 0, $maxPayloadBytes + 1);
        if (false === $json || strlen($json) > $maxPayloadBytes) {
            JsonResponse::send(413, ['error' => 'Payload Too Large']);
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            JsonResponse::send(400, ['error' => 'Invalid JSON']);
        }
        if (!is_array($data) || array_is_list($data)) {
            JsonResponse::send(400, ['error' => 'Invalid data']);
        }

        return $data;
    }

    private function idempotencyKey(MailLogEntry $entry): ?string
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
            JsonResponse::send(400, ['error' => 'Invalid idempotency key']);
        }

        return $idempotencyKey;
    }
}
