<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Application;

/**
 * How an attempt to release a message ended.
 *
 * A failure comes in two shapes, because the transports do: a program that
 * exited with a status, or a refusal with nothing but a reason. The caller
 * needs to tell them apart to say what happened.
 */
final readonly class ReleaseOutcome
{
    /** @param list<string> $detail */
    private function __construct(
        public bool $delivered,
        public ?int $exitCode,
        public array $detail,
    ) {
    }

    public static function delivered(): self
    {
        return new self(true, null, []);
    }

    public static function refused(string $reason): self
    {
        return new self(false, null, [$reason]);
    }

    /** @param list<string> $output */
    public static function failed(int $exitCode, array $output): self
    {
        return new self(false, $exitCode, $output);
    }
}
