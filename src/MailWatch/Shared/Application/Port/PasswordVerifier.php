<?php

declare(strict_types=1);

namespace MailWatch\Shared\Application\Port;

interface PasswordVerifier
{
    public function verify(string $password, string $hash): bool;

    public function needsRehash(string $hash): bool;
}
