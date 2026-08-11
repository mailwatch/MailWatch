<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Application;

use MailWatch\Users\Application\ManageSavedFilters;
use MailWatch\Users\Application\SavedFilterAccessDenied;
use MailWatch\Users\Application\SavedFilterAdministrationGateway;
use MailWatch\Users\Application\UnknownLocalAccount;
use MailWatch\Users\Domain\LocalAccount;
use MailWatch\Users\Domain\SavedFilter;
use PHPUnit\Framework\TestCase;

final class ManageSavedFiltersTest extends TestCase
{
    public function testItUsesAllActorFiltersAsDelegatedDomains(): void
    {
        $gateway = new RecordingSavedFilterGateway();
        $gateway->accounts[2] = new LocalAccount(2, 'user@delegated.test', 'U');
        $gateway->filters['manager@example.test'] = [new SavedFilter('delegated.test', false)];
        $gateway->filters['user@delegated.test'] = [new SavedFilter('alias@delegated.test', true)];

        $overview = (new ManageSavedFilters($gateway))->overview(
            'manager@example.test',
            'D',
            'example.test',
            2,
            false,
        );

        self::assertTrue($overview->canMutate);
        self::assertSame('alias@delegated.test', $overview->filters[0]->value);
    }

    public function testItRejectsChangesToADomainAdministratorsOwnFilters(): void
    {
        $gateway = new RecordingSavedFilterGateway();
        $gateway->accounts[1] = new LocalAccount(1, 'manager@example.test', 'D');
        $manager = new ManageSavedFilters($gateway);

        $overview = $manager->overview('manager@example.test', 'D', 'example.test', 1, false);
        self::assertFalse($overview->canMutate);

        $this->expectException(SavedFilterAccessDenied::class);
        $manager->add(
            'manager@example.test',
            'D',
            'example.test',
            1,
            false,
            new SavedFilter('other.test', true),
        );
    }

    public function testItDispatchesAuthorizedMutationsToPersistence(): void
    {
        $gateway = new RecordingSavedFilterGateway();
        $gateway->accounts[2] = new LocalAccount(2, 'user@example.test', 'U');
        $manager = new ManageSavedFilters($gateway);

        $manager->add('root', 'A', '', 2, false, new SavedFilter('alias@example.test', true));
        self::assertTrue($manager->toggle('root', 'A', '', 2, false, 'alias@example.test'));
        self::assertTrue($manager->delete('root', 'A', '', 2, false, 'alias@example.test'));
        self::assertSame([], $gateway->filters['user@example.test']);
    }

    public function testItReportsAnUnknownTarget(): void
    {
        $this->expectException(UnknownLocalAccount::class);

        (new ManageSavedFilters(new RecordingSavedFilterGateway()))->overview('root', 'A', '', 99, false);
    }
}

final class RecordingSavedFilterGateway implements SavedFilterAdministrationGateway
{
    /** @var array<int, LocalAccount> */
    public array $accounts = [];

    /** @var array<string, list<SavedFilter>> */
    public array $filters = [];

    public function accountById(int $id): ?LocalAccount
    {
        return $this->accounts[$id] ?? null;
    }

    public function filtersFor(string $username): array
    {
        return $this->filters[$username] ?? [];
    }

    public function add(string $username, SavedFilter $filter): void
    {
        $this->filters[$username][] = $filter;
    }

    public function delete(string $username, string $filter): bool
    {
        $before = count($this->filters[$username] ?? []);
        $this->filters[$username] = array_values(array_filter(
            $this->filters[$username] ?? [],
            static fn(SavedFilter $savedFilter): bool => $filter !== $savedFilter->value,
        ));

        return $before !== count($this->filters[$username]);
    }

    public function toggle(string $username, string $filter): bool
    {
        foreach ($this->filters[$username] ?? [] as $offset => $savedFilter) {
            if ($filter !== $savedFilter->value) {
                continue;
            }

            $this->filters[$username][$offset] = new SavedFilter($filter, !$savedFilter->active);

            return true;
        }

        return false;
    }
}
