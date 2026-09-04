<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation;

use PHPUnit\Framework\TestCase;

/**
 * The traffic graph and the previous-day report feed GraphGenerator the raw
 * maillog.timestamp, which the pinned session returns as UTC, while the time
 * scale is built in the default zone. This pins that a stored instant lands
 * in the local bucket, not in the bucket of the same wall-clock digits.
 */
final class GraphGeneratorTimeScaleTest extends TestCase
{
    private string $previousZone;

    protected function setUp(): void
    {
        require_once \dirname(__DIR__, 4) . '/mailscanner/graphgenerator.inc.php';
        $this->previousZone = date_default_timezone_get();
        date_default_timezone_set('Europe/Rome');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousZone);
    }

    public function testAStoredUtcInstantIsPlacedInTheLocalBucket(): void
    {
        // 12:00 UTC is 14:00 in Rome in July: the last of four hourly buckets.
        self::assertSame([0, 0, 0, 1], $this->convert('2026-07-15 14:30:00', ['2026-07-15 12:00:00']));
    }

    public function testTheMostRecentHoursAreNotEmpty(): void
    {
        // Read as local time, 14:20 UTC would be 14:20 Rome and fall inside the
        // window while the real instant is 16:20 and later than now.
        self::assertSame([0, 0, 0, 0], $this->convert('2026-07-15 14:30:00', ['2026-07-15 14:20:00']));
        self::assertSame([0, 0, 0, 1], $this->convert('2026-07-15 14:30:00', ['2026-07-15 12:20:00']));
    }

    /**
     * @param list<string> $storedInstants
     *
     * @return list<int>
     */
    private function convert(string $localNow, array $storedInstants): array
    {
        $generator = new \GraphGenerator();
        $generator->settings = [
            'timeInterval' => 'PT3H',
            'timeScale' => 'PT1H',
            'timeFormat' => '%H:%i',
            'timeGroupFormat' => 'Y-m-d H',
            'now' => new \DateTime($localNow),
        ];

        $data = new \ReflectionProperty(\GraphGenerator::class, 'data');
        $data->setValue($generator, [
            'xaxis' => $storedInstants,
            'total_mail' => array_fill(0, \count($storedInstants), 1),
        ]);

        (new \ReflectionMethod(\GraphGenerator::class, 'convertToTimeScale'))->invoke($generator, 'total_mail');

        /** @var array{total_mailconv: list<int>} $converted */
        $converted = $data->getValue($generator);

        return $converted['total_mailconv'];
    }
}
