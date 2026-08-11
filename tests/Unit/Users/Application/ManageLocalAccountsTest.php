<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Application;

use MailWatch\Shared\Application\Port\PasswordHasher;
use MailWatch\Users\Application\AccountAccessDenied;
use MailWatch\Users\Application\AccountAdministrationGateway;
use MailWatch\Users\Application\AccountDomainAccessDenied;
use MailWatch\Users\Application\DuplicateLocalAccount;
use MailWatch\Users\Application\ManageLocalAccounts;
use MailWatch\Users\Application\OwnAccountDeletionDenied;
use MailWatch\Users\Application\UnknownLocalAccount;
use MailWatch\Users\Domain\AccountProfile;
use MailWatch\Users\Domain\AccountSummary;
use MailWatch\Users\Domain\ManagedAccount;
use PHPUnit\Framework\TestCase;

final class ManageLocalAccountsTest extends TestCase
{
    public function testItCreatesAnAccountWithAHashedPassword(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $hasher = new AccountRecordingPasswordHasher();

        (new ManageLocalAccounts($gateway, $hasher))->create(
            'root',
            'A',
            '',
            false,
            self::profile('user@example.test'),
            'secret',
        );

        self::assertSame(['secret'], $hasher->passwords);
        self::assertSame('hashed:secret', $gateway->created[0]['passwordHash']);
    }

    public function testItRejectsDuplicateUsernames(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'user@example.test');

        $this->expectException(DuplicateLocalAccount::class);

        (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->create(
            'root',
            'A',
            '',
            false,
            self::profile('user@example.test'),
            'secret',
        );
    }

