<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Users\Domain\LocalUserProfile;
use MailWatch\Users\Domain\ProfilePreferences;

interface UserProfileGateway
{
    public function profileByUsername(string $username): ?LocalUserProfile;

    public function update(
        string $username,
        ProfilePreferences $preferences,
        ?string $passwordHash,
    ): void;
}
