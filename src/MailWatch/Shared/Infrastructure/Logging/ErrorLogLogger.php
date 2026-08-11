<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\Logging;

use Psr\Log\AbstractLogger;

final class ErrorLogLogger extends AbstractLogger
{
    /**
     * @param mixed                $level
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $record = json_encode([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => (string)$level,
            'event' => (string)$message,
            ...$context,
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        error_log('MailWatch API ' . (false === $record ? '{"event":"logging_failure"}' : $record));
    }
}
