<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine\Domain;

use MailWatch\Quarantine\Domain\QuarantineAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuarantineAccessTest extends TestCase
{
    #[DataProvider('dangerousContentCases')]
    public function testDangerousContentUsesIndependentViewAndReleasePermissions(
        string $role,
        bool $domainCanSee,
        bool $domainCanRelease,
        bool $canView,
        bool $canRelease,
    ): void {
        $access = new QuarantineAccess($role, $domainCanSee, $domainCanRelease);

        self::assertSame($canView, $access->canView(true));
        self::assertSame($canRelease, $access->canRelease(true));
    }

    /** @return iterable<string, array{string, bool, bool, bool, bool}> */
    public static function dangerousContentCases(): iterable
    {
        yield 'global administrator' => ['A', false, false, true, true];
        yield 'domain administrator with both permissions' => ['D', true, true, true, true];
        yield 'domain administrator can only view' => ['D', true, false, true, false];
        yield 'domain administrator can only release' => ['D', false, true, false, true];
        yield 'domain administrator without permissions' => ['D', false, false, false, false];
        yield 'ordinary user' => ['U', true, true, false, false];
    }

    public function testSafeContentCanBeViewedAndReleasedByEveryRole(): void
    {
        foreach (['A', 'D', 'U', 'R', 'H'] as $role) {
            $access = new QuarantineAccess($role, false, false);

            self::assertTrue($access->canView(false));
            self::assertTrue($access->canRelease(false));
        }
    }

    public function testAlternateRecipientsRemainLimitedToAdministrators(): void
    {
        self::assertTrue((new QuarantineAccess('A', false, false))->canUseAlternateRecipient(true));
        self::assertTrue((new QuarantineAccess('D', false, false))->canUseAlternateRecipient(false));
        self::assertFalse((new QuarantineAccess('D', true, false))->canUseAlternateRecipient(true));
        self::assertTrue((new QuarantineAccess('D', false, true))->canUseAlternateRecipient(true));
        self::assertFalse((new QuarantineAccess('U', true, true))->canUseAlternateRecipient(false));
    }
}
