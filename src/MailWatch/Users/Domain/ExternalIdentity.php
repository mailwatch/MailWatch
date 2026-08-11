<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

final readonly class ExternalIdentity
{
    public function __construct(
        public string $username,
        public string $fullName,
        public AuthenticationSource $source,
        public bool $provisionLocalAccount,
    ) {
    }
}