    public function testItRejectsAccountsOutsideADelegatedDomain(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();

        $this->expectException(AccountDomainAccessDenied::class);

        (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->create(
            'manager@example.test',
            'D',
            'example.test',
            false,
            self::profile('user@outside.test'),
            'secret',
        );
    }

    public function testAnEmptyPasswordPreservesTheHashAndRenamesTheAccount(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'old@example.test');

        $previous = (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->update(
            'root',
            'A',
            '',
            false,
            1,
            self::profile('new@example.test'),
            '',
        );

        self::assertSame('old@example.test', $previous->username);
        self::assertNull($gateway->updated[0]['passwordHash']);
        self::assertSame('new@example.test', $gateway->updated[0]['profile']->username);
    }

    public function testDomainAdministratorsCannotEditGlobalAdministrators(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'root@example.test', 'A');

        $this->expectException(AccountAccessDenied::class);

        (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->account(
            'manager@example.test',
            'D',
            'example.test',
            true,
            1,
        );
    }

    public function testItDeletesTheAccountThroughTheGateway(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'user@example.test');

        $deleted = (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->delete(
            'manager@example.test',
            'D',
            'example.test',
            false,
            1,
        );

        self::assertSame('user@example.test', $deleted->username);
        self::assertSame([1], $gateway->deleted);
    }

    public function testItRejectsDeletingTheCurrentAccount(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'root', 'A');

        $this->expectException(OwnAccountDeletionDenied::class);

        (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->delete(
            'root',
            'A',
            '',
            false,
            1,
        );
    }

    public function testItReportsAnUnknownAccount(): void
    {
        $this->expectException(UnknownLocalAccount::class);

        (new ManageLocalAccounts(
            new RecordingAccountAdministrationGateway(),
            new AccountRecordingPasswordHasher(),
        ))->account('root', 'A', '', false, 99);
    }

    public function testOverviewOnlyIncludesAccountsVisibleToTheDomainAdministrator(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->delegatedDomains['manager@example.test'] = ['delegated.test'];
        $gateway->summaries = [
            self::summary(1, 'root@example.test', 'A'),
            self::summary(2, 'peer@example.test', 'D'),
            self::summary(3, 'user@example.test'),
            self::summary(4, 'user@delegated.test'),
            self::summary(5, 'peer@delegated.test', 'D'),
            self::summary(6, 'user@outside.test'),
            self::summary(7, 'domainless'),
        ];

        $accounts = (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->overview(
            'manager@example.test',
            'D',
            'example.test',
            false,
        );

        self::assertSame(
            ['peer@example.test', 'user@example.test', 'user@delegated.test'],
            array_map(static fn(AccountSummary $account): string => $account->username, $accounts),
        );
    }

    public function testItAuthorizesReportsInsideADelegatedDomain(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'user@delegated.test');
        $gateway->delegatedDomains['manager@example.test'] = ['delegated.test'];

        $account = (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->accountForReport(
            'manager@example.test',
            'D',
            'example.test',
            false,
            1,
        );

        self::assertSame('user@delegated.test', $account->username);
    }

    public function testItRejectsReportsOutsideTheAdministratorsDomains(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'user@outside.test');

        $this->expectException(AccountDomainAccessDenied::class);

        (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->accountForReport(
            'manager@example.test',
            'D',
            'example.test',
            false,
            1,
        );
    }

    public function testOrdinaryUsersCanRequestTheirOwnReport(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'user@example.test');

        $account = (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->accountForReport(
            'user@example.test',
            'U',
            'example.test',
            false,
            1,
        );

        self::assertSame('user@example.test', $account->username);
    }

    public function testOrdinaryUsersCannotRequestAnotherUsersReport(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'other@example.test');

        $this->expectException(AccountDomainAccessDenied::class);

        (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->accountForReport(
            'user@example.test',
            'U',
            'example.test',
            false,
            1,
        );
    }

    public function testItForcesLogoutThroughTheGateway(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'user@example.test');

        (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->forceLogout(
            'manager@example.test',
            'D',
            'example.test',
            false,
            1,
        );

        self::assertSame([1], $gateway->loggedOut);
    }

    public function testRegularDomainAdministratorsCannotForceLogoutPeerAdministrators(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'peer@example.test', 'D');

        $this->expectException(AccountAccessDenied::class);

        (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->forceLogout(
            'manager@example.test',
            'D',
            'example.test',
            false,
            1,
        );
    }

    public function testSuperDomainAdministratorsCanForceLogoutPeerAdministrators(): void
    {
        $gateway = new RecordingAccountAdministrationGateway();
        $gateway->accounts[1] = self::account(1, 'peer@example.test', 'D');

        (new ManageLocalAccounts($gateway, new AccountRecordingPasswordHasher()))->forceLogout(
            'manager@example.test',
            'D',
            'example.test',
            true,
            1,
        );

        self::assertSame([1], $gateway->loggedOut);
    }

    private static function profile(string $username): AccountProfile
    {
        return new AccountProfile($username, 'User Name', 'U', true, 3, 7, true, '', -1);
    }

    private static function account(int $id, string $username, string $role = 'U'): ManagedAccount
    {
        return new ManagedAccount($id, $username, 'User Name', $role, true, 3, 7, true, '', -1, -1);
    }

    private static function summary(int $id, string $username, string $role = 'U'): AccountSummary
    {
        return new AccountSummary($id, $username, 'User Name', $role, true, 3, 7, -1);
    }
}

final class RecordingAccountAdministrationGateway implements AccountAdministrationGateway
{
    /** @var array<int, ManagedAccount> */
    public array $accounts = [];

    /** @var list<AccountSummary> */
    public array $summaries = [];

    /** @var array<string, list<string>> */
    public array $delegatedDomains = [];

    /** @var list<array{profile: AccountProfile, passwordHash: string}> */
    public array $created = [];

    /** @var list<array{target: ManagedAccount, profile: AccountProfile, passwordHash: ?string}> */
    public array $updated = [];

    /** @var list<int> */
    public array $deleted = [];

    /** @var list<int> */
    public array $loggedOut = [];

    public function accountById(int $id): ?ManagedAccount
    {
        return $this->accounts[$id] ?? null;
    }

    public function accountSummaries(): array
    {
        return $this->summaries;
    }

    public function delegatedDomainsFor(string $username): array
    {
        return $this->delegatedDomains[$username] ?? [];
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        foreach ($this->accounts as $id => $account) {
            if ($id !== $exceptId && $username === $account->username) {
                return true;
            }
        }

        return false;
    }

    public function create(AccountProfile $profile, string $passwordHash): void
    {
        $this->created[] = ['profile' => $profile, 'passwordHash' => $passwordHash];
    }

    public function update(ManagedAccount $target, AccountProfile $profile, ?string $passwordHash): void
    {
        $this->updated[] = ['target' => $target, 'profile' => $profile, 'passwordHash' => $passwordHash];
    }

    public function delete(ManagedAccount $target): void
    {
        $this->deleted[] = $target->id;
    }

    public function forceLogout(ManagedAccount $target): void
    {
        $this->loggedOut[] = $target->id;
    }
}

final class AccountRecordingPasswordHasher implements PasswordHasher
{
    /** @var list<string> */
    public array $passwords = [];

    public function hash(string $password): string
    {
        $this->passwords[] = $password;

        return 'hashed:' . $password;
    }
}
