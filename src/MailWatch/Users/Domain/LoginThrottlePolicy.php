<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

/**
 * How many failures one client IP address may make on one key (a username,
 * or a fixed name for other guarded requests) before it has to wait.
 */
final readonly class LoginThrottlePolicy
{
    /**
     * @param int $maxFailures    failures that lock the key, 0 or less never locks
     * @param int $lockoutSeconds how long the lock lasts after the last failure
     */
    public function __construct(
        public int $maxFailures,
        public int $lockoutSeconds,
    ) {
    }

    public function enabled(): bool
    {
        return 0 < $this->maxFailures;
    }

    public function isLocked(int $failures, int $lastFailure, int $now): bool
    {
        return $this->enabled()
            && $failures >= $this->maxFailures
            && $now - $lastFailure < $this->lockoutSeconds;
    }

    /**
     * The username is stored hashed: users sometimes type their password in the username field.
     */
    public static function key(string $username): string
    {
        // usernames compare case-insensitively in the users table
        return sha1(strtolower(trim($username)));
    }
}
