<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users;

use PHPUnit\Framework\TestCase;

final class LegacyOwnProfileBoundaryTest extends TestCase
{
    public function testOwnProfileManagementIsOnlyAnAdapterOverTheUseCase(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/mailscanner/user_manager.php');
        self::assertIsString($source);

        $start = strpos($source, "} elseif (!isset(\$_POST['submit'])) {");
        $end = strpos($source, '// Add footer', false === $start ? 0 : $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        $adapter = substr($source, $start, $end - $start);

        self::assertStringNotContainsString('dbquery' . '(', $adapter);
        self::assertStringNotContainsString('Database' . '::', $adapter);
        self::assertStringNotContainsString('password_' . 'hash', $adapter);
        self::assertStringNotContainsString('xxxxxxxx', strtolower($adapter));
        self::assertStringContainsString('ownProfileAdministration', $adapter);
        self::assertStringContainsString('AuthenticationSource::fromSession', $adapter);
        self::assertStringContainsString('checkFormToken', $adapter);
    }
}
