<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../mailscanner/filter.inc.php';

final class FilterDateFunctionTest extends TestCase
{
    #[DataProvider('provideDateFunctions')]
    public function testAcceptsDateFunctions(string $value): void
    {
        self::assertTrue(\Filter::isDateFunction($value), $value);
    }

    /** @return list<array{string}> */
    public static function provideDateFunctions(): array
    {
        return [
            ['CURRENT_DATE()'],
            ['curdate()'],
            ['UTC_TIMESTAMP()'],
            ['NOW() - INTERVAL 7 DAY'],
            ['CURDATE()+INTERVAL 1 MONTH'],
            ['DATE_SUB(CURDATE(), INTERVAL 30 DAY)'],
        ];
    }

    #[DataProvider('provideOtherExpressions')]
    public function testRejectsOtherExpressions(string $value): void
    {
        self::assertFalse(\Filter::isDateFunction($value), $value);
    }

    /** @return list<array{string}> */
    public static function provideOtherExpressions(): array
    {
        return [
            [''],
            ['(SELECT 1)'],
            ['NOW() OR 1=1'],
            ['SLEEP(3)'],
            ['NOW() - INTERVAL (SELECT 1) DAY'],
            ['CURDATE() AND (SELECT password FROM users LIMIT 1)'],
        ];
    }
}
