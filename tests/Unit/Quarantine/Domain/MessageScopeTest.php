<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine\Domain;

use MailWatch\Quarantine\Domain\MessageScope;
use PHPUnit\Framework\TestCase;

final class MessageScopeTest extends TestCase
{
    public function testAnAdministratorIsUnrestricted(): void
    {
        $scope = MessageScope::forAccount('admin@example.test', 'A', ['someone@example.test'], false);

        self::assertTrue($scope->isUnrestricted());
        self::assertSame([], $scope->addresses());
        self::assertSame([], $scope->domains());
    }

    public function testAUserOwnsTheirAccountAndTheirFilters(): void
    {
        $scope = MessageScope::forAccount(
            'Owner@Example.test',
            'U',
            ['alias@example.test', 'owner@example.test', ' '],
            false,
        );

        self::assertFalse($scope->isUnrestricted());
        self::assertSame(['alias@example.test', 'owner@example.test'], $scope->addresses());
        self::assertSame([], $scope->domains());
        self::assertTrue($scope->includesSender());
    }

    public function testRecipientOnlyDropsTheSenderColumns(): void
    {
        $scope = MessageScope::forAccount('owner@example.test', 'U', [], true);

        self::assertFalse($scope->includesSender());
    }

    public function testADomainAdministratorSplitsAddressesFromDomainsAndKeepsTheirOwn(): void
    {
        $scope = MessageScope::forAccount(
            'admin@example.test',
            'D',
            ['other.test', 'delegate@third.test'],
            false,
        );

        self::assertFalse($scope->isUnrestricted());
        self::assertSame(['admin@example.test', 'delegate@third.test'], $scope->addresses());
        self::assertSame(['example.test', 'other.test'], $scope->domains());
    }

    public function testADomainAdministratorNamedAfterADomainAddsNoAddress(): void
    {
        $scope = MessageScope::forAccount('example.test', 'D', [], false);

        self::assertSame([], $scope->addresses());
        self::assertSame(['example.test'], $scope->domains());
    }

    public function testAnUnknownRoleReachesNothing(): void
    {
        $scope = MessageScope::forAccount('owner@example.test', 'H', ['host.example.test'], false);

        self::assertFalse($scope->isUnrestricted());
        self::assertSame([], $scope->addresses());
        self::assertSame([], $scope->domains());
    }

    /**
     * The legacy filter produced an empty string here, which turned the
     * surrounding WHERE clause into a syntax error rather than a denial.
     */
    public function testAnAccountWithoutAnyIdentityReachesNothing(): void
    {
        $scope = MessageScope::forAccount('  ', 'U', [], false);

        self::assertFalse($scope->isUnrestricted());
        self::assertSame([], $scope->addresses());
    }

    public function testTheUnrestrictedScopeIsWhatTheUnauthenticatedCallersGet(): void
    {
        self::assertTrue(MessageScope::unrestricted()->isUnrestricted());
        self::assertFalse(MessageScope::denied()->isUnrestricted());
    }
}
