<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain;

use MailWatch\Users\Domain\AuthenticationSource;
use PHPUnit\Framework\TestCase;

final class AuthenticationSourceTest extends TestCase
{
    public function testItPreservesTheLoginSourcePrecedence(): void
    {
        self::assertSame(AuthenticationSource::Database, AuthenticationSource::fromSession(false, false));
        self::assertSame(AuthenticationSource::Imap, AuthenticationSource::fromSession(false, true));
        self::assertSame(AuthenticationSource::Ldap, AuthenticationSource::fromSession(true, false));
        self::assertSame(AuthenticationSource::Ldap, AuthenticationSource::fromSession(true, true));
    }

    public function testOnlyDatabaseAuthenticationCanChangeThePassword(): void
    {
        self::assertTrue(AuthenticationSource::Database->canChangePassword());
        self::assertFalse(AuthenticationSource::Ldap->canChangePassword());
        self::assertFalse(AuthenticationSource::Imap->canChangePassword());
    }
}
