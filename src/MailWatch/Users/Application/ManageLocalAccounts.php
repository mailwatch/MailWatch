<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Shared\Application\Port\PasswordHasher;
use MailWatch\Users\Domain\AccountAdministrationAccess;
use MailWatch\Users\Domain\AccountProfile;
use MailWatch\Users\Domain\ManagedAccount;

final readonly class ManageLocalAccounts
{
    public function __construct(
        private AccountAdministrationGateway $gateway,
        private PasswordHasher $passwordHasher,
    ) {
    }

    public function account(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        bool $superDomainAdministrators,
        int $id,
    ): ManagedAccount {
        $target = $this->target($id);
        $access = $this->access($actorUsername, $actorRole, $actorDomain, $superDomainAdministrators);
        $this->assertDomainAccess($access, $target->username, 'edit');
        if (!$access->canEditTarget($target)) {
            throw new AccountAccessDenied();
        }

        return $target;
    }

    public function create(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        bool $superDomainAdministrators,
        AccountProfile $profile,
        string $password,
    ): void {
        $access = $this->access($actorUsername, $actorRole, $actorDomain, $superDomainAdministrators);
        $this->assertDomainAccess($access, $profile->username, 'create');
        $this->assertRoleAssignment($access, $profile);
        $this->assertUniqueUsername($profile->username);

        $this->gateway->create($profile, $this->passwordHasher->hash($password));
    }

    public function update(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        bool $superDomainAdministrators,
        int $id,
        AccountProfile $profile,
        ?string $password,
    ): ManagedAccount {
        $target = $this->target($id);
        $access = $this->access($actorUsername, $actorRole, $actorDomain, $superDomainAdministrators);
        $this->assertDomainAccess($access, $target->username, 'edit');
        if (!$access->canEditTarget($target)) {
            throw new AccountAccessDenied();
        }
        $this->assertDomainAccess($access, $profile->username, 'to');
        $this->assertRoleAssignment($access, $profile);
        $this->assertUniqueUsername($profile->username, $target->id);

        $password = null === $password || '' === $password ? null : $password;
        $this->gateway->update(
            $target,
            $profile,
            null === $password ? null : $this->passwordHasher->hash($password),
        );

        return $target;
    }

    public function delete(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        bool $superDomainAdministrators,
        int $id,
    ): ManagedAccount {
        $target = $this->target($id);
        $access = $this->access($actorUsername, $actorRole, $actorDomain, $superDomainAdministrators);
        $this->assertDomainAccess($access, $target->username, 'delete');
        if ($access->sameAccount($target->username)) {
            throw new OwnAccountDeletionDenied();
        }
        if (!$access->canDelete($target)) {
            throw new AccountAccessDenied();
        }

        $this->gateway->delete($target);

        return $target;
    }

    private function target(int $id): ManagedAccount
    {
        $target = $this->gateway->accountById($id);
        if (null === $target) {
            throw new UnknownLocalAccount();
        }

        return $target;
    }

    private function access(
        string $actorUsername,
        string $actorRole,
        string $actorDomain,
        bool $superDomainAdministrators,
    ): AccountAdministrationAccess {
        return new AccountAdministrationAccess(
            $actorUsername,
            $actorRole,
            $actorDomain,
            $this->gateway->delegatedDomainsFor($actorUsername),
            $superDomainAdministrators,
        );
    }

    private function assertDomainAccess(
        AccountAdministrationAccess $access,
        string $username,
        string $operation,
    ): void {
        if (!$access->canReach($username)) {
            throw new AccountDomainAccessDenied($operation, $access->domainOf($username));
        }
    }

    private function assertRoleAssignment(AccountAdministrationAccess $access, AccountProfile $profile): void
    {
        if (!$access->canAssign($profile->username, $profile->role)) {
            if ('A' === $profile->role) {
                throw new AccountRoleAssignmentDenied();
            }

            throw new AccountAccessDenied();
        }
    }

    private function assertUniqueUsername(string $username, ?int $exceptId = null): void
    {
        if ($this->gateway->usernameExists($username, $exceptId)) {
            throw new DuplicateLocalAccount($username);
        }
    }
}
