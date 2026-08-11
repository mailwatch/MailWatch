<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

final readonly class AuthenticatedUser
{
    public function __construct(
        public LoginAccount $account,
        public AuthenticationSource $source,
        public bool $passwordHashUpgraded = false,
    ) {
    }
}
