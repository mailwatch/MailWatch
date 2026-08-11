<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Users\Domain\LocalAccount;
use MailWatch\Users\Domain\SavedFilter;
use MailWatch\Users\Domain\SavedFilterAccess;

final readonly class ManageSavedFilters
{
    public function __construct(private SavedFilterAdministrationGateway $gateway)
    {
    }

    public function overview(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        int $targetId,
        bool $superDomainAdministrators,
    ): SavedFilterOverview {
        [$target, $access] = $this->targetAndAccess(
            $actorUsername,
            $actorRole,
            $actorDomain,
            $targetId,
            $superDomainAdministrators,
        );

        if (!$access->canView($target)) {
            throw new SavedFilterAccessDenied();
        }

        return new SavedFilterOverview(
            $target,
            $this->gateway->filtersFor($target->username),
            $access->canMutate($target),
        );
    }

    public function add(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        int $targetId,
        bool $superDomainAdministrators,
        SavedFilter $filter,
    ): void {
        $target = $this->mutableTarget(
            $actorUsername,
            $actorRole,
            $actorDomain,
            $targetId,
            $superDomainAdministrators,
        );
        $this->gateway->add($target->username, $filter);
    }

    public function delete(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        int $targetId,
        bool $superDomainAdministrators,
        string $filter,
    ): bool {
        $target = $this->mutableTarget(
            $actorUsername,
            $actorRole,
            $actorDomain,
            $targetId,
            $superDomainAdministrators,
        );

        return $this->gateway->delete($target->username, $filter);
    }

    public function toggle(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        int $targetId,
        bool $superDomainAdministrators,
        string $filter,
    ): bool {
        $target = $this->mutableTarget(
            $actorUsername,
            $actorRole,
            $actorDomain,
            $targetId,
            $superDomainAdministrators,
        );

        return $this->gateway->toggle($target->username, $filter);
    }

    private function mutableTarget(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        int $targetId,
        bool $superDomainAdministrators,
    ): LocalAccount {
        [$target, $access] = $this->targetAndAccess(
            $actorUsername,
            $actorRole,
            $actorDomain,
            $targetId,
            $superDomainAdministrators,
        );

        if (!$access->canMutate($target)) {
            throw new SavedFilterAccessDenied();
        }

        return $target;
    }

    /** @return array{LocalAccount, SavedFilterAccess} */
    private function targetAndAccess(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        int $targetId,
        bool $superDomainAdministrators,
    ): array {
        $target = $this->gateway->accountById($targetId);
        if (null === $target) {
            throw new UnknownLocalAccount();
        }

        $delegatedDomains = 'D' === $actorRole
            ? array_map(
                static fn(SavedFilter $filter): string => $filter->value,
                $this->gateway->filtersFor($actorUsername),
            )
            : [];

        return [
            $target,
            new SavedFilterAccess(
                $actorUsername,
                $actorRole,
                $actorDomain,
                $delegatedDomains,
                $superDomainAdministrators,
            ),
        ];
    }
}
