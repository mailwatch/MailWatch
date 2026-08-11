<?php

declare(strict_types=1);

namespace MailWatch\Shared\Http;

use Psr\Log\LoggerInterface;

final readonly class ApiTelemetry
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function rejected(
        ApiRequestContext $request,
        string $endpoint,
        string $reason,
        int $status,
    ): void {
        $this->logger->warning('mailwatch.api.request.rejected', [
            ...$this->requestContext($request, $endpoint, $status),
            'reason' => $reason,
        ]);
    }

    public function failed(
        ApiRequestContext $request,
        string $endpoint,
        string $reason,
        int $status,
        ?\Throwable $exception = null,
    ): void {
        $context = [
            ...$this->requestContext($request, $endpoint, $status),
            'reason' => $reason,
        ];
        if (null !== $exception) {
            $context['exception_class'] = $exception::class;
        }

        $this->logger->error('mailwatch.api.request.failed', $context);
    }

    public function completed(
        ApiRequestContext $request,
        string $endpoint,
        string $outcome,
        int $status,
        int $startedAt,
    ): void {
        $this->logger->info('mailwatch.api.request.completed', [
            ...$this->requestContext($request, $endpoint, $status),
            'outcome' => $outcome,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
        ]);
    }

    public function observation(ApiRequestContext $request, string $endpoint, string $observation): void
    {
        $this->logger->notice('mailwatch.api.compatibility.observation', [
            'request_id' => $request->requestId(),
            'endpoint' => $endpoint,
            'observation' => $observation,
        ]);
    }

    /**
     * @return array{request_id: string, endpoint: string, status: int}
     */
    private function requestContext(ApiRequestContext $request, string $endpoint, int $status): array
    {
        return [
            'request_id' => $request->requestId(),
            'endpoint' => $endpoint,
            'status' => $status,
        ];
    }
}
