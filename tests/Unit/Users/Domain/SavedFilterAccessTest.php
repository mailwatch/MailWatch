<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain;

use MailWatch\Users\Domain\LocalAccount;
use MailWatch\Users\Domain\SavedFilterAccess;
use PHPUnit\Framework\TestCase;

final class SavedFilterAccessTest extends TestCase
{
    public function testAdministratorsCanManageEveryAccount(): void
    {
        $access = new SavedFilterAccess('root', 'A', '', [], false);

        self::assertTrue($access->canView(new LocalAccount(1, 'admin@example.test', 'A')));
        self::assertTrue($access->canMutate(new LocalAccount(2, 'user@other.test', 'U')));
    }

    public function testDomainAdministratorsCanManageUsersInLocalAndDelegatedDomains(): void
    {
        $access = new SavedFilterAccess(
            'manager@example.test',
            'D',
            'example.test',
            ['delegated.test'],
            false,
        );

        self::assertTrue($access->canMutate(new LocalAccount(1, 'user@example.test', 'U')));
        self::assertTrue($access->canMutate(new LocalAccount(2, 'user@delegated.test', 'U')));
        self::assertFalse($access->canView(new LocalAccount(3, 'user@outside.test', 'U')));
        self::assertFalse($access->canView(new LocalAccount(4, 'root@example.test', 'A')));
        self::assertFalse($access->canView(new LocalAccount(5, 'peer@example.test', 'D')));
    }

    public function testDomainAdministratorsMayViewButNotChangeTheirOwnFilters(): void
    {
        $account = new LocalAccount(1, 'manager@example.test', 'D');
        $access = new SavedFilterAccess('manager@example.test', 'D', 'example.test', [], false);

        self::assertTrue($access->canView($account));
        self::assertFalse($access->canMutate($account));
    }

    public function testSuperDomainAdministratorsCanManagePeerDomainAdministrators(): void
    {
        $access = new SavedFilterAccess('manager@example.test', 'D', 'example.test', [], true);

        self::assertTrue($access->canMutate(new LocalAccount(1, 'peer@example.test', 'D')));
        self::assertFalse($access->canView(new LocalAccount(2, 'root@example.test', 'A')));
    }

    public function testDomainlessAdministratorsCanOnlyReachDomainlessAccounts(): void
    {
        $access = new SavedFilterAccess('manager', 'D', '', [], false);

        self::assertTrue($access->canMutate(new LocalAccount(1, 'user', 'U')));
        self::assertFalse($access->canView(new LocalAccount(2, 'user@example.test', 'U')));
    }
}
