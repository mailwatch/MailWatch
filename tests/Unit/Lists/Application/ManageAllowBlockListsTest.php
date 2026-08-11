<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lists\Application;

use MailWatch\Lists\Application\ListAdministrationGateway;
use MailWatch\Lists\Application\ManageAllowBlockLists;
use MailWatch\Lists\Domain\ListAccess;
use MailWatch\Lists\Domain\ListEntry;
use MailWatch\Lists\Domain\ListKind;
use PHPUnit\Framework\TestCase;

final class ManageAllowBlockListsTest extends TestCase
{
    public function testItReturnsOnlyEntriesInsideTheLocalAccountsScope(): void
    {
        $gateway = new RecordingListAdministrationGateway();
        $gateway->filters = ['alias@example.test'];
        $gateway->allowlist = [
            new ListEntry(1, 'first@example.test', 'owner@example.test', 'example.test'),
            new ListEntry(2, 'second@example.test', 'alias@example.test', 'example.test'),
            new ListEntry(3, 'third@example.test', 'other@example.test', 'example.test'),
        ];

        $overview = (new ManageAllowBlockLists($gateway))->overview('owner@example.test', 'U');

        self::assertSame([1, 2], array_map(static fn(ListEntry $entry): int => $entry->id, $overview->allowlist));
        self::assertSame([], $overview->blocklist);
    }

    public function testItRejectsAnOutOfScopeInsertBeforeCallingPersistence(): void
    {
        $gateway = new RecordingListAdministrationGateway();
        $manager = new ManageAllowBlockLists($gateway);

        self::assertFalse($manager->add(
            'owner@example.test',
            'U',
            ListKind::Allowlist,
            'sender@example.test',
            'other@example.test',
            'example.test',
        ));
        self::assertSame([], $gateway->replacements);

        self::assertTrue($manager->add(
            'owner@example.test',
            'U',
            ListKind::Allowlist,
            'Sender@Example.test',
            'OWNER@Example.test',
            'Example.test',
        ));
        self::assertCount(1, $gateway->replacements);
        self::assertSame([
            ListKind::Allowlist,
            'sender@example.test',
            'owner@example.test',
            'example.test',
        ], $gateway->replacements[0]);
    }

    public function testDeleteUsesTheSameScopeAsTheOverview(): void
    {
        $gateway = new RecordingListAdministrationGateway();
        $gateway->allowlist = [
            new ListEntry(1, 'first@example.test', 'owner@example.test', 'example.test'),
            new ListEntry(2, 'second@example.test', 'other@example.test', 'other.test'),
        ];
        $manager = new ManageAllowBlockLists($gateway);

        self::assertNull($manager->delete('owner@example.test', 'U', ListKind::Allowlist, 2));
        self::assertNotNull($manager->delete('owner@example.test', 'U', ListKind::Allowlist, 1));
        self::assertSame([2], array_map(static fn(ListEntry $entry): int => $entry->id, $gateway->allowlist));
    }
}

final class RecordingListAdministrationGateway implements ListAdministrationGateway
{
    /** @var list<string> */
    public array $filters = [];

    /** @var list<ListEntry> */
    public array $allowlist = [];

    /** @var list<ListEntry> */
    public array $blocklist = [];

    /** @var list<array{ListKind, string, string, string}> */
    public array $replacements = [];

    public function activeFilters(string $username): array
    {
        return $this->filters;
    }

    public function entries(ListKind $kind, ListAccess $access): array
    {
        return array_values(array_filter(
            ListKind::Allowlist === $kind ? $this->allowlist : $this->blocklist,
            static fn(ListEntry $entry): bool => $access->canView($entry),
        ));
    }

    public function replace(ListKind $kind, string $fromAddress, string $toAddress, string $toDomain): void
    {
        $this->replacements[] = [$kind, $fromAddress, $toAddress, $toDomain];
    }

    public function delete(ListKind $kind, int $id, ListAccess $access): ?ListEntry
    {
        $entries = ListKind::Allowlist === $kind ? $this->allowlist : $this->blocklist;
        foreach ($entries as $offset => $entry) {
            if ($entry->id !== $id || !$access->canView($entry)) {
                continue;
            }

            array_splice($entries, $offset, 1);
            if (ListKind::Allowlist === $kind) {
                $this->allowlist = $entries;
            } else {
                $this->blocklist = $entries;
            }

            return $entry;
        }

        return null;
    }
}
