<?php

declare(strict_types=1);

namespace MailWatch\Antivirus\Domain;

/**
 * One antivirus product as the status page needs it: what to call the page and
 * which command produces the report.
 */
final readonly class AntivirusScanner
{
    /**
     * @param string      $id            the identifier used in the URL, for example "clamav"
     * @param string      $titleKey      translation key for the page title
     * @param string|null $reportCommand null when the scanner is not installed on this host
     */
    public function __construct(
        public string $id,
        public string $titleKey,
        public ?string $reportCommand
    ) {
    }
}
