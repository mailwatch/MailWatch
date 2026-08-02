<?php

declare(strict_types=1);

namespace App\Tests\Unit\Antivirus\Http;

use App\Tests\Support\FakePageFrame;
use App\Tests\Support\RecordingCommandRunner;
use MailWatch\Antivirus\Domain\AntivirusScanner;
use MailWatch\Antivirus\Http\AntivirusStatusController;
use MailWatch\Shared\Http\PageGuard;
use MailWatch\Shared\Presentation\TemplateRenderer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../fixtures/legacy/functions.php';

final class AntivirusStatusControllerTest extends TestCase
{
    /** @var list<string> */
    private array $auditLog = [];

    private FakePageFrame $frame;

    protected function setUp(): void
    {
        $this->auditLog = [];
        $this->frame = new FakePageFrame();
    }

    public function testItRendersTheReportInsideThePageFrame(): void
    {
        $commands = new RecordingCommandRunner([
            '/usr/local/bin/sophos -v' => '<table class="sophos"><tr><td>Engine 3.2</td></tr></table>',
        ]);

        $response = $this->controller($commands)->handle(
            new AntivirusScanner('sophos', 'sophos53', '/usr/local/bin/sophos -v')
        );

        self::assertSame(200, $response->statusCode());
        self::assertNull($response->location());
        self::assertContains('/usr/local/bin/sophos -v', $commands->commands);

        $body = $response->body();
        self::assertStringStartsWith('<!DOCTYPE HTML>', $body);
        self::assertStringContainsString('<table class="boxtable" width="100%">', $body);
        self::assertStringContainsString('<tr><td>Engine 3.2</td></tr>', $body);
        self::assertStringContainsString('<ul id="menu">', $body);
        self::assertStringEndsWith("</html>\n", $body);
    }

    public function testItTitlesThePageAfterTheScanner(): void
    {
        $response = $this->controller()->handle(
            new AntivirusScanner('clamav', 'avclamavstatus19', 'clamscan -V')
        );

        self::assertSame(['avclamavstatus19'], $this->frame->titles);
        self::assertStringContainsString('<title>MailWatch - avclamavstatus19</title>', $response->body());
    }

    public function testItSaysSoWhenTheScannerIsNotInstalled(): void
    {
        $commands = new RecordingCommandRunner();

        $response = $this->controller($commands)->handle(
            new AntivirusScanner('clamav', 'avclamavstatus19', null)
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame([], $commands->commands);
        self::assertStringContainsString('<table class="boxtable" width="100%">', $response->body());
        self::assertStringContainsString('avnotavailable19', $response->body());
    }

    public function testItSendsANonAdministratorBackAndRecordsIt(): void
    {
        $commands = new RecordingCommandRunner();

        $response = $this->controller($commands, isAdministrator: false)->handle(
            new AntivirusScanner('sophos', 'sophos53', '/usr/local/bin/sophos -v')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/', $response->location());
        self::assertSame('', $response->body());
        self::assertSame(['auditlog19'], $this->auditLog);

        // The scanner must not run for someone not allowed to see the page.
        self::assertSame([], $commands->commands);
        self::assertSame([], $this->frame->titles);
    }

    private function controller(
        ?RecordingCommandRunner $commands = null,
        bool $isAdministrator = true
    ): AntivirusStatusController {
        return new AntivirusStatusController(
            TemplateRenderer::create(\dirname(__DIR__, 4) . '/templates'),
            $this->frame,
            new PageGuard(['user_type' => $isAdministrator ? 'A' : 'U']),
            $commands ?? new RecordingCommandRunner(),
            function (string $message): void {
                $this->auditLog[] = $message;
            }
        );
    }
}
