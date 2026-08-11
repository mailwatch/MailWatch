<?php

declare(strict_types=1);

namespace MailWatch\Lists\Application;

use MailWatch\Lists\Domain\ListAccess;
use MailWatch\Lists\Domain\ListEntry;

final readonly class ListOverview
{
    /**
     * @param list<ListEntry> $allowlist
     * @param list<ListEntry> $blocklist
     */
    public function __construct(
        public ListAccess $access,
        public array $allowlist,
        public array $blocklist,
    ) {
    }
}
