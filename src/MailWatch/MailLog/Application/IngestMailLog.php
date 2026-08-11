<?php

declare(strict_types=1);

namespace MailWatch\MailLog\Application;

use MailWatch\MailLog\Domain\MailLogEntry;

final readonly class IngestMailLog
{
    public function __construct(private MailLogGateway $gateway)
    {
    }

    public function ingest(MailLogEntry $entry, ?string $idempotencyKey): IngestionResult
    {
        return $this->gateway->store($entry, $idempotencyKey);
    }
}
