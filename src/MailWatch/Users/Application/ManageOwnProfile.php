<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Shared\Application\Port\PasswordHasher;
use MailWatch\Users\Domain\AuthenticationSource;
use MailWatch\Users\Domain\ProfilePreferences;

final readonly class ManageOwnProfile
{
    public function __construct(
        private UserProfileGateway $gateway,
        private PasswordHasher $passwordHasher,
    ) {
    }

    public function overview(string $username, AuthenticationSource $authenticationSource): OwnProfile
    {
        $profile = $this->gateway->profileByUsername($username);
        if (null === $profile) {
            throw new UnknownLocalAccount();
        }

        return new OwnProfile($profile, $authenticationSource);
    }

    public function update(
        string $username,
        AuthenticationSource $authenticationSource,
        ProfilePreferences $preferences,
        ?string $newPassword,
    ): void {
        if (null === $this->gateway->profileByUsername($username)) {
            throw new UnknownLocalAccount();
        }

        $newPassword = null === $newPassword || '' === $newPassword ? null : $newPassword;
        if (null !== $newPassword && !$authenticationSource->canChangePassword()) {
            throw new ProfilePasswordChangeDenied();
        }

        $this->gateway->update(
            $username,
            $preferences,
            null === $newPassword ? null : $this->passwordHasher->hash($newPassword),
        );
    }
}
