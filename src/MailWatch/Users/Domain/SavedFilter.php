<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

final readonly class SavedFilter
{
    public function __construct(
        public string $value,
        public bool $active,
    ) {
    }
}
