<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users;

use PHPUnit\Framework\TestCase;

final class LegacyAccountAdministrationBoundaryTest extends TestCase
{
    public function testAccountCrudIsOnlyAnAdapterOverTheUseCase(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/mailscanner/user_manager.php');
        self::assertIsString($source);

        $start = strpos($source, 'function newUser(');
        $end = strpos($source, 'function userFilter()', false === $start ? 0 : $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        $adapter = substr($source, $start, $end - $start);

        self::assertStringNotContainsString('dbquery' . '(', $adapter);
        self::assertStringNotContainsString('Database' . '::', $adapter);
        self::assertStringNotContainsString('password_' . 'hash', $adapter);
        self::assertStringNotContainsString('checkForExistingUser', $adapter);
        self::assertStringNotContainsString('testSameDomainMembership', $adapter);
        self::assertStringNotContainsString('testPermissions', $adapter);
        self::assertStringNotContainsString('storeUser', $adapter);
        self::assertStringContainsString('localAccountAdministration', $adapter);
        self::assertStringContainsString("'POST'", $adapter);
        self::assertStringContainsString('/user_manager.php delete token', $adapter);
        self::assertStringNotContainsString('XXXXXXXX', $source);
    }
}
