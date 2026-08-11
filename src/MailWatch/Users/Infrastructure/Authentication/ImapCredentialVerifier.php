<?php

declare(strict_types=1);

namespace MailWatch\Users\Infrastructure\Authentication;

use MailWatch\Users\Application\ExternalCredentialVerifier;
use MailWatch\Users\Domain\ExternalIdentity;

final readonly class ImapCredentialVerifier implements ExternalCredentialVerifier
{
    public function authenticate(string $username, string $password): ?ExternalIdentity
    {
        return \imap_authenticate($username, $password);
    }
}
