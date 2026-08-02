<?php

declare(strict_types=1);

namespace MailWatch\Antivirus\Http;

use MailWatch\Antivirus\Domain\AntivirusScanner;
use MailWatch\Shared\Http\Response;

/**
 * A status page for one antivirus product.
 *
 * Most products are formatted by an awk script and share one implementation;
 * F-Secure 12 parses its own output and has another. The route does not need
 * to know which.
 */
interface StatusController
{
    public function handle(AntivirusScanner $scanner): Response;
}
