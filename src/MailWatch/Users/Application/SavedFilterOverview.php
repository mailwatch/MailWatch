<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Users\Domain\LocalAccount;
use MailWatch\Users\Domain\SavedFilter;

final readonly class SavedFilterOverview
{
    /** @param list<SavedFilter> $filters */
    public function __construct(
        public LocalAccount $account,
        public array $filters,
        public bool $canMutate,
    ) {
    }
}
