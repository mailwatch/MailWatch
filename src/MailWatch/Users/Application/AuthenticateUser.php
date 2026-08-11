<?php

declare(strict_types=1);

namespace MailWatch\Users\Application;

use MailWatch\Shared\Application\Port\PasswordHasher;
use MailWatch\Shared\Application\Port\PasswordVerifier;
use MailWatch\Users\Domain\AuthenticatedUser;
use MailWatch\Users\Domain\AuthenticationSource;
use MailWatch\Users\Domain\ExternalIdentity;
use MailWatch\Users\Domain\LoginAccount;

final readonly class AuthenticateUser
{
    /**
     * @param list<ExternalCredentialVerifier> $externalProviders
     */
    public function __construct(
        private LoginAccountGateway $gateway,
        private PasswordHasher $passwordHasher,
        private PasswordVerifier $passwordVerifier,
        private array $externalProviders,
        private ?int $globalSessionTimeout,
    ) {
    }

    public function authenticate(string $username, string $password, int $now): AuthenticatedUser
    {
        foreach ($this->externalProviders as $provider) {
            $identity = $provider->authenticate($username, $password);
            if (null !== $identity) {
                return $this->externalLogin($identity, $now);
            }
        }

        if ('' === $password) {
            throw new EmptyPassword();
        }

        $account = $this->gateway->accountByUsername($username);
        if (null === $account || null === $account->passwordHash) {
            throw new InvalidCredentials();
        }

        $passwordHash = $account->passwordHash;
        $passwordHashUpgraded = false;
        if ($this->passwordVerifier->verify($password, $passwordHash)) {
            if ($this->passwordVerifier->needsRehash($passwordHash)) {
                $this->gateway->updatePasswordHash($account->username, $this->passwordHasher->hash($password));
                $passwordHashUpgraded = true;
            }
        } elseif (hash_equals(md5($password), $passwordHash)) {
            $this->gateway->updatePasswordHash($account->username, $this->passwordHasher->hash($password));
            $passwordHashUpgraded = true;
        } else {
            throw new InvalidCredentials();
        }

        return $this->successfulLogin($account, AuthenticationSource::Database, $now, $passwordHashUpgraded);
    }

    private function externalLogin(ExternalIdentity $identity, int $now): AuthenticatedUser
    {
        $account = $this->gateway->accountByUsername($identity->username);
        if (null === $account && $identity->provisionLocalAccount) {
            $this->gateway->provisionExternalAccount($identity->username, $identity->fullName);
            $account = $this->gateway->accountByUsername($identity->username);
        }
        if (null === $account) {
            throw new InvalidCredentials();
        }

        return $this->successfulLogin($account, $identity->source, $now);
    }

    private function successfulLogin(
        LoginAccount $account,
        AuthenticationSource $source,
        int $now,
        bool $passwordHashUpgraded = false,
    ): AuthenticatedUser {
        $this->gateway->recordSuccessfulLogin(
            $account->username,
            $this->loginExpiry($account->loginTimeout, $now),
            $now,
        );

        return new AuthenticatedUser($account, $source, $passwordHashUpgraded);
    }

    private function loginExpiry(int $accountTimeout, int $now): int
    {
        if (-1 !== $accountTimeout) {
            return 0 === $accountTimeout ? 0 : $now + $accountTimeout;
        }
        if (null === $this->globalSessionTimeout) {
            return $now + 600;
        }

        return 0 < $this->globalSessionTimeout && 99999 >= $this->globalSessionTimeout
            ? $now + $this->globalSessionTimeout
            : 0;
    }
}
