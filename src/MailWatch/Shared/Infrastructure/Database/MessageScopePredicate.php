<?php

declare(strict_types=1);

namespace MailWatch\Shared\Infrastructure\Database;

use MailWatch\Shared\Domain\MessageScope;

/**
 * A MessageScope as a bound WHERE fragment over maillog.
 *
 * Every query that reads maillog on behalf of a signed-in account needs the
 * same predicate. It lives here, once, so that the quarantine and the message
 * listing cannot drift apart on who may see which row - which is the kind of
 * divergence that turns into an authorisation hole rather than a display bug.
 */
final readonly class MessageScopePredicate
{
    /**
     * @param string $prefix distinguishes the placeholders of this predicate
     *                       from the other parameters of the query it joins
     *
     * @return array{string, array<string, string>}
     */
    public static function build(MessageScope $scope, string $prefix = 'scope'): array
    {
        if ($scope->isUnrestricted()) {
            return ['1 = 1', []];
        }

        $clauses = [];
        $parameters = [];
        $index = 0;

        foreach ($scope->addresses() as $address) {
            // The recipient column holds a comma-separated list, so the address
            // can sit alone, first, last or in the middle. The patterns are the
            // ones the login used to write, including their treatment of % and
            // _ inside an address as wildcards rather than as characters.
            $exact = $prefix . '_to_' . $index;
            $first = $prefix . '_tf_' . $index;
            $last = $prefix . '_tl_' . $index;
            $middle = $prefix . '_tm_' . $index;

            $clauses[] = \sprintf(
                'LOWER(to_address) = :%s OR LOWER(to_address) LIKE :%s'
                    . ' OR LOWER(to_address) LIKE :%s OR LOWER(to_address) LIKE :%s',
                $exact,
                $first,
                $last,
                $middle,
            );
            $parameters[$exact] = $address;
            $parameters[$first] = $address . ',%';
            $parameters[$last] = '%,' . $address;
            $parameters[$middle] = '%,' . $address . ',%';

            if ($scope->includesSender()) {
                $from = $prefix . '_from_' . $index;
                $clauses[] = 'LOWER(from_address) = :' . $from;
                $parameters[$from] = $address;
            }

            ++$index;
        }

        $index = 0;
        foreach ($scope->domains() as $domain) {
            $to = $prefix . '_td_' . $index;
            $clauses[] = 'LOWER(to_domain) = :' . $to;
            $parameters[$to] = $domain;

            if ($scope->includesSender()) {
                $from = $prefix . '_fd_' . $index;
                $clauses[] = 'LOWER(from_domain) = :' . $from;
                $parameters[$from] = $domain;
            }

            ++$index;
        }

        if ([] === $clauses) {
            return ['1 = 0', []];
        }

        return ['(' . implode(' OR ', $clauses) . ')', $parameters];
    }
}
