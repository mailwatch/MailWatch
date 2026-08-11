<?php

declare(strict_types=1);

namespace MailWatch\MailLog\Application;

use MailWatch\MailLog\Domain\MailLogEntry;

interface MailLogGateway
{
    public function store(MailLogEntry $entry, ?string $idempotencyKey): IngestionResult;
}
