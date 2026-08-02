<?php

declare(strict_types=1);

namespace MailWatch\MailLog\Http;

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
        $database = ($this->connectDatabase)();
        $statement = $database->prepare(
            'INSERT INTO maillog (timestamp, id, size, from_address, from_domain, to_address, to_domain, subject, clientip, archive, isspam, ishighspam, issaspam, isrblspam, spamallowlisted, spamblocklisted, sascore, spamreport, virusinfected, nameinfected, otherinfected, report, ismcp, ishighmcp, issamcp, mcpallowlisted, mcpblocklisted, mcpsascore, mcpreport, hostname, date, time, headers, quarantined, rblspamreport, token, messageid, ingestion_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        if (!$statement) {
            $database->close();
            JsonResponse::send(500, ['error' => 'Failed to prepare statement']);
        }

        $statement->bind_param(
            'ssisssssssiiiiiidsiiisiiiiidsssssissss',
            $mailLogEntry->timestamp,
            $mailLogEntry->id,
            $mailLogEntry->size,
            $mailLogEntry->from,
            $mailLogEntry->from_domain,
            $mailLogEntry->to,
            $mailLogEntry->to_domain,
            $mailLogEntry->subject,
            $mailLogEntry->clientip,
            $mailLogEntry->archiveplaces,
            $mailLogEntry->isspam,
            $mailLogEntry->ishigh,
            $mailLogEntry->issaspam,
            $mailLogEntry->isrblspam,
            $mailLogEntry->spamallowlisted,
            $mailLogEntry->spamblocklisted,
            $mailLogEntry->sascore,
            $mailLogEntry->spamreport,
            $mailLogEntry->virusinfected,
            $mailLogEntry->nameinfected,
            $mailLogEntry->otherinfected,
            $mailLogEntry->reports,
            $mailLogEntry->ismcp,
            $mailLogEntry->ishighmcp,
            $mailLogEntry->issamcp,
            $mailLogEntry->mcpallowlisted,
            $mailLogEntry->mcpblocklisted,
            $mailLogEntry->mcpsascore,
            $mailLogEntry->mcpreport,
            $mailLogEntry->hostname,
            $mailLogEntry->date,
            $mailLogEntry->time,
            $mailLogEntry->headers,
            $mailLogEntry->quarantined,
            $mailLogEntry->rblspamreport,
            $mailLogEntry->token,
            $mailLogEntry->messageid,
            $idempotencyKey,
        );

        $status = 500;
        $body = ['error' => 'Failed to insert data'];
        try {
            if ($statement->execute()) {
                $status = 201;
                $body = ['success' => 'Data inserted successfully'];
            }
        } catch (\mysqli_sql_exception $exception) {
            if (1062 === $exception->getCode() && null !== $idempotencyKey) {
                $status = 200;
                $body = ['success' => 'Data already inserted', 'duplicate' => true];
            }
        } catch (\Throwable) {
        }

        $statement->close();
        $database->close();
        JsonResponse::send($status, $body);
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
