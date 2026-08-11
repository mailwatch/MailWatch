<?php

declare(strict_types=1);

namespace MailWatch\Shared\Application\Port;

/**
 * What an external command left behind: how it ended, and what it said.
 */
final readonly class ProcessResult
{
    /** @param list<string> $output */
    public function __construct(
        public int $exitCode,
        public array $output,
    ) {
    }

    public function succeeded(): bool
    {
        return 0 === $this->exitCode;
    }
}
