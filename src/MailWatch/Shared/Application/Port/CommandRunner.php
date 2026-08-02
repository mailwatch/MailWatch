<?php

declare(strict_types=1);

namespace MailWatch\Shared\Application\Port;

/**
 * Runs an external command and returns what it wrote to standard output.
 *
 * Standard error is not captured, matching what the page scripts did with
 * passthru(): diagnostics from a scanner belong in the web server log, not in
 * the page.
 */
interface CommandRunner
{
    public function run(string $command): string;
}
