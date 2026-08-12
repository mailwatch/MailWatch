<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Domain;

/**
 * The covering message a released item travels in.
 *
 * Only the SMTP transport has one: releasing through sendmail pipes the
 * original message as it was, and takes nothing from here but the envelope
 * sender.
 */
final readonly class ReleaseNotice
{
    public function __construct(
        public string $from,
        public string $subject,
        public string $body,
    ) {
    }
}
