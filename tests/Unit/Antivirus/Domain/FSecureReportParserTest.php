<?php

declare(strict_types=1);

namespace App\Tests\Unit\Antivirus\Domain;

use MailWatch\Antivirus\Domain\FSecureReportParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FSecureReportParserTest extends TestCase
{
    public function testItReadsEveryEngineFromRealOutput(): void
    {
        $engines = (new FSecureReportParser())->parse($this->realOutput());

        self::assertCount(4, $engines);

        self::assertSame('Aquarius', $engines[0]->name);
        self::assertSame('18.0.790', $engines[0]->version);
        self::assertSame('2021-12-15_10', $engines[0]->date);

        self::assertSame('Hydra', $engines[1]->name);
        self::assertSame('6.0.425', $engines[1]->version);
        self::assertSame('2021-12-14_01', $engines[1]->date);

        self::assertSame('FMLib', $engines[2]->name);
        self::assertSame('17.11.62.513 (8a526f1)', $engines[2]->version);
        self::assertSame('2021-12-02_01', $engines[2]->date);

        self::assertSame('fsicapd', $engines[3]->name);
        self::assertSame('2.0.217', $engines[3]->version);
        self::assertNull($engines[3]->date);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function outputWithoutEngines(): iterable
    {
        yield 'nothing at all' => [''];
        yield 'command not found' => ["sh: fsanalyze: command not found\n"];
        yield 'only the failure line' => ["fsanalyze: failed to process './notexistingfile.txt'\n"];
        yield 'wording changed' => ["Engine versions: F-Secure Aquarius 19.0\n"];
    }

    #[DataProvider('outputWithoutEngines')]
    public function testItReportsNoEnginesRatherThanGuessing(string $output): void
    {
        self::assertSame([], (new FSecureReportParser())->parse($output));
    }

    private function realOutput(): string
    {
        $fixture = \dirname(__DIR__, 3) . '/fixtures/assets/antivirus/fsecure12.txt';
        $contents = file_get_contents($fixture);

        self::assertIsString($contents, 'The recorded fsanalyze output is missing');

        return $contents;
    }
}
