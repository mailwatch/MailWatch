<?php

declare(strict_types=1);

namespace App\Tests\Support;

use MailWatch\Shared\Application\Port\ProcessResult;
use MailWatch\Shared\Application\Port\ProcessRunner;

final class RecordingProcessRunner implements ProcessRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<string|null> */
    public array $standardInputFiles = [];

    /**
     * @param list<string> $output
     */
    public function __construct(
        private readonly int $exitCode = 0,
        private readonly array $output = [],
    ) {
    }

    public function run(array $command, ?string $standardInputFile = null): ProcessResult
    {
        $this->commands[] = $command;
        $this->standardInputFiles[] = $standardInputFile;

        return new ProcessResult($this->exitCode, $this->output);
    }
}
