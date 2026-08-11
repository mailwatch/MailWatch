<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\System;

use MailWatch\Shared\Application\Port\ProcessResult;
use MailWatch\Shared\Application\Port\ProcessRunner;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * No shell is involved, so nothing has to be quoted for one, and a stored file
 * name that happens to contain a semicolon is a file name.
 *
 * The timeout is the point of the component: a learner that hangs on a network
 * lookup used to hold the request open for as long as the web server allowed.
 */
final readonly class SymfonyProcessRunner implements ProcessRunner
{
    public function __construct(private ?float $timeout = 300.0)
    {
    }

    public function run(array $command, ?string $standardInputFile = null): ProcessResult
    {
        $process = new Process($command);
        $process->setTimeout($this->timeout);

        if (null !== $standardInputFile) {
            $input = @fopen($standardInputFile, 'rb');
            if (false === $input) {
                return new ProcessResult(1, ['Cannot read ' . $standardInputFile]);
            }
            $process->setInput($input);
        }

        try {
            $exitCode = $process->run();
        } catch (ExceptionInterface $exception) {
            // A timeout, or a program that could not be started at all. Both
            // are failures the caller reports the same way a non-zero exit is.
            return new ProcessResult(1, [$exception->getMessage()]);
        }

        return new ProcessResult(
            $exitCode,
            self::lines($process->getOutput() . $process->getErrorOutput()),
        );
    }

    /** @return list<string> */
    private static function lines(string $output): array
    {
        $trimmed = rtrim(str_replace("\r\n", "\n", $output), "\n");

        return '' === $trimmed ? [] : explode("\n", $trimmed);
    }
}
