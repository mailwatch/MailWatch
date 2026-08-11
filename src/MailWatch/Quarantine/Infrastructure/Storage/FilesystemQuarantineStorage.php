<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Infrastructure\Storage;

use MailWatch\Quarantine\Application\QuarantineStorage;
use MailWatch\Quarantine\Domain\QuarantineItem;
use MailWatch\Shared\Application\Port\CommandRunner;

final readonly class FilesystemQuarantineStorage implements QuarantineStorage
{
    /**
     * The single-file forms, in the order the operations have always indexed
     * them. Changing the order would renumber the items a rendered form
     * refers to.
     */
    private const CATEGORIES = ['nonspam', 'spam', 'mcp'];

    public function __construct(
        private string $root,
        private CommandRunner $commands,
    ) {
    }

    public function folders(): array
    {
        return $this->names($this->root);
    }

    public function entries(string $folder): array
    {
        $base = $this->root . '/' . $folder;

        $entries = $this->names($base);
        foreach (self::CATEGORIES as $category) {
            $entries = [...$entries, ...$this->names($base . '/' . $category)];
        }

        return $entries;
    }

    public function items(string $folder, string $messageId): array
    {
        $base = $this->root . '/' . $folder;
        $items = [];

        foreach (self::CATEGORIES as $category) {
            $path = $base . '/' . $category . '/' . $messageId;
            if (file_exists($path) && is_readable($path)) {
                $items[] = new QuarantineItem('message', $path, 'message/rfc822');
            }
        }

        $directory = $base . '/' . $messageId;
        foreach ($this->names($directory) as $name) {
            $path = $directory . '/' . $name;
            $items[] = new QuarantineItem($name, $path, $this->typeOf($path));
        }

        return $items;
    }

    public function delete(string $path): bool
    {
        return @unlink($path);
    }

    /**
     * The names inside a directory, or nothing when it is absent or closed to
     * us. An unreadable quarantine is an empty one as far as the operations
     * are concerned: they report what they could not act on.
     *
     * @return list<string>
     */
    private function names(string $directory): array
    {
        if (!is_dir($directory) || !is_readable($directory)) {
            return [];
        }

        $handle = @opendir($directory);
        if (false === $handle) {
            return [];
        }

        $names = [];
        while (false !== ($name = readdir($handle))) {
            if ('.' !== $name && '..' !== $name) {
                $names[] = $name;
            }
        }
        closedir($handle);

        return $names;
    }

    private function typeOf(string $path): string
    {
        $type = trim($this->commands->run('/usr/bin/file -bi ' . escapeshellarg($path)));

        // In some cases file returns text/x-mail instead of message/rfc822
        return 1 === preg_match('!^text/x-mail!', $type) ? 'message/rfc822' : $type;
    }
}
