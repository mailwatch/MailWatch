<?php

declare(strict_types=1);

namespace MailWatch\Lists\Application;

final readonly class GetAllowBlockListSnapshot
{
    public function __construct(private AllowBlockListGateway $gateway)
    {
    }

    public function get(): AllowBlockListSnapshot
    {
        $lists = $this->gateway->entries();

        return new AllowBlockListSnapshot(
            $this->normalize($lists['allowlist']),
            $this->normalize($lists['blocklist']),
        );
    }

    /**
     * @param list<array{to_address: string, from_address: string}> $entries
     *
     * @return list<array{to_address: string, from_address: string}>
     */
    private function normalize(array $entries): array
    {
        $entries = array_map(
            static fn(array $entry): array => [
                'to_address' => strtolower($entry['to_address']),
                'from_address' => strtolower($entry['from_address']),
            ],
            $entries,
        );
        usort(
            $entries,
            static fn(array $left, array $right): int => [$left['to_address'], $left['from_address']]
                <=> [$right['to_address'], $right['from_address']],
        );

        return $entries;
    }
}
