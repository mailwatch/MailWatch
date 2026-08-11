<?php

declare(strict_types=1);

namespace MailWatch\Lists\Application;

interface AllowBlockListGateway
{
    /**
     * @return array{
     *     allowlist: list<array{to_address: string, from_address: string}>,
     *     blocklist: list<array{to_address: string, from_address: string}>
     * }
     */
    public function entries(): array;
}
