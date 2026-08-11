<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

final readonly class ProfilePreferences
{
    public function __construct(
        public bool $quarantineReport,
        public float $spamScore,
        public float $highSpamScore,
        public bool $scanForSpam,
        public string $quarantineRecipient,
    ) {
    }
}
