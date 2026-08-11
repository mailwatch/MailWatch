<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Application;

use MailWatch\Shared\Application\Port\PasswordHasher;
use MailWatch\Users\Application\ManageOwnProfile;
use MailWatch\Users\Application\ProfilePasswordChangeDenied;
use MailWatch\Users\Application\UnknownLocalAccount;
use MailWatch\Users\Application\UserProfileGateway;
use MailWatch\Users\Domain\AuthenticationSource;
use MailWatch\Users\Domain\LocalUserProfile;
use MailWatch\Users\Domain\ProfilePreferences;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ManageOwnProfileTest extends TestCase
{
    public function testItReturnsTheLocalProfileWithItsAuthenticationSource(): void
    {
        $gateway = new RecordingUserProfileGateway();
        $gateway->profiles['user@example.test'] = self::profile();

        $overview = (new ManageOwnProfile($gateway, new RecordingPasswordHasher()))->overview(
            'user@example.test',
            AuthenticationSource::Ldap,
        );

        self::assertSame('User Name', $overview->profile->fullName);
        self::assertSame(AuthenticationSource::Ldap, $overview->authenticationSource);
    }

    public function testDatabaseUsersCanUpdatePreferencesAndPassword(): void
    {
        $gateway = new RecordingUserProfileGateway();
        $gateway->profiles['user@example.test'] = self::profile();
        $hasher = new RecordingPasswordHasher();
        $preferences = new ProfilePreferences(false, 4.2, 8.7, false, 'reports@example.test');

        (new ManageOwnProfile($gateway, $hasher))->update(
            'user@example.test',
            AuthenticationSource::Database,
            $preferences,
            'new secret',
        );

        self::assertSame(['new secret'], $hasher->passwords);
        self::assertSame($preferences, $gateway->updates[0]['preferences']);
        self::assertSame('hashed:new secret', $gateway->updates[0]['passwordHash']);
    }

    public function testAnEmptyDatabasePasswordLeavesTheExistingHashUntouched(): void
    {
        $gateway = new RecordingUserProfileGateway();
        $gateway->profiles['user@example.test'] = self::profile();
        $hasher = new RecordingPasswordHasher();

        (new ManageOwnProfile($gateway, $hasher))->update(
            'user@example.test',
            AuthenticationSource::Database,
            new ProfilePreferences(true, 0, 0, true, ''),
            '',
        );

        self::assertSame([], $hasher->passwords);
        self::assertNull($gateway->updates[0]['passwordHash']);
    }

    #[DataProvider('externalAuthenticationSources')]
    public function testExternalUsersCanUpdatePreferencesWithoutChangingPassword(
        AuthenticationSource $authenticationSource,
    ): void {
        $gateway = new RecordingUserProfileGateway();
        $gateway->profiles['user@example.test'] = self::profile();

        (new ManageOwnProfile($gateway, new RecordingPasswordHasher()))->update(
            'user@example.test',
            $authenticationSource,
            new ProfilePreferences(true, 1, 2, true, ''),
            null,
        );

        self::assertCount(1, $gateway->updates);
    }

    /** @return iterable<string, array{AuthenticationSource}> */
    public static function externalAuthenticationSources(): iterable
    {
        yield 'LDAP' => [AuthenticationSource::Ldap];
        yield 'IMAP' => [AuthenticationSource::Imap];
    }

    public function testItRejectsAPasswordInjectedForAnExternallyAuthenticatedUser(): void
    {
        $gateway = new RecordingUserProfileGateway();
        $gateway->profiles['user@example.test'] = self::profile();

        $this->expectException(ProfilePasswordChangeDenied::class);

        (new ManageOwnProfile($gateway, new RecordingPasswordHasher()))->update(
            'user@example.test',
            AuthenticationSource::Imap,
            new ProfilePreferences(true, 1, 2, true, ''),
            'injected password',
        );
    }

    public function testItReportsAnUnknownLocalProfile(): void
    {
        $this->expectException(UnknownLocalAccount::class);

        (new ManageOwnProfile(new RecordingUserProfileGateway(), new RecordingPasswordHasher()))->overview(
            'missing@example.test',
            AuthenticationSource::Database,
        );
    }

    private static function profile(): LocalUserProfile
    {
        return new LocalUserProfile(1, 'user@example.test', 'User Name', 'U', true, 3, 7, true, '');
    }
}

final class RecordingUserProfileGateway implements UserProfileGateway
{
    /** @var array<string, LocalUserProfile> */
    public array $profiles = [];

    /** @var list<array{username: string, preferences: ProfilePreferences, passwordHash: ?string}> */
    public array $updates = [];

    public function profileByUsername(string $username): ?LocalUserProfile
    {
        return $this->profiles[$username] ?? null;
    }

    public function update(string $username, ProfilePreferences $preferences, ?string $passwordHash): void
    {
        $this->updates[] = [
            'username' => $username,
            'preferences' => $preferences,
            'passwordHash' => $passwordHash,
        ];
    }
}

final class RecordingPasswordHasher implements PasswordHasher
{
    /** @var list<string> */
    public array $passwords = [];

    public function hash(string $password): string
    {
        $this->passwords[] = $password;

        return 'hashed:' . $password;
    }
}
