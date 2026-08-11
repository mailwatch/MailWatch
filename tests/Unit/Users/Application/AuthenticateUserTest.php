<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Application;

use MailWatch\Shared\Application\Port\PasswordHasher;
use MailWatch\Shared\Application\Port\PasswordVerifier;
use MailWatch\Users\Application\AuthenticateUser;
use MailWatch\Users\Application\EmptyPassword;
use MailWatch\Users\Application\ExternalCredentialVerifier;
use MailWatch\Users\Application\InvalidCredentials;
use MailWatch\Users\Application\LoginAccountGateway;
use MailWatch\Users\Domain\AuthenticationSource;
use MailWatch\Users\Domain\ExternalIdentity;
use MailWatch\Users\Domain\LoginAccount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthenticateUserTest extends TestCase
{
    public function testLdapSuccessStopsTheProviderChain(): void
    {
        $gateway = new RecordingLoginAccountGateway();
        $gateway->accounts['ldap@example.test'] = self::account('ldap@example.test');
        $ldap = new RecordingExternalCredentialVerifier(
            new ExternalIdentity('ldap@example.test', 'LDAP User', AuthenticationSource::Ldap, true),
        );
        $imap = new RecordingExternalCredentialVerifier(
            new ExternalIdentity('imap@example.test', 'IMAP User', AuthenticationSource::Imap, false),
        );

        $result = self::authenticator($gateway, [$ldap, $imap])->authenticate('login', 'secret', 1000);

        self::assertSame(AuthenticationSource::Ldap, $result->source);
        self::assertSame('ldap@example.test', $result->account->username);
        self::assertSame([['login', 'secret']], $ldap->attempts);
        self::assertSame([], $imap->attempts);
    }

    public function testImapRunsAfterLdapDeclinesTheCredentials(): void
    {
        $gateway = new RecordingLoginAccountGateway();
        $gateway->accounts['user@example.test'] = self::account('user@example.test');
        $ldap = new RecordingExternalCredentialVerifier(null);
        $imap = new RecordingExternalCredentialVerifier(
            new ExternalIdentity('user@example.test', 'User', AuthenticationSource::Imap, false),
        );

        $result = self::authenticator($gateway, [$ldap, $imap])->authenticate('USER@example.test', 'secret', 1000);

        self::assertSame(AuthenticationSource::Imap, $result->source);
        self::assertCount(1, $ldap->attempts);
        self::assertCount(1, $imap->attempts);
    }

    public function testAnExternalIdentityCanProvisionItsRequiredLocalAccount(): void
    {
        $gateway = new RecordingLoginAccountGateway();
        $ldap = new RecordingExternalCredentialVerifier(
            new ExternalIdentity('user@example.test', 'Directory Name', AuthenticationSource::Ldap, true),
        );

        $result = self::authenticator($gateway, [$ldap])->authenticate('user', 'secret', 1000);

        self::assertSame([['user@example.test', 'Directory Name']], $gateway->provisioned);
        self::assertSame('user@example.test', $result->account->username);
        self::assertNull($result->account->passwordHash);
    }

    public function testExternalSuccessWithoutProvisioningStillRequiresALocalAccount(): void
    {
        $gateway = new RecordingLoginAccountGateway();
        $imap = new RecordingExternalCredentialVerifier(
            new ExternalIdentity('user@example.test', 'User', AuthenticationSource::Imap, false),
        );

        $this->expectException(InvalidCredentials::class);

        self::authenticator($gateway, [$imap])->authenticate('user@example.test', 'secret', 1000);
    }

    public function testLocalAuthenticationRunsAfterExternalProvidersDecline(): void
    {
        $gateway = new RecordingLoginAccountGateway();
        $gateway->accounts['local@example.test'] = self::account('local@example.test', 'current-hash');
        $passwords = new RecordingPasswordService(['current-hash']);

        $result = self::authenticator(
            $gateway,
            [new RecordingExternalCredentialVerifier(null)],
            $passwords,
        )->authenticate('local@example.test', 'secret', 1000);

        self::assertSame(AuthenticationSource::Database, $result->source);
        self::assertFalse($result->passwordHashUpgraded);
        self::assertSame([], $gateway->passwordUpdates);
    }

    public function testLegacyMd5PasswordsAreUpgradedAfterSuccessfulVerification(): void
    {
        $gateway = new RecordingLoginAccountGateway();
        $gateway->accounts['local@example.test'] = self::account('local@example.test', md5('secret'));

        $result = self::authenticator($gateway)->authenticate('local@example.test', 'secret', 1000);

        self::assertTrue($result->passwordHashUpgraded);
        self::assertSame([['local@example.test', 'new:secret']], $gateway->passwordUpdates);
    }

    public function testModernHashesAreRehashedWhenTheAlgorithmRequiresIt(): void
    {
        $gateway = new RecordingLoginAccountGateway();
        $gateway->accounts['local@example.test'] = self::account('local@example.test', 'old-modern-hash');
        $passwords = new RecordingPasswordService(['old-modern-hash'], ['old-modern-hash']);

        $result = self::authenticator($gateway, [], $passwords)->authenticate('local@example.test', 'secret', 1000);

        self::assertTrue($result->passwordHashUpgraded);
        self::assertSame([['local@example.test', 'new:secret']], $gateway->passwordUpdates);
    }

    public function testInvalidLocalCredentialsAreRejected(): void
    {
        $gateway = new RecordingLoginAccountGateway();
        $gateway->accounts['local@example.test'] = self::account('local@example.test', 'different-hash');

        $this->expectException(InvalidCredentials::class);

        self::authenticator($gateway)->authenticate('local@example.test', 'secret', 1000);
    }

    public function testAnEmptyPasswordIsRejectedAfterConfiguredProvidersDeclineIt(): void
    {
        $provider = new RecordingExternalCredentialVerifier(null);

        try {
            self::authenticator(new RecordingLoginAccountGateway(), [$provider])->authenticate('user', '', 1000);
            self::fail('The empty password should have been rejected.');
        } catch (EmptyPassword) {
            self::assertSame([['user', '']], $provider->attempts);
        }
    }

    #[DataProvider('loginExpiryCases')]
    public function testItRecordsTheLegacyLoginExpiryRules(
        int $accountTimeout,
        ?int $globalTimeout,
        int $expectedExpiry,
    ): void {
        $gateway = new RecordingLoginAccountGateway();
        $gateway->accounts['local@example.test'] = self::account(
            'local@example.test',
            'current-hash',
            $accountTimeout,
        );
        $passwords = new RecordingPasswordService(['current-hash']);

        self::authenticator($gateway, [], $passwords, $globalTimeout)
            ->authenticate('local@example.test', 'secret', 1000);

        self::assertSame([['local@example.test', $expectedExpiry, 1000]], $gateway->successfulLogins);
    }

    /** @return iterable<string, array{int, ?int, int}> */
    public static function loginExpiryCases(): iterable
    {
        yield 'individual expiry' => [300, 600, 1300];
        yield 'individual session never expires' => [0, 600, 0];
        yield 'global expiry' => [-1, 900, 1900];
        yield 'missing global uses ten minutes' => [-1, null, 1600];
        yield 'disabled global never expires' => [-1, 0, 0];
        yield 'invalid global never expires' => [-1, 100000, 0];
    }

    /** @param list<ExternalCredentialVerifier> $providers */
    private static function authenticator(
        RecordingLoginAccountGateway $gateway,
        array $providers = [],
        ?RecordingPasswordService $passwords = null,
        ?int $globalTimeout = 600,
    ): AuthenticateUser {
        $passwords ??= new RecordingPasswordService();

        return new AuthenticateUser($gateway, $passwords, $passwords, $providers, $globalTimeout);
    }

    private static function account(
        string $username,
        ?string $passwordHash = null,
        int $loginTimeout = -1,
    ): LoginAccount {
        return new LoginAccount($username, 'User Name', 'U', $passwordHash, $loginTimeout, ['alias@example.test']);
    }
}

