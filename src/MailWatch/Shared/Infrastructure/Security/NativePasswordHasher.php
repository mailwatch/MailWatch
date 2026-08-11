<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\Security;

use MailWatch\Shared\Application\Port\PasswordHasher;
use MailWatch\Shared\Application\Port\PasswordVerifier;

final readonly class NativePasswordHasher implements PasswordHasher, PasswordVerifier
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
