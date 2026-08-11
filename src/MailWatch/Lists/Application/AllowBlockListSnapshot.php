<?php

declare(strict_types=1);

namespace MailWatch\Lists\Application;

final readonly class AllowBlockListSnapshot
{
    /**
     * @param list<array{to_address: string, from_address: string}> $allowlist
     * @param list<array{to_address: string, from_address: string}> $blocklist
     */
    public function __construct(
        private array $allowlist,
        private array $blocklist,
    ) {
    }

    /**
     * @return array{
     *     allowlist: list<array{to_address: string, from_address: string}>,
     *     blocklist: list<array{to_address: string, from_address: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'allowlist' => $this->allowlist,
            'blocklist' => $this->blocklist,
        ];
    }
}
