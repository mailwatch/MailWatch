<?php

declare(strict_types=1);

namespace MailWatch\Lists\Domain;

/**
 * The recipients one logged-in MailWatch account may administer.
 *
 * The local account role is the authority. Directory and IMAP providers only
 * verify credentials; they do not widen this scope.
 */
final readonly class ListAccess
{
    /**
     * @param list<string> $visibleAddresses
     * @param list<string> $selectableAddresses
     * @param list<string> $domains
     */
    private function __construct(
        private string $role,
        private array $visibleAddresses,
        private array $selectableAddresses,
        private array $domains,
    ) {
    }

    /**
     * @param list<string> $activeFilters
     */
    public static function forAccount(string $username, string $role, array $activeFilters): self
    {
        $filters = self::normalize($activeFilters);
        $normalizedUsername = strtolower(trim($username));

        if ('A' === $role) {
            return new self($role, [], [], []);
        }

        if ('U' === $role) {
            $visibleAddresses = self::normalize([...$filters, $normalizedUsername]);
            $selectableAddresses = array_values(array_filter(
                $visibleAddresses,
                static fn(string $address): bool => false !== filter_var($address, FILTER_VALIDATE_EMAIL),
            ));

            return new self($role, $visibleAddresses, $selectableAddresses, []);
        }

        if ('D' === $role) {
            return new self($role, [], [], self::normalize([...$filters, self::domainOf($normalizedUsername)]));
        }

        return new self($role, [], [], []);
    }

    public function role(): string
    {
        return $this->role;
    }

    /** @return list<string> */
    public function visibleAddresses(): array
    {
        return $this->visibleAddresses;
    }

    /** @return list<string> */
    public function selectableAddresses(): array
    {
        return $this->selectableAddresses;
    }

    /** @return list<string> */
    public function domains(): array
    {
        return $this->domains;
    }

    public function canView(ListEntry $entry): bool
    {
        return match ($this->role) {
            'A' => true,
            'U' => in_array(strtolower($entry->toAddress), $this->visibleAddresses, true),
            'D' => in_array(strtolower($entry->toDomain), $this->domains, true),
            default => false,
        };
    }

    public function canAdd(string $toAddress, string $toDomain): bool
    {
        return match ($this->role) {
            'A' => true,
            'U' => in_array(strtolower($toAddress), $this->selectableAddresses, true),
            // MailScanner matches on to_address, so the address itself must be in a managed domain
            'D' => in_array(strtolower($toDomain), $this->domains, true)
                && in_array(self::domainOf(strtolower($toAddress)), $this->domains, true),
            default => false,
        };
    }

    private static function domainOf(string $address): string
    {
        $separator = strrpos($address, '@');

        return false === $separator ? $address : substr($address, $separator + 1);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function normalize(array $values): array
    {
        $normalized = array_values(array_unique(array_filter(
            array_map(static fn(string $value): string => strtolower(trim($value)), $values),
            static fn(string $value): bool => '' !== $value,
        )));
        sort($normalized);

        return $normalized;
    }
}
