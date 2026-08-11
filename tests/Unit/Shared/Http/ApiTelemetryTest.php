<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use MailWatch\Shared\Http\ApiRequestContext;
use MailWatch\Shared\Http\ApiTelemetry;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ApiTelemetryTest extends TestCase
{
    public function testFailureLogsOnlyTheExceptionClassAndSafeRequestMetadata(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string)$message,
                    'context' => $context,
                ];
            }
        };
        $telemetry = new ApiTelemetry($logger);
        $request = ApiRequestContext::fromServer(['HTTP_X_REQUEST_ID' => 'safe-request-id']);
        $exception = new \RuntimeException(
            'api-key=never-log-this; payload=private; SELECT secret FROM users'
        );

        $telemetry->failed($request, 'messages', 'ingestion_failed', 500, $exception);

        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame('mailwatch.api.request.failed', $logger->records[0]['message']);
        self::assertSame([
            'request_id' => 'safe-request-id',
            'endpoint' => 'messages',
            'status' => 500,
            'reason' => 'ingestion_failed',
            'exception_class' => \RuntimeException::class,
        ], $logger->records[0]['context']);

        $serialized = json_encode($logger->records, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('never-log-this', $serialized);
        self::assertStringNotContainsString('private', $serialized);
        self::assertStringNotContainsString('SELECT secret', $serialized);
    }

    public function testCompletionIsACompactStructuredTimingRecord(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string)$message,
                    'context' => $context,
                ];
            }
        };
        $telemetry = new ApiTelemetry($logger);
        $request = ApiRequestContext::fromServer(['HTTP_X_REQUEST_ID' => 'timed-request']);

        $telemetry->completed($request, 'spam-settings', 'snapshot', 200, hrtime(true));

        self::assertSame('info', $logger->records[0]['level']);
        self::assertSame('mailwatch.api.request.completed', $logger->records[0]['message']);
        self::assertSame('timed-request', $logger->records[0]['context']['request_id']);
        self::assertSame('spam-settings', $logger->records[0]['context']['endpoint']);
        self::assertSame('snapshot', $logger->records[0]['context']['outcome']);
        self::assertIsFloat($logger->records[0]['context']['duration_ms']);
        self::assertGreaterThanOrEqual(0, $logger->records[0]['context']['duration_ms']);
    }
}
