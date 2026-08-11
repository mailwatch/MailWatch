<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

/**
 * The local account policy for viewing and changing saved filters.
 *
 * Authentication providers establish identity only. The local role, domain
 * and delegated filters remain the authority for this administration scope.
 */
final readonly class SavedFilterAccess
{
    /**
     * @param list<string> $delegatedDomains
     */
    public function __construct(
        private string $actorUsername,
        private string $actorRole,
        private string $actorDomain,
        private array $delegatedDomains,
        private bool $superDomainAdministrators,
    ) {
    }

    public function canView(LocalAccount $target): bool
    {
        if ('A' === $this->actorRole) {
            return true;
        }

        if ('D' !== $this->actorRole || 'A' === $target->role) {
            return false;
        }

        if (!$this->sameDomain($target->username)) {
            return false;
        }

        return $this->sameAccount($target)
            || 'U' === $target->role
            || $this->superDomainAdministrators;
    }

    public function canMutate(LocalAccount $target): bool
    {
        return $this->canView($target)
            && ('A' === $this->actorRole || !$this->sameAccount($target));
    }

    private function sameAccount(LocalAccount $target): bool
    {
        return 0 === strcasecmp($this->actorUsername, $target->username);
    }

    private function sameDomain(string $username): bool
    {
        $separator = strrpos($username, '@');
        if (false === $separator) {
            return '' === $this->actorDomain;
        }

        $targetDomain = strtolower(substr($username, $separator + 1));
        $domains = array_map('strtolower', [...$this->delegatedDomains, $this->actorDomain]);

        return in_array($targetDomain, $domains, true);
    }
}
