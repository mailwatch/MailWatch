<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users;

use PHPUnit\Framework\TestCase;

final class LegacySavedFilterBoundaryTest extends TestCase
{
    public function testSavedFilterAdministrationIsOnlyAnAdapterOverTheUseCase(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/mailscanner/user_manager.php');
        self::assertIsString($source);

        $start = strpos($source, 'function userFilter()');
        $end = strpos($source, 'function sendReport()', false === $start ? 0 : $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        $adapter = substr($source, $start, $end - $start);

        self::assertStringNotContainsString('dbquery' . '(', $adapter);
        self::assertStringNotContainsString('Database' . '::', $adapter);
        self::assertStringNotContainsString("\$_GET['delete']", $adapter);
        self::assertStringNotContainsString("\$_GET['change_state']", $adapter);
        self::assertStringContainsString("\$_POST['filter_operation']", $adapter);
        self::assertStringContainsString('checkFormToken', $adapter);
    }
}
