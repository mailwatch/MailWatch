<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Domain;

final readonly class QuarantineAccess
{
    public function __construct(
        private string $role,
        private bool $domainAdministratorCanSeeDangerousContent,
        private bool $domainAdministratorCanReleaseDangerousContent,
    ) {
    }

    public function canView(bool $dangerous): bool
    {
        return !$dangerous
            || 'A' === $this->role
            || ('D' === $this->role && $this->domainAdministratorCanSeeDangerousContent);
    }

    public function canRelease(bool $dangerous): bool
    {
        return !$dangerous
            || 'A' === $this->role
            || ('D' === $this->role && $this->domainAdministratorCanReleaseDangerousContent);
    }

    public function canUseAlternateRecipient(bool $containsDangerousContent): bool
    {
        return 'A' === $this->role
            || ('D' === $this->role
                && (!$containsDangerousContent || $this->domainAdministratorCanReleaseDangerousContent));
    }
}
