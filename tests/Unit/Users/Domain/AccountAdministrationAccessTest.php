<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain;

use MailWatch\Users\Domain\AccountAdministrationAccess;
use MailWatch\Users\Domain\AccountSummary;
use MailWatch\Users\Domain\ManagedAccount;
use PHPUnit\Framework\TestCase;

final class AccountAdministrationAccessTest extends TestCase
{
    public function testAdministratorsCanManageEveryAccountExceptDeletingThemselves(): void
    {
        $access = new AccountAdministrationAccess('root', 'A', '', [], false);

        self::assertTrue($access->canReach('user@other.test'));
        self::assertTrue($access->canAssign('user@other.test', 'A'));
        self::assertTrue($access->canEditTarget(self::account('other-admin', 'A')));
        self::assertFalse($access->canDelete(self::account('root', 'A')));
        self::assertTrue($access->canDelete(self::account('other-admin', 'A')));
    }

    public function testDomainAdministratorsReachTheirOwnAndDelegatedDomains(): void
    {
        $access = new AccountAdministrationAccess(
            'manager@example.test',
            'D',
            'example.test',
            ['delegated.test'],
            false,
        );

        self::assertTrue($access->canReach('user@example.test'));
        self::assertTrue($access->canReach('user@delegated.test'));
        self::assertFalse($access->canReach('user@outside.test'));
        self::assertFalse($access->canReach('domainless'));
    }

    public function testDomainlessAdministratorsOnlyReachDomainlessAccounts(): void
    {
        $access = new AccountAdministrationAccess('manager', 'D', '', [], false);

        self::assertTrue($access->canReach('user'));
        self::assertFalse($access->canReach('user@example.test'));
    }

    public function testRoleAssignmentPreservesTheLegacySuperDomainAdministratorRules(): void
    {
        $regular = new AccountAdministrationAccess('manager@example.test', 'D', 'example.test', [], false);
        $super = new AccountAdministrationAccess('manager@example.test', 'D', 'example.test', [], true);

        self::assertTrue($regular->canAssign('user@example.test', 'U'));
        self::assertFalse($regular->canAssign('peer@example.test', 'D'));
        self::assertTrue($super->canAssign('peer@example.test', 'D'));
        self::assertFalse($super->canAssign('peer@example.test', 'A'));
    }

    public function testDomainAdministratorsOnlyDeleteOrdinaryUsers(): void
    {
        $access = new AccountAdministrationAccess('manager@example.test', 'D', 'example.test', [], true);

        self::assertTrue($access->canDelete(self::account('user@example.test', 'U')));
        self::assertFalse($access->canDelete(self::account('peer@example.test', 'D')));
        self::assertFalse($access->canDelete(self::account('manager@example.test', 'D')));
    }

    public function testAdministratorsCanListEveryAccount(): void
    {
        $access = new AccountAdministrationAccess('root', 'A', '', [], false);

        self::assertTrue($access->canList(self::summary('admin@example.test', 'A')));
        self::assertTrue($access->canList(self::summary('user@outside.test', 'U')));
        self::assertTrue($access->canList(self::summary('domainless', 'R')));
    }

    public function testDomainAdministratorsListTheirDomainAndOnlyUsersInDelegatedDomains(): void
    {
        $access = new AccountAdministrationAccess(
            'manager@example.test',
            'D',
            'example.test',
            ['delegated.test'],
            false,
        );

        self::assertTrue($access->canList(self::summary('peer@example.test', 'D')));
        self::assertTrue($access->canList(self::summary('user@delegated.test', 'U')));
        self::assertFalse($access->canList(self::summary('peer@delegated.test', 'D')));
        self::assertFalse($access->canList(self::summary('root@example.test', 'A')));
        self::assertFalse($access->canList(self::summary('user@outside.test', 'U')));
        self::assertFalse($access->canList(self::summary('domainless', 'U')));
    }

    public function testDomainlessAdministratorsOnlyListDomainlessNonAdministrators(): void
    {
        $access = new AccountAdministrationAccess('manager', 'D', '', [], false);

        self::assertTrue($access->canList(self::summary('user', 'U')));
        self::assertFalse($access->canList(self::summary('root', 'A')));
        self::assertFalse($access->canList(self::summary('user@example.test', 'U')));
    }

    private static function account(string $username, string $role): ManagedAccount
    {
        return new ManagedAccount(1, $username, '', $role, false, 0, 0, true, '', -1, -1);
    }

    private static function summary(string $username, string $role): AccountSummary
    {
        return new AccountSummary(1, $username, '', $role, true, 0, 0, -1);
    }
}
