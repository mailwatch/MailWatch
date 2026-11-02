<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../mailscanner/filter.inc.php';

class FilterDateFunctionTest extends TestCase
{
    /**
     * @dataProvider provideDateFunctions
     */
    public function testAcceptsDateFunctions($value)
    {
        $this->assertTrue(\Filter::isDateFunction($value), $value);
    }

    public function provideDateFunctions()
    {
        return [
            ['CURRENT_DATE()'],
            ['curdate()'],
            ['NOW() - INTERVAL 7 DAY'],
            ['CURDATE()+INTERVAL 1 MONTH'],
            ['DATE_SUB(CURDATE(), INTERVAL 30 DAY)'],
        ];
    }

    /**
     * @dataProvider provideOtherExpressions
     */
    public function testRejectsOtherExpressions($value)
    {
        $this->assertFalse(\Filter::isDateFunction($value), $value);
    }

    public function provideOtherExpressions()
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
