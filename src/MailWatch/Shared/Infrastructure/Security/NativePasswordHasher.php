<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\Security;

use MailWatch\Shared\Application\Port\PasswordHasher;

final readonly class NativePasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
