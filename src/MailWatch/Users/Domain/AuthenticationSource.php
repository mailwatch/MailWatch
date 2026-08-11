<?php

declare(strict_types=1);

namespace MailWatch\Users\Domain;

enum AuthenticationSource
{
    case Database;
    case Ldap;
    case Imap;

    public static function fromSession(bool $ldap, bool $imap): self
    {
        return match (true) {
            $ldap => self::Ldap,
            $imap => self::Imap,
            default => self::Database,
        };
    }

    public function canChangePassword(): bool
    {
        return self::Database === $this;
    }
}
