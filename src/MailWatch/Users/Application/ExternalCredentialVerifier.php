<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Users\Domain\ExternalIdentity;

interface ExternalCredentialVerifier
{
    public function authenticate(string $username, string $password): ?ExternalIdentity;
}
