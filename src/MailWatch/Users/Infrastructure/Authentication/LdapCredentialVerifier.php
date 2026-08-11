<?php

declare(strict_types=1);

namespace MailWatch\Users\Infrastructure\Authentication;

use MailWatch\Users\Application\ExternalCredentialVerifier;
use MailWatch\Users\Domain\ExternalIdentity;

final readonly class LdapCredentialVerifier implements ExternalCredentialVerifier
{
    public function authenticate(string $username, string $password): ?ExternalIdentity
    {
        return \ldap_authenticate($username, $password);
    }
}