final class RecordingExternalCredentialVerifier implements ExternalCredentialVerifier
{
    /** @var list<array{string, string}> */
    public array $attempts = [];

    public function __construct(private readonly ?ExternalIdentity $identity)
    {
    }

    public function authenticate(string $username, string $password): ?ExternalIdentity
    {
        $this->attempts[] = [$username, $password];

        return $this->identity;
    }
}

final class RecordingLoginAccountGateway implements LoginAccountGateway
{
    /** @var array<string, LoginAccount> */
    public array $accounts = [];

    /** @var list<array{string, string}> */
    public array $provisioned = [];

    /** @var list<array{string, string}> */
    public array $passwordUpdates = [];

    /** @var list<array{string, int, int}> */
    public array $successfulLogins = [];

    public function accountByUsername(string $username): ?LoginAccount
    {
        return $this->accounts[$username] ?? null;
    }

    public function provisionExternalAccount(string $username, string $fullName): void
    {
        $this->provisioned[] = [$username, $fullName];
        $this->accounts[$username] = new LoginAccount($username, $fullName, 'U', null, -1, []);
    }

    public function updatePasswordHash(string $username, string $passwordHash): void
    {
        $this->passwordUpdates[] = [$username, $passwordHash];
    }

    public function recordSuccessfulLogin(string $username, int $loginExpiry, int $loginTime): void
    {
        $this->successfulLogins[] = [$username, $loginExpiry, $loginTime];
    }
}

final class RecordingPasswordService implements PasswordHasher, PasswordVerifier
{
    /**
     * @param list<string> $validHashes
     * @param list<string> $rehashHashes
     */
    public function __construct(
        private readonly array $validHashes = [],
        private readonly array $rehashHashes = [],
    ) {
    }

    public function hash(string $password): string
    {
        return 'new:' . $password;
    }

    public function verify(string $password, string $hash): bool
    {
        return 'secret' === $password && in_array($hash, $this->validHashes, true);
    }

    public function needsRehash(string $hash): bool
    {
        return in_array($hash, $this->rehashHashes, true);
    }
}
