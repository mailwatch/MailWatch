<?php

declare(strict_types=1);

namespace App\Tests\Support;

use MailWatch\Shared\Application\Port\CommandRunner;

final class RecordingCommandRunner implements CommandRunner
{
    /** @var list<string> */
    public array $commands = [];

    /**
     * @param array<string, string> $responses output keyed by the exact command
     */
    public function __construct(private readonly array $responses = [])
    {
    }

    public function run(string $command): string
    {
        $this->commands[] = $command;

        return $this->responses[$command] ?? '';
    }
}
