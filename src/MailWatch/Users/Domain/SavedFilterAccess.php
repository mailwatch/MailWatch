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

    /**
     * A filter widens what the target account sees, so it may only point into
     * the domains the actor manages.
     */
    public function canAssign(string $filter): bool
    {
        if ('A' === $this->actorRole) {
            return true;
        }

        if ('D' !== $this->actorRole) {
            return false;
        }

        $separator = strrpos($filter, '@');
        $filterDomain = false === $separator ? $filter : substr($filter, $separator + 1);

        return '' !== $filterDomain && in_array(strtolower($filterDomain), $this->managedDomains(), true);
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

        return in_array(strtolower(substr($username, $separator + 1)), $this->managedDomains(), true);
    }

    /** @return list<string> */
    private function managedDomains(): array
    {
        return array_map('strtolower', [...$this->delegatedDomains, $this->actorDomain]);
    }
}
