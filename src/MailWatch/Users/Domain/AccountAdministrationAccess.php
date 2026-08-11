<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

final readonly class AccountAdministrationAccess
{
    /** @param list<string> $delegatedDomains */
    public function __construct(
        private string $actorUsername,
        private string $actorRole,
        private string $actorDomain,
        private array $delegatedDomains,
        private bool $superDomainAdministrators,
    ) {
    }

    public function canReach(string $username): bool
    {
        if ('A' === $this->actorRole) {
            return true;
        }
        if ('D' !== $this->actorRole) {
            return false;
        }

        $domain = $this->domainOf($username);
        if (null === $domain) {
            return '' === $this->actorDomain;
        }

        return in_array(
            strtolower($domain),
            array_map('strtolower', [...$this->delegatedDomains, $this->actorDomain]),
            true,
        );
    }

    public function canList(AccountSummary $target): bool
    {
        if ('A' === $this->actorRole) {
            return true;
        }
        if ('D' !== $this->actorRole || 'A' === $target->role) {
            return false;
        }

        $domain = $this->domainOf($target->username);
        if ('' === $this->actorDomain) {
            return null === $domain;
        }
        if (null === $domain) {
            return false;
        }
        if (0 === strcasecmp($domain, $this->actorDomain)) {
            return true;
        }

        return 'U' === $target->role && in_array(
            strtolower($domain),
            array_map('strtolower', $this->delegatedDomains),
            true,
        );
    }

    public function canEditTarget(ManagedAccount $target): bool
    {
        return 'A' === $this->actorRole
            || ('D' === $this->actorRole && 'A' !== $target->role);
    }

    public function canAssign(string $username, string $role): bool
    {
        if ('A' === $this->actorRole) {
            return true;
        }
        if ('D' !== $this->actorRole || 'A' === $role) {
            return false;
        }

        return $this->sameAccount($username)
            || 'U' === $role
            || $this->superDomainAdministrators;
    }

    public function canDelete(ManagedAccount $target): bool
    {
        if ($this->sameAccount($target->username)) {
            return false;
        }

        return 'A' === $this->actorRole
            || ('D' === $this->actorRole && 'U' === $target->role);
    }

    public function sameAccount(string $username): bool
    {
        return 0 === strcasecmp($this->actorUsername, $username);
    }

    public function domainOf(string $username): ?string
    {
        $separator = strrpos($username, '@');

        return false === $separator ? null : substr($username, $separator + 1);
    }
}
