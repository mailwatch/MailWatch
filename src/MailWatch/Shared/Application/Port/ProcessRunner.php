<?php

declare(strict_types=1);

namespace MailWatch\Shared\Application\Port;

/**
 * Runs an external program and reports both its output and how it ended.
 *
 * The command is a list — the program followed by its arguments — and never a
 * string, because a string would have to be assembled for a shell and then
 * quoted correctly for it. There is no shell here: an argument containing a
 * space, a quote or a semicolon is one argument.
 *
 * Separate from CommandRunner, which serves the commands whose output is the
 * whole answer, and whose shell pipelines are part of what they are.
 */
interface ProcessRunner
{
    /**
     * @param list<string> $command           the program and its arguments
     * @param string|null  $standardInputFile a file to feed the program on standard input
     */
    public function run(array $command, ?string $standardInputFile = null): ProcessResult;
}
