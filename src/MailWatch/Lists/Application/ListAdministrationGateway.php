<?php

declare(strict_types=1);

namespace MailWatch\Lists\Application;

use MailWatch\Lists\Domain\ListAccess;
use MailWatch\Lists\Domain\ListEntry;
use MailWatch\Lists\Domain\ListKind;

interface ListAdministrationGateway
{
    /** @return list<string> */
    public function activeFilters(string $username): array;

    /** @return list<ListEntry> */
    public function entries(ListKind $kind, ListAccess $access): array;

    public function replace(ListKind $kind, string $fromAddress, string $toAddress, string $toDomain): void;

    public function delete(ListKind $kind, int $id, ListAccess $access): ?ListEntry;
}
