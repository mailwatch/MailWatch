<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

final readonly class AccountSummary
{
    public function __construct(
        public int $id,
        public string $username,
        public string $fullName,
        public string $role,
        public bool $scanForSpam,
        public float $spamScore,
        public float $highSpamScore,
        public int $loginExpiry,
    ) {
    }

    public function isLoggedIn(int $now): bool
    {
        return 0 === $this->loginExpiry || $this->loginExpiry > $now;
    }
}
