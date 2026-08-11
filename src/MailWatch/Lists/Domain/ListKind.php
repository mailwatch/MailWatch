<?php

declare(strict_types=1);

namespace MailWatch\Lists\Domain;

enum ListKind: string
{
    case Allowlist = 'allowlist';
    case Blocklist = 'blocklist';

    public static function fromLegacyCode(string $code): ?self
    {
        return match ($code) {
            'w' => self::Allowlist,
            'b' => self::Blocklist,
            default => null,
        };
    }

    public function legacyCode(): string
    {
        return match ($this) {
            self::Allowlist => 'w',
            self::Blocklist => 'b',
        };
    }
}
