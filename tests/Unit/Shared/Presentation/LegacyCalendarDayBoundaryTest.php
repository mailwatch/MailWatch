<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation;

use PHPUnit\Framework\TestCase;

/**
 * With the database session pinned to UTC, the database's idea of "today" is
 * the UTC day, while maillog.date is a calendar day of the receiving host.
 * Calendar days are named by DateTimeFormatter, so a legacy page must not ask
 * the database for one.
 */
final class LegacyCalendarDayBoundaryTest extends TestCase
{
    public function testNoLegacyPageAsksTheDatabaseForTheCurrentDay(): void
    {
        $root = \dirname(__DIR__, 4);
        $files = array_merge(
            glob($root . '/mailscanner/*.php') ?: [],
            glob($root . '/tools/Cron_jobs/*.php') ?: [],
        );
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression('/\b(?:CURRENT_DATE|CURDATE)\s*\(/i', $source, $file);
        }
    }

    public function testTheAuditLogReportFiltersOnUtcIntervals(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 4) . '/mailscanner/rep_audit_log.php');
        self::assertIsString($source);

        self::assertStringContainsString('->utcInterval(', $source);
        self::assertStringNotContainsString('23:59:59', $source);
        self::assertStringNotContainsString('00:00:00"', $source);
    }
}
