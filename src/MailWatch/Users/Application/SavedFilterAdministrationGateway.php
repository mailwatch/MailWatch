<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Users\Domain\LocalAccount;
use MailWatch\Users\Domain\SavedFilter;

interface SavedFilterAdministrationGateway
{
    public function accountById(int $id): ?LocalAccount;

    /** @return list<SavedFilter> */
    public function filtersFor(string $username): array;

    public function add(string $username, SavedFilter $filter): void;

    public function delete(string $username, string $filter): bool;

    public function toggle(string $username, string $filter): bool;
}
