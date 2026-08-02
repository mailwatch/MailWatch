<?php

declare(strict_types=1);

namespace MailWatch\Api;

use MailWatch\Configuration\ApiConfiguration;
use MailWatch\Security\ApiKeyAuthenticator;

final readonly class SpamSettingsController
{
    private const CONTRACT = 'mailwatch.spam-settings.v1';

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
            $result = $database->query(
                'SELECT username, spamscore, highspamscore, noscan
                 FROM users
                 WHERE spamscore > 0 OR highspamscore > 0 OR noscan > 0',
            );
            if (false === $result) {
                throw new \RuntimeException('Unable to read spam settings snapshot');
            }

            $spamScores = [];
            $highSpamScores = [];
            $noScan = [];
            while ($row = $result->fetch_assoc()) {
                $username = strtolower((string)($row['username'] ?? ''));
                $spamScore = (float)($row['spamscore'] ?? 0);
                $highSpamScore = (float)($row['highspamscore'] ?? 0);
                $noScanValue = (int)($row['noscan'] ?? 0);

                if ($spamScore > 0) {
                    $spamScores[$username] = $spamScore;
                }
                if ($highSpamScore > 0) {
                    $highSpamScores[$username] = $highSpamScore;
                }
                if ($noScanValue > 0) {
                    $noScan[] = $username;
                }
            }
            $result->free();
            $database->close();
        } catch (\Throwable) {
            JsonResponse::error(500, 'snapshot_unavailable', 'Snapshot unavailable', $headers);
        }

        ksort($spamScores, SORT_STRING);
        ksort($highSpamScores, SORT_STRING);
        sort($noScan, SORT_STRING);
        $snapshotData = [
            'spam_scores' => (object)$spamScores,
            'high_spam_scores' => (object)$highSpamScores,
            'no_scan' => $noScan,
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
}
