<?php

declare(strict_types=1);

namespace MailWatch\Lists\Domain;

final readonly class ListEntry
{
    public function __construct(
        public int $id,
        public string $fromAddress,
        public string $toAddress,
        public string $toDomain,
    ) {
    }
}
