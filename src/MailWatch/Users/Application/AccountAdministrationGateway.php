<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Users\Domain\AccountProfile;
use MailWatch\Users\Domain\ManagedAccount;

interface AccountAdministrationGateway
{
    public function accountById(int $id): ?ManagedAccount;

    /** @return list<string> */
    public function delegatedDomainsFor(string $username): array;

    public function usernameExists(string $username, ?int $exceptId = null): bool;

    public function create(AccountProfile $profile, string $passwordHash): void;

    public function update(
        ManagedAccount $target,
        AccountProfile $profile,
        ?string $passwordHash,
    ): void;

    public function delete(ManagedAccount $target): void;
}
