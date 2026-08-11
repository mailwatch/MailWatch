<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine\Infrastructure\Storage;

use App\Tests\Support\RecordingCommandRunner;
use MailWatch\Quarantine\Domain\QuarantineItem;
use MailWatch\Quarantine\Infrastructure\Storage\FilesystemQuarantineStorage;
use PHPUnit\Framework\TestCase;

final class FilesystemQuarantineStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $root = tempnam(sys_get_temp_dir(), 'mailwatch-quarantine');
        self::assertIsString($root);
        unlink($root);
        mkdir($root, 0o700);
        $this->root = $root;
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->root);
    }

    public function testItListsTheQuarantineFolders(): void
    {
        mkdir($this->root . '/20260810');
        mkdir($this->root . '/20260811');

        $folders = $this->storage()->folders();
        sort($folders);

        self::assertSame(['20260810', '20260811'], $folders);
    }

    public function testAQuarantineThatIsNotThereIsAnEmptyOne(): void
    {
        $storage = new FilesystemQuarantineStorage($this->root . '/absent', new RecordingCommandRunner());

        self::assertSame([], $storage->folders());
        self::assertSame([], $storage->entries('20260811'));
        self::assertSame([], $storage->items('20260811', 'message-001'));
    }

    /**
     * The calling page turns this into a SQL list of message identifiers, and
     * has always had the three subfolder names in it as well.
     */
    public function testEntriesCoverTheFolderAndItsCategories(): void
    {
        mkdir($this->root . '/20260811');
        mkdir($this->root . '/20260811/message-001');
        mkdir($this->root . '/20260811/spam');
        touch($this->root . '/20260811/spam/message-002');

        $entries = $this->storage()->entries('20260811');
        sort($entries);

        self::assertSame(['message-001', 'message-002', 'spam'], $entries);
    }

    public function testASingleFileMessageIsFoundInEachCategoryInAFixedOrder(): void
    {
        foreach (['spam', 'nonspam', 'mcp'] as $category) {
            mkdir($this->root . '/20260811/' . $category, 0o700, true);
            touch($this->root . '/20260811/' . $category . '/message-001');
        }

        $items = $this->storage()->items('20260811', 'message-001');

        self::assertCount(3, $items);
        self::assertSame(
            [
                $this->root . '/20260811/nonspam/message-001',
                $this->root . '/20260811/spam/message-001',
                $this->root . '/20260811/mcp/message-001',
            ],
            array_map(static fn(QuarantineItem $item): string => $item->path, $items),
        );
        foreach ($items as $item) {
            self::assertSame('message', $item->file);
            self::assertSame('message/rfc822', $item->type);
        }
    }

    public function testTheStoredPartsOfAMessageCarryTheTypeTheDetectorReports(): void
    {
        mkdir($this->root . '/20260811/message-001', 0o700, true);
        touch($this->root . '/20260811/message-001/attachment.pdf');
        $path = $this->root . '/20260811/message-001/attachment.pdf';

        $commands = new RecordingCommandRunner([
            '/usr/bin/file -bi ' . escapeshellarg($path) => "application/pdf; charset=binary\n",
        ]);

        $items = (new FilesystemQuarantineStorage($this->root, $commands))->items('20260811', 'message-001');

        self::assertCount(1, $items);
        self::assertSame('attachment.pdf', $items[0]->file);
        self::assertSame($path, $items[0]->path);
        self::assertSame('application/pdf; charset=binary', $items[0]->type);
        self::assertSame(['/usr/bin/file -bi ' . escapeshellarg($path)], $commands->commands);
    }

    public function testAMailReportedAsTextIsStillAMessage(): void
    {
        mkdir($this->root . '/20260811/message-001', 0o700, true);
        $path = $this->root . '/20260811/message-001/message';
        touch($path);

        $storage = new FilesystemQuarantineStorage($this->root, new RecordingCommandRunner([
            '/usr/bin/file -bi ' . escapeshellarg($path) => 'text/x-mail; charset=us-ascii',
        ]));

        self::assertSame('message/rfc822', $storage->items('20260811', 'message-001')[0]->type);
    }

    public function testItDeletesAStoredPartAndReportsAMissingOne(): void
    {
        $path = $this->root . '/20260811/nonspam/message-001';
        mkdir(\dirname($path), 0o700, true);
        touch($path);

        $storage = $this->storage();

        self::assertTrue($storage->delete($path));
        self::assertFileDoesNotExist($path);
        self::assertFalse($storage->delete($path));
    }

    private function storage(): FilesystemQuarantineStorage
    {
        return new FilesystemQuarantineStorage($this->root, new RecordingCommandRunner());
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $name) {
            if ('.' === $name || '..' === $name) {
                continue;
            }
            $path = $directory . '/' . $name;
            is_dir($path) ? self::removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
