<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Application;

use MailWatch\Quarantine\Domain\QuarantineItem;

/**
 * Sends a held message on to its recipients.
 *
 * The two transports MailWatch offers do genuinely different things, and the
 * difference is visible to whoever receives the mail: over SMTP the parts
 * travel inside a covering message, while sendmail pipes the original message
 * as it was and ignores everything that is not one.
 */
interface QuarantineReleaser
{
    /**
     * @param string               $recipients one or more addresses, comma separated
     * @param list<QuarantineItem> $parts      the stored parts the operator selected
     */
    public function release(string $recipients, array $parts): ReleaseOutcome;
}
