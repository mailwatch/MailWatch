<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\System;

use MailWatch\Shared\Application\Port\CommandRunner;

final readonly class ShellCommandRunner implements CommandRunner
{
    public function run(string $command): string
    {
        $output = shell_exec($command);

        return \is_string($output) ? $output : '';
    }
}
