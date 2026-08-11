<?php

declare(strict_types=1);

namespace MailWatch\SpamSettings\Http;

use MailWatch\Shared\Http\JsonResponse;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;
use MailWatch\Shared\Infrastructure\Security\ApiKeyAuthenticator;
use MailWatch\SpamSettings\Application\GetSpamSettingsSnapshot;

final readonly class SpamSettingsController
{
    private const CONTRACT = 'mailwatch.spam-settings.v1';

    public function __construct(
        private ApiConfiguration $configuration,
        private ApiKeyAuthenticator $authenticator,
        private GetSpamSettingsSnapshot $getSnapshot,
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
            $snapshotData = $this->getSnapshot->get()->toArray();
        } catch (\Throwable) {
            JsonResponse::error(500, 'snapshot_unavailable', 'Snapshot unavailable', $headers);
        }

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
