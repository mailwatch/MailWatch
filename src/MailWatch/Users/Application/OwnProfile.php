<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Users\Domain\AuthenticationSource;
use MailWatch\Users\Domain\LocalUserProfile;

final readonly class OwnProfile
{
    public function __construct(
        public LocalUserProfile $profile,
        public AuthenticationSource $authenticationSource,
    ) {
    }
}
