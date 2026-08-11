<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Users\Domain\LoginAccount;

interface LoginAccountGateway
{
    public function accountByUsername(string $username): ?LoginAccount;

    public function provisionExternalAccount(string $username, string $fullName): void;

    public function updatePasswordHash(string $username, string $passwordHash): void;

    public function recordSuccessfulLogin(string $username, int $loginExpiry, int $loginTime): void;
}
