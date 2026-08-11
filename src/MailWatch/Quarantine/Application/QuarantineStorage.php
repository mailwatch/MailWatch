<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Application;

use MailWatch\Quarantine\Domain\QuarantineItem;

/**
 * Where MailScanner puts the messages it holds back.
 *
 * The quarantine is a directory tree the application only reads and prunes;
 * MailScanner itself writes it. Everything the operations need from it is
 * here, so that they can be reasoned about without a filesystem.
 */
interface QuarantineStorage
{
    /**
     * The folders of the quarantine, one per processing day.
     *
     * @return list<string>
     */
    public function folders(): array;

    /**
     * Everything stored under one folder, its `spam`, `nonspam` and `mcp`
     * subfolders included — which is why the subfolder names appear in the
     * result alongside the message identifiers.
     *
     * @return list<string>
     */
    public function entries(string $folder): array;

    /**
     * The stored parts of one message, in the order the operations index them:
     * the single-file forms first, then the contents of its own directory.
     *
     * @return list<QuarantineItem>
     */
    public function items(string $folder, string $messageId): array;

    public function delete(string $path): bool;
}
