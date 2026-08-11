<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Domain;

/**
 * The maillog rows one signed-in account may reach.
 *
 * This replaces the SQL fragment the login built into the session and every
 * quarantine query pasted into its WHERE clause. The scope carries the
 * addresses and domains themselves, so the gateway can bind them as parameters
 * instead of concatenating a filter it has to trust.
 *
 * The rules are the ones the login expressed: an administrator sees
 * everything; a user sees the addresses they own; a domain administrator sees
 * the domains they administer, the addresses among their filters, and the
 * domain of their own account. Recipient columns always count, sender columns
 * only when FILTER_TO_ONLY is off.
 */
final readonly class MessageScope
{
    /**
     * @param list<string> $addresses
     * @param list<string> $domains
     */
    private function __construct(
        private bool $unrestricted,
        private array $addresses,
        private array $domains,
        private bool $recipientOnly,
    ) {
    }

    /**
     * No filter at all: what the unauthenticated callers — the auto-release
     * link and the XML-RPC server — have always used, each guarded by its own
     * token or address allow-list rather than by a session.
     */
    public static function unrestricted(): self
    {
        return new self(true, [], [], false);
    }

    /**
     * A scope that matches nothing.
     *
     * Reached when the role is unknown or the account owns no address. The
     * legacy filter produced an empty string there, which made the surrounding
     * SQL a syntax error; refusing every row is the same denial without the
     * crash.
     */
    public static function denied(): self
    {
        return new self(false, [], [], true);
    }

    /**
     * @param list<string> $filters active filters, plus anything else the login
     *                              added to the session list
     */
    public static function forAccount(
        string $username,
        string $role,
        array $filters,
        bool $recipientOnly,
    ): self {
        $account = strtolower(trim($username));
        $entries = self::normalize([$account, ...$filters]);

        if ('A' === $role) {
            return self::unrestricted();
        }

        if ('U' === $role) {
            return [] === $entries
                ? self::denied()
                : new self(false, $entries, [], $recipientOnly);
        }

        if ('D' === $role) {
            $addresses = [];
            $domains = [];
            foreach ($entries as $entry) {
                if (str_contains($entry, '@')) {
                    $addresses[] = $entry;
                } else {
                    $domains[] = $entry;
                }
            }
            if (str_contains($account, '@')) {
                $domains[] = substr($account, (int)strrpos($account, '@') + 1);
            }
            $domains = self::normalize($domains);

            return [] === $addresses && [] === $domains
                ? self::denied()
                : new self(false, $addresses, $domains, $recipientOnly);
        }

        return self::denied();
    }

    public function isUnrestricted(): bool
    {
        return $this->unrestricted;
    }

    /** @return list<string> */
    public function addresses(): array
    {
        return $this->addresses;
    }

    /** @return list<string> */
    public function domains(): array
    {
        return $this->domains;
    }

    /**
     * Whether a message the account sent is in scope as well as one it
     * received. False when FILTER_TO_ONLY restricts the match to recipients.
     */
    public function includesSender(): bool
    {
        return !$this->recipientOnly;
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
