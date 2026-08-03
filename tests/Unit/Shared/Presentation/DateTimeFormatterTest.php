<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation;

use MailWatch\Shared\Infrastructure\Configuration\InvalidConfiguration;
use MailWatch\Shared\Presentation\DateTimeFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateTimeFormatterTest extends TestCase
{
    public function testConvertsFromUtcToTheDisplayZone(): void
    {
        $formatter = $this->formatter('Europe/Rome');

        self::assertSame('15/01/26 13:00:00', $formatter->dateTime('2026-01-15 12:00:00'));
    }

    public function testCrossesMidnightWhenTheOffsetRequiresIt(): void
    {
        $formatter = $this->formatter('Pacific/Auckland');

        self::assertSame('16/01/26 01:00:00', $formatter->dateTime('2026-01-15 12:00:00'));
    }

    public function testAppliesDaylightSavingOfTheStoredInstant(): void
    {
        $formatter = $this->formatter('Europe/Rome');

        self::assertSame('15/07/26 14:00:00', $formatter->dateTime('2026-07-15 12:00:00'));
    }

    public function testTakesTheSeparatorFromTheCaller(): void
    {
        $formatter = $this->formatter('UTC');

        self::assertSame('15/01/26<br/>12:00:00', $formatter->dateTime('2026-01-15 12:00:00', '<br/>'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function patterns(): iterable
    {
        yield 'named parts' => ['%W %D %M %Y', 'Thursday 15th January 2026'];
        yield 'abbreviations' => ['%a %b %c %e', 'Thu Jan 1 15'];
        yield 'twelve hour clock' => ['%h:%i %p', '12:00 PM'];
        yield 'composite time' => ['%T', '12:00:00'];
        yield 'literal percent' => ['%Y%%', '2026%'];
        yield 'literal letters' => ['%d MyDay', '15 MyDay'];
        yield 'unknown specifier' => ['%Q%Y', 'Q2026'];
    }

    #[DataProvider('patterns')]
    public function testTranslatesMysqlPatterns(string $pattern, string $expected): void
    {
        $formatter = new DateTimeFormatter($pattern, '%H', new \DateTimeZone('UTC'));

        self::assertSame($expected, $formatter->date('2026-01-15 12:00:00'));
    }

    public function testRejectsSpecifiersThatHaveNoEquivalent(): void
    {
        $this->expectException(InvalidConfiguration::class);

        new DateTimeFormatter('%j', '%H', new \DateTimeZone('UTC'));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function emptyValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'zero date' => ['0000-00-00 00:00:00'];
    }

    #[DataProvider('emptyValues')]
    public function testRendersNothingForAMissingValue(?string $value): void
    {
        self::assertSame('', $this->formatter('UTC')->dateTime($value));
    }

    private function formatter(string $zone): DateTimeFormatter
    {
        return new DateTimeFormatter('%d/%m/%y', '%H:%i:%s', new \DateTimeZone($zone));
    }
}
