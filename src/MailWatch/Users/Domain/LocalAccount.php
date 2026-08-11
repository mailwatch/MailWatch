<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

final readonly class LocalAccount
{
    public function __construct(
        public int $id,
        public string $username,
        public string $role,
    ) {
    }
}
