<?php

declare(strict_types=1);

namespace App\Tests\Unit\Antivirus\Infrastructure;

use App\Tests\Support\RecordingCommandRunner;
use MailWatch\Antivirus\Infrastructure\AntivirusScannerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../fixtures/legacy/functions.php';

final class AntivirusScannerRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mailwatch_test_conf'] = [];
        $GLOBALS['mailwatch_test_virus_conf'] = [];
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function scanners(): iterable
    {
        yield 'clamav' => ['clamav', 'clamscan', 'clamscan -V', 'clamav.awk'];
        yield 'sophos' => ['sophos', '/usr/local/bin/sophos', '/usr/local/bin/sophos -v', 'sophos.awk'];
        yield 'mcafee' => ['mcafee', '/usr/local/bin/mcafee', '/usr/local/bin/mcafee --version', 'mcafee.awk'];
        yield 'f-secure' => [
            'f-secure',
            '/opt/f-secure/fsav/bin/fsav',
            '/opt/f-secure/fsav/bin/fsav --version',
            'f-secure.awk',
        ];
    }

    #[DataProvider('scanners')]
    public function testItPipesTheProductVersionThroughItsAwkScript(
        string $id,
        string $executable,
        string $expectedCommand,
        string $expectedScript
    ): void {
        $scanner = $this->registry($this->installed($executable))->get($id);

        self::assertSame($id, $scanner->id);
        self::assertNotNull($scanner->reportCommand);
        self::assertStringStartsWith($expectedCommand . ' | awk -f ', $scanner->reportCommand);
        self::assertStringEndsWith($expectedScript . "'", $scanner->reportCommand);
    }

    #[DataProvider('scanners')]
    public function testItReportsNoCommandWhenTheProductIsNotInstalled(string $id): void
    {
        self::assertNull($this->registry(new RecordingCommandRunner())->get($id)->reportCommand);
    }

    /**
     * get_virus_conf() answers false for a product MailScanner does not have
     * configured, which is the common case on a host running only one scanner.
     */
    public function testItReportsNoCommandWhenTheProductIsNotConfigured(): void
    {
        $GLOBALS['mailwatch_test_virus_conf']['sophos'] = false;
        $commands = $this->installed('/usr/local/bin/sophos');

        self::assertNull($this->registry($commands)->get('sophos')->reportCommand);
        self::assertSame([], $commands->commands);
    }

    public function testItAsksTheShellWhetherTheExecutableCanBeRun(): void
    {
        $commands = $this->installed('clamscan');

        $this->registry($commands)->get('clamav');

        self::assertSame(["command -v 'clamscan' 2>/dev/null"], $commands->commands);
    }

    /**
     * A scanner configured as a command with arguments must be looked up by its
     * executable alone, or it would never be found.
     */
    public function testItLooksUpOnlyTheExecutableOfAConfiguredCommand(): void
    {
        $GLOBALS['mailwatch_test_virus_conf']['sophos'] = '/usr/local/bin/sophos --ide-dir /var/sophos';
        $commands = $this->installed('/usr/local/bin/sophos');

        $scanner = $this->registry($commands)->get('sophos');

        self::assertSame(["command -v '/usr/local/bin/sophos' 2>/dev/null"], $commands->commands);
        self::assertNotNull($scanner->reportCommand);
        self::assertStringStartsWith('/usr/local/bin/sophos --ide-dir /var/sophos -v |', $scanner->reportCommand);
    }

    public function testItUsesTheVersionSixOptionForFProt(): void
    {
        $GLOBALS['mailwatch_test_conf']['VirusScanners'] = 'f-prot-6/-6/scanner';

        self::assertStringStartsWith(
            '/usr/local/bin/f-prot -virno |',
            (string)$this->registry($this->installed('/usr/local/bin/f-prot'))->get('f-prot')->reportCommand
        );
    }

    public function testItUsesTheOlderOptionForEarlierFProt(): void
    {
        $GLOBALS['mailwatch_test_conf']['VirusScanners'] = 'f-prot/scanner';

        self::assertStringStartsWith(
            '/usr/local/bin/f-prot -verno |',
            (string)$this->registry($this->installed('/usr/local/bin/f-prot'))->get('f-prot')->reportCommand
        );
    }

    public function testFSecure12IsProbedWithoutAnAwkScript(): void
    {
        $scanner = $this->registry($this->installed('/opt/f-secure/linuxsecurity/bin/fsanalyze'))
            ->get('f-secure12');

        self::assertNotNull($scanner->reportCommand);
        self::assertStringNotContainsString('awk', $scanner->reportCommand);
        self::assertStringContainsString('notexistingfile.txt', $scanner->reportCommand);
    }

    public function testItRefusesAnUnknownScanner(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->registry(new RecordingCommandRunner())->get('../../etc/passwd');
    }

    public function testEveryListedScannerCanBeBuilt(): void
    {
        foreach (AntivirusScannerRegistry::SCANNERS as $id) {
            self::assertSame($id, $this->registry(new RecordingCommandRunner())->get($id)->id);
        }
    }

    private function installed(string $executable): RecordingCommandRunner
    {
        return new RecordingCommandRunner([
            "command -v '" . $executable . "' 2>/dev/null" => $executable . "\n",
        ]);
    }

    private function registry(RecordingCommandRunner $commands): AntivirusScannerRegistry
    {
        return new AntivirusScannerRegistry($commands, '/opt/mailwatch/mailscanner');
    }
}
