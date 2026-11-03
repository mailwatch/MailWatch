<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

/**
 * Failure counters per client IP address and key. An installation without the
 * login_failures table counts nothing: every read finds no failure.
 */
interface LoginFailureGateway
{
    /**
     * @return array{failures: int, lastFailure: int}|null
     */
    public function failures(string $ip, string $key): ?array;

    /**
     * Counts one failure at $now; a counter whose last failure is at or before
     * $restartBefore starts again from one.
     */
    public function recordFailure(string $ip, string $key, int $now, int $restartBefore): void;

    public function clear(string $ip, string $key): void;
}
