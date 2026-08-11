<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

final readonly class ManagedAccount
{
    public function __construct(
        public int $id,
        public string $username,
        public string $fullName,
        public string $role,
        public bool $quarantineReport,
        public float $spamScore,
        public float $highSpamScore,
        public bool $scanForSpam,
        public string $quarantineRecipient,
        public int $loginTimeout,
        public int $lastLogin,
    ) {
    }
}
