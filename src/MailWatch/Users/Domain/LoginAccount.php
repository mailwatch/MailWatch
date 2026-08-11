<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

final readonly class LoginAccount
{
    /** @param list<string> $activeFilters */
    public function __construct(
        public string $username,
        public string $fullName,
        public string $role,
        public ?string $passwordHash,
        public int $loginTimeout,
        public array $activeFilters,
    ) {
    }
}
