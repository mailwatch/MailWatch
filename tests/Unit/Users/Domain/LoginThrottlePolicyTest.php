<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain;

use MailWatch\Users\Domain\LoginThrottlePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoginThrottlePolicyTest extends TestCase
{
    #[DataProvider('lockStates')]
    public function testIsLocked(int $failures, int $lastFailure, int $now, int $maxFailures, bool $expected): void
    {
        self::assertSame($expected, (new LoginThrottlePolicy($maxFailures, 900))->isLocked($failures, $lastFailure, $now));
    }

    /** @return iterable<string, array{int, int, int, int, bool}> */
    public static function lockStates(): iterable
    {
        yield 'below the limit' => [4, 1000, 1001, 5, false];
        yield 'at the limit' => [5, 1000, 1001, 5, true];
        yield 'last second of the lockout' => [5, 1000, 1899, 5, true];
        yield 'lockout over' => [5, 1000, 1900, 5, false];
        yield 'clock moved back' => [5, 1000, 500, 5, true];
        yield 'lockout disabled' => [50, 1000, 1001, 0, false];
    }

    public function testTheKeyIgnoresCaseAndSurroundingSpaces(): void
    {
        self::assertSame(LoginThrottlePolicy::key('User@Example.com'), LoginThrottlePolicy::key(' user@example.com'));
        self::assertNotSame(LoginThrottlePolicy::key('user@example.com'), LoginThrottlePolicy::key('other@example.com'));
    }
}
