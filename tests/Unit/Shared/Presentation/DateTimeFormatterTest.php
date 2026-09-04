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

    public function testNamesTodayInTheDisplayZone(): void
    {
        $lateEvening = new \DateTimeImmutable('2026-01-15 23:30:00', new \DateTimeZone('UTC'));

        self::assertSame('2026-01-16', $this->formatter('Europe/Rome')->calendarDay(0, $lateEvening));
        self::assertSame('2026-01-15', $this->formatter('America/New_York')->calendarDay(0, $lateEvening));
        self::assertSame('2026-01-08', $this->formatter('America/New_York')->calendarDay(7, $lateEvening));
    }

    public function testTurnsADayIntoTheUtcIntervalThatContainsIt(): void
    {
        $formatter = $this->formatter('Europe/Rome');

        self::assertSame(['2026-01-14 23:00:00', '2026-01-15 23:00:00'], $formatter->utcInterval('2026-01-15'));
        self::assertSame(['2026-07-14 22:00:00', '2026-07-15 22:00:00'], $formatter->utcInterval('2026-07-15'));
    }

    public function testTheIntervalOfTheDayClocksMoveForwardIsAnHourShort(): void
    {
        self::assertSame(
            ['2026-03-28 23:00:00', '2026-03-29 22:00:00'],
            $this->formatter('Europe/Rome')->utcInterval('2026-03-29'),
        );
    }

    public function testRejectsADayThatDoesNotParse(): void
    {
        self::assertNull($this->formatter('UTC')->utcInterval('not a day'));
    }

    private function formatter(string $zone): DateTimeFormatter
    {
        return new DateTimeFormatter('%d/%m/%y', '%H:%i:%s', new \DateTimeZone($zone));
    }
}
