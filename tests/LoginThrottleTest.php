<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../mailscanner/loginthrottle.inc.php';

class LoginThrottleTest extends TestCase
{
    /**
     * @dataProvider provideLockStates
     */
    public function testIsLockedAt($failures, $lastFailure, $now, $maxFailures, $expected)
    {
        $this->assertSame($expected, login_throttle_is_locked_at($failures, $lastFailure, $now, $maxFailures, 900));
    }

    public function provideLockStates()
    {
        return [
            'below the limit' => [4, 1000, 1001, 5, false],
            'at the limit' => [5, 1000, 1001, 5, true],
            'last second of the lockout' => [5, 1000, 1899, 5, true],
            'lockout over' => [5, 1000, 1900, 5, false],
            'clock moved back' => [5, 1000, 500, 5, true],
            'lockout disabled' => [50, 1000, 1001, 0, false],
        ];
    }

    public function testUserHashIgnoresCaseAndSpaces()
    {
        $this->assertSame(login_throttle_user_hash('User@Example.com'), login_throttle_user_hash(' user@example.com'));
        $this->assertNotSame(login_throttle_user_hash('user@example.com'), login_throttle_user_hash('other@example.com'));
    }
}
