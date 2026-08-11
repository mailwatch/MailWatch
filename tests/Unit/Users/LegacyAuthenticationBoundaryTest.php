<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users;

use PHPUnit\Framework\TestCase;

final class LegacyAuthenticationBoundaryTest extends TestCase
{
    public function testCheckLoginIsOnlyAnAdapterOverTheAuthenticationUseCase(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/mailscanner/checklogin.php');
        self::assertIsString($source);

        self::assertStringContainsString('userAuthentication()->authenticate(', $source);
        self::assertStringContainsString('AuthenticationSource::Ldap', $source);
        self::assertStringContainsString('AuthenticationSource::Imap', $source);
        self::assertStringNotContainsString('dbquery' . '(', $source);
        self::assertStringNotContainsString('Database' . '::', $source);
        self::assertStringNotContainsString('password_' . 'verify', $source);
        self::assertStringNotContainsString('password_' . 'hash', $source);
        self::assertStringNotContainsString('ldap_' . 'authenticate(', $source);
        self::assertStringNotContainsString('imap_' . 'authenticate(', $source);
        self::assertStringNotContainsString('updateLoginExpiry', $source);
    }

    public function testExternalProviderFunctionsReturnResultsWithoutPersistenceOrSessionEffects(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/mailscanner/functions.php');
        self::assertIsString($source);

        $ldap = self::functionSource($source, 'function ldap_authenticate(', 'function ldap_print_error(');
        $imap = self::functionSource($source, 'function imap_authenticate(', 'function translate_etoi(');

        foreach ([$ldap, $imap] as $provider) {
            self::assertStringContainsString('ExternalIdentity', $provider);
            self::assertStringNotContainsString('dbquery' . '(', $provider);
            self::assertStringNotContainsString('quote_smart' . '(', $provider);
            self::assertStringNotContainsString('$_SESSION', $provider);
            self::assertStringNotContainsString('exit' . '(', $provider);
        }
        self::assertStringContainsString('AuthenticationProviderUnavailable', $ldap);
    }

    private static function functionSource(string $source, string $startMarker, string $endMarker): string
    {
        $start = strpos($source, $startMarker);
        $end = strpos($source, $endMarker, false === $start ? 0 : $start);
        self::assertIsInt($start);
        self::assertIsInt($end);

        return substr($source, $start, $end - $start);
    }
}
