<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

final class DuplicateLocalAccount extends AccountAdministrationException
{
    public function __construct(public readonly string $username)
    {
        parent::__construct();
    }
}
