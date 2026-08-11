<?php

declare(strict_types=1);

namespace MailWatch\Shared\Application\Port;

interface PasswordHasher
{
    public function hash(string $password): string;
}
