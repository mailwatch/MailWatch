<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Domain;

/**
 * One stored part of a quarantined message.
 *
 * A message is held either as a single file under `spam`, `nonspam` or `mcp`,
 * or as a directory of parts. Both arrive here the same way: a name, where it
 * is, and what it contains.
 */
final readonly class QuarantineItem
{
    public function __construct(
        public string $file,
        public string $path,
        public string $type,
    ) {
    }
}
