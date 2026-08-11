<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lists;

use PHPUnit\Framework\TestCase;

final class LegacyListPageSqlBoundaryTest extends TestCase
{
    public function testTheLegacyPageIsOnlyAnAdapterOverTheListUseCase(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/mailscanner/lists.php');
        self::assertIsString($source);

        self::assertStringNotContainsString('dbquery' . '(', $source);
        self::assertStringNotContainsString('Database' . '::', $source);
        self::assertDoesNotMatchRegularExpression(
            '/\b(?:REPLACE\s+INTO|INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+\w+\s+SET|SELECT\s+.+\s+FROM)\b/is',
            $source,
        );
        self::assertStringNotContainsString("\$_GET['submit']", $source);
        self::assertStringContainsString('method="post"', $source);
        self::assertStringContainsString('checkFormToken', $source);
    }
}
