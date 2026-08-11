<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

final class AccountDomainAccessDenied extends AccountAdministrationException
{
    public function __construct(
        public readonly string $operation,
        public readonly ?string $domain,
    ) {
        parent::__construct();
    }
}
