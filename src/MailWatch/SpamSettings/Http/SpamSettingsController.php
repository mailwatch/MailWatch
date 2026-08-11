<?php

declare(strict_types=1);

namespace MailWatch\SpamSettings\Http;

use MailWatch\Shared\Http\ApiRequestContext;
use MailWatch\Shared\Http\ApiTelemetry;
use MailWatch\Shared\Http\JsonResponse;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;
use MailWatch\Shared\Infrastructure\Security\ApiKeyAuthenticator;
use MailWatch\SpamSettings\Application\GetSpamSettingsSnapshot;

final readonly class SpamSettingsController
{
    private const CONTRACT = 'mailwatch.spam-settings.v1';
    private const ENDPOINT = 'spam-settings';

    public function __construct(
        private ApiConfiguration $configuration,
        private ApiKeyAuthenticator $authenticator,
        private GetSpamSettingsSnapshot $getSnapshot,
        private ApiRequestContext $request,
        private ApiTelemetry $telemetry,
    ) {
    }

    public function handle(): never
    {
        $startedAt = hrtime(true);
        $headers = [
            ...$this->request->responseHeaders(),
            'Cache-Control: private, no-cache',
            'X-MailWatch-Contract: ' . self::CONTRACT,
        ];

        if ('GET' !== ($_SERVER['REQUEST_METHOD'] ?? null)) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'method_not_allowed', 405);
            JsonResponse::error(405, 'method_not_allowed', 'Method Not Allowed', [...$headers, 'Allow: GET']);
        }
        if (!$this->authenticator->isAuthorized($_SERVER['HTTP_X_MAILWATCH_API_KEY'] ?? null)) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'unauthorized', 401);
            JsonResponse::error(401, 'unauthorized', 'Unauthorized', $headers);
        }
        if ('1' !== ($_SERVER['HTTP_X_MAILWATCH_CONTRACT_VERSION'] ?? '1')) {
            $this->telemetry->rejected($this->request, self::ENDPOINT, 'unsupported_contract_version', 406);
            JsonResponse::error(406, 'unsupported_contract_version', 'Unsupported contract version', $headers);
        }

        try {
            $snapshotData = $this->getSnapshot->get()->toArray();
        } catch (\Throwable $exception) {
            $this->telemetry->failed($this->request, self::ENDPOINT, 'snapshot_unavailable', 500, $exception);
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
                $this->telemetry->completed($this->request, self::ENDPOINT, 'not_modified', 304, $startedAt);
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
            $this->telemetry->failed(
                $this->request,
                self::ENDPOINT,
                'snapshot_too_large',
                500,
            );
            JsonResponse::error(
                500,
                'snapshot_too_large',
                'Snapshot exceeds the configured response limit',
                $headers,
            );
        }

        $this->telemetry->completed($this->request, self::ENDPOINT, 'snapshot', 200, $startedAt);
        JsonResponse::send(200, $response, $headers);
    }
}
