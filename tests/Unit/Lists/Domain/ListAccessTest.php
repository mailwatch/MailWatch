<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lists\Domain;

use MailWatch\Lists\Domain\ListAccess;
use MailWatch\Lists\Domain\ListEntry;
use PHPUnit\Framework\TestCase;

final class ListAccessTest extends TestCase
{
    public function testAUserCanManageOnlyItsOwnAndActiveFilteredAddresses(): void
    {
        $access = ListAccess::forAccount(
            'Owner@Example.test',
            'U',
            ['Alias@Example.test', 'domain-only.example.test', 'ALIAS@example.test'],
        );

        self::assertSame(['alias@example.test', 'owner@example.test'], $access->selectableAddresses());
        self::assertTrue($access->canAdd('ALIAS@example.test', 'example.test'));
        self::assertFalse($access->canAdd('other@example.test', 'example.test'));
        self::assertTrue($access->canView(new ListEntry(1, 'sender', 'domain-only.example.test', '')));
        self::assertFalse($access->canView(new ListEntry(2, 'sender', 'other@example.test', 'example.test')));
    }

    public function testADomainAdministratorUsesItsDomainAndActiveFilters(): void
    {
        $access = ListAccess::forAccount('admin@example.test', 'D', ['delegated.example.test']);

        self::assertSame(['delegated.example.test', 'example.test'], $access->domains());
        self::assertTrue($access->canAdd('recipient@delegated.example.test', 'delegated.example.test'));
        self::assertTrue($access->canView(new ListEntry(1, 'sender', 'recipient@example.test', 'example.test')));
        self::assertFalse($access->canAdd('recipient@other.test', 'other.test'));
    }

    public function testAdministratorsAreUnrestrictedAndUnsupportedRolesHaveNoListAccess(): void
    {
        $entry = new ListEntry(1, 'sender', 'recipient@example.test', 'example.test');

        $administrator = ListAccess::forAccount('root', 'A', []);
        self::assertTrue($administrator->canAdd('anything', 'anywhere'));
        self::assertTrue($administrator->canView($entry));

        $regexUser = ListAccess::forAccount('pattern', 'R', []);
        self::assertFalse($regexUser->canAdd('anything', 'anywhere'));
        self::assertFalse($regexUser->canView($entry));
    }
}
