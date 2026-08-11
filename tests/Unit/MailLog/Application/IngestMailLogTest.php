<?php

declare(strict_types=1);

namespace App\Tests\Unit\MailLog\Application;

use MailWatch\MailLog\Application\IngestionResult;
use MailWatch\MailLog\Application\IngestMailLog;
use MailWatch\MailLog\Application\MailLogGateway;
use MailWatch\MailLog\Domain\MailLogEntry;
use PHPUnit\Framework\TestCase;

final class IngestMailLogTest extends TestCase
{
    public function testItDelegatesTheEntryAndIdempotencyKeyToTheGateway(): void
    {
        $gateway = new class implements MailLogGateway {
            public ?MailLogEntry $entry = null;
            public ?string $idempotencyKey = null;

            public function store(MailLogEntry $entry, ?string $idempotencyKey): IngestionResult
            {
                $this->entry = $entry;
                $this->idempotencyKey = $idempotencyKey;

                return IngestionResult::Duplicate;
            }
        };
        $entry = new MailLogEntry(['id' => 'message-001']);

        $result = (new IngestMailLog($gateway))->ingest($entry, 'same-key');

        self::assertSame(IngestionResult::Duplicate, $result);
        self::assertSame($entry, $gateway->entry);
        self::assertSame('same-key', $gateway->idempotencyKey);
    }
}
