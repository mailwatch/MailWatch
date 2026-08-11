<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Domain;

/**
 * The maillog row behind a quarantined message.
 *
 * Only the columns the quarantine operations need: where the message was
 * processed, which directory holds it, who it was for, and whether it counts
 * as spam or as dangerous content.
 */
final readonly class QuarantinedMessage
{
    public function __construct(
        public string $id,
        public string $hostname,
        /** The quarantine directory for this message, as Ymd; empty when the row carries no date. */
        public string $storageDate,
        public string $recipients,
        public bool $spam,
        public bool $dangerous,
    ) {
    }
}
