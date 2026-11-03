<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Users\Domain\LoginThrottlePolicy;

final readonly class ThrottleLogins
{
    public function __construct(
        private LoginFailureGateway $gateway,
        private LoginThrottlePolicy $policy,
    ) {
    }

    public function isLocked(string $ip, string $username, int $now): bool
    {
        if (!$this->policy->enabled()) {
            return false;
        }
        $failures = $this->gateway->failures($ip, LoginThrottlePolicy::key($username));

        return null !== $failures
            && $this->policy->isLocked($failures['failures'], $failures['lastFailure'], $now);
    }

    public function recordFailure(string $ip, string $username, int $now): void
    {
        if (!$this->policy->enabled()) {
            return;
        }
        $this->gateway->recordFailure(
            $ip,
            LoginThrottlePolicy::key($username),
            $now,
            $now - $this->policy->lockoutSeconds,
        );
    }

    public function clear(string $ip, string $username): void
    {
        $this->gateway->clear($ip, LoginThrottlePolicy::key($username));
    }

    public function lockoutSeconds(): int
    {
        return $this->policy->lockoutSeconds;
    }
}
