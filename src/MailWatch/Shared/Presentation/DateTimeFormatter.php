<?php

declare(strict_types=1);

namespace MailWatch\Shared\Presentation;

use MailWatch\Shared\Infrastructure\Configuration\InvalidConfiguration;

/**
 * Renders a stored point in time for display.
 *
 * Stored values are UTC, the connection reads them literally, and the
 * conversion to the administrator's TIME_ZONE happens here: the database is
 * asked for data, never for a formatted string. DATE_FORMAT and TIME_FORMAT
 * keep their existing MySQL syntax, so no installation has to rewrite its
 * configuration; the patterns are translated to date() here.
 */
final readonly class DateTimeFormatter
{
    /**
     * MySQL DATE_FORMAT specifiers and their date() equivalents.
     *
     * @var array<string, string>
     */
    private const SPECIFIERS = [
        'a' => 'D', 'b' => 'M', 'c' => 'n', 'D' => 'jS', 'd' => 'd', 'e' => 'j',
        'f' => 'u', 'H' => 'H', 'h' => 'h', 'I' => 'h', 'i' => 'i', 'k' => 'G',
        'l' => 'g', 'M' => 'F', 'm' => 'm', 'p' => 'A', 'r' => 'h:i:s A',
        'S' => 's', 's' => 's', 'T' => 'H:i:s', 'v' => 'W', 'W' => 'l',
        'w' => 'w', 'x' => 'o', 'Y' => 'Y', 'y' => 'y',
    ];

    /**
     * Specifiers date() cannot express: day of the year, and the Sunday-based
     * week and year numbers. Rejected rather than approximated.
     *
     * @var list<string>
     */
    private const UNSUPPORTED = ['j', 'U', 'u', 'V', 'X'];

    private string $datePattern;

    private string $timePattern;

    public function __construct(
        string $datePattern,
        string $timePattern,
        private \DateTimeZone $displayZone,
    ) {
        $this->datePattern = self::translate($datePattern);
        $this->timePattern = self::translate($timePattern);
    }

    public static function fromConfiguration(): self
    {
        return new self(
            \defined('DATE_FORMAT') ? (string)\constant('DATE_FORMAT') : '%Y-%m-%d',
            \defined('TIME_FORMAT') ? (string)\constant('TIME_FORMAT') : '%H:%i:%s',
            new \DateTimeZone(\defined('TIME_ZONE') ? (string)\constant('TIME_ZONE') : date_default_timezone_get()),
        );
    }

    public function date(?string $utc): string
    {
        return $this->render($utc, $this->datePattern);
    }

    public function time(?string $utc): string
    {
        return $this->render($utc, $this->timePattern);
    }

    /**
     * The separator sits between the two patterns as a literal, so a caller
     * that wants the two halves on separate lines passes its own markup.
     */
    public function dateTime(?string $utc, string $separator = ' '): string
    {
        $date = $this->date($utc);

        return '' === $date ? '' : $date . $separator . $this->time($utc);
    }

    private function render(?string $utc, string $pattern): string
    {
        if (null === $utc || '' === trim($utc) || str_starts_with($utc, '0000-00-00')) {
            return '';
        }

        try {
            $moment = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return '';
        }

        return $moment->setTimezone($this->displayZone)->format($pattern);
    }

    private static function translate(string $pattern): string
    {
        $format = '';
        $length = \strlen($pattern);

        for ($i = 0; $i < $length; ++$i) {
            if ('%' !== $pattern[$i] || $i + 1 === $length) {
                $format .= '\\' . $pattern[$i];
                continue;
            }

            $specifier = $pattern[++$i];
            if (\in_array($specifier, self::UNSUPPORTED, true)) {
                throw new InvalidConfiguration(sprintf('The date format specifier %%%s has no equivalent outside MySQL', $specifier));
            }

            // MySQL prints an unknown specifier as the character itself.
            $format .= self::SPECIFIERS[$specifier] ?? '\\' . $specifier;
        }

        return $format;
    }
}
