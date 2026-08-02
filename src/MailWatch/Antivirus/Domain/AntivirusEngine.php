<?php

declare(strict_types=1);

namespace MailWatch\Antivirus\Domain;

/**
 * One scanning engine inside an antivirus product, as its version output
 * reports it.
 */
final readonly class AntivirusEngine
{
    public function __construct(
        public string $name,
        public string $version,
        public ?string $date
    ) {
    }
}
