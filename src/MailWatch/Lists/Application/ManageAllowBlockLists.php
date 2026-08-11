<?php

declare(strict_types=1);

namespace MailWatch\Lists\Application;

use MailWatch\Lists\Domain\ListAccess;
use MailWatch\Lists\Domain\ListEntry;
use MailWatch\Lists\Domain\ListKind;

final readonly class ManageAllowBlockLists
{
    public function __construct(private ListAdministrationGateway $gateway)
    {
    }

    public function overview(string $username, string $role): ListOverview
    {
        $access = $this->access($username, $role);

        return new ListOverview(
            $access,
            $this->gateway->entries(ListKind::Allowlist, $access),
            $this->gateway->entries(ListKind::Blocklist, $access),
        );
    }

    public function add(
        string $username,
        string $role,
        ListKind $kind,
        string $fromAddress,
        string $toAddress,
        string $toDomain,
    ): bool {
        $access = $this->access($username, $role);
        if (!$access->canAdd($toAddress, $toDomain)) {
            return false;
        }

        $this->gateway->replace(
            $kind,
            strtolower($fromAddress),
            strtolower($toAddress),
            strtolower($toDomain),
        );

        return true;
    }

    public function delete(string $username, string $role, ListKind $kind, int $id): ?ListEntry
    {
        return $this->gateway->delete($kind, $id, $this->access($username, $role));
    }

    private function access(string $username, string $role): ListAccess
    {
        $filters = in_array($role, ['U', 'D'], true)
            ? $this->gateway->activeFilters($username)
            : [];

        return ListAccess::forAccount($username, $role, $filters);
    }
}
