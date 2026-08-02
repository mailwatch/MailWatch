<?php

declare(strict_types=1);

namespace App\Tests\Unit\Antivirus\Http;

use App\Tests\Support\FakePageFrame;
use App\Tests\Support\RecordingCommandRunner;
use MailWatch\Antivirus\Domain\AntivirusScanner;
use MailWatch\Antivirus\Domain\FSecureReportParser;
use MailWatch\Antivirus\Http\FSecureStatusController;
use MailWatch\Shared\Http\PageGuard;
use MailWatch\Shared\Presentation\TemplateRenderer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../fixtures/legacy/functions.php';

final class FSecureStatusControllerTest extends TestCase
{
    private const PROBE = '/opt/f-secure/linuxsecurity/bin/fsanalyze /opt/mailwatch/notexistingfile.txt';

    /** @var list<string> */
    private array $auditLog = [];

    protected function setUp(): void
    {
        $this->auditLog = [];
    }

    public function testItShowsATableOfEngines(): void
    {
        $output = (string)file_get_contents(
            \dirname(__DIR__, 3) . '/fixtures/assets/antivirus/fsecure12.txt'
        );

        $response = $this->controller(new RecordingCommandRunner([self::PROBE => $output]))
            ->handle($this->scanner());

        $body = $response->body();
        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('F-Secure 12 Information', $body);
        self::assertStringContainsString('<tr><td>Aquarius</td><td>18.0.790</td><td>2021-12-15_10</td></tr>', $body);
        self::assertStringContainsString('<tr><td>fsicapd</td><td>2.0.217</td><td>&nbsp;</td></tr>', $body);
    }

    public function testItSaysSoWhenTheProductIsNotInstalled(): void
    {
        $commands = new RecordingCommandRunner();

        $response = $this->controller($commands)->handle(
            new AntivirusScanner('f-secure12', 'fsecurestatus23', null)
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame([], $commands->commands);
        self::assertStringContainsString('avnotavailable19', $response->body());
    }

    /**
     * Installed but reporting in a wording the parser does not know is a
     * different case, and must not claim the product is missing.
     */
    public function testItShowsNoTableWhenTheOutputIsNotRecognised(): void
    {
        $response = $this->controller(
            new RecordingCommandRunner([self::PROBE => "Engine versions: something new\n"])
        )->handle($this->scanner());

        $body = $response->body();
        self::assertSame(200, $response->statusCode());
        self::assertStringStartsWith('<!DOCTYPE HTML>', $body);
        self::assertStringContainsString('<table class="boxtable" style="width: 100%">', $body);
        self::assertStringNotContainsString('F-Secure 12 Information', $body);
        self::assertStringNotContainsString('avnotavailable19', $body);
    }

    public function testItSendsANonAdministratorBack(): void
    {
        $commands = new RecordingCommandRunner();

        $response = $this->controller($commands, isAdministrator: false)->handle($this->scanner());

        self::assertSame(302, $response->statusCode());
        self::assertSame('/', $response->location());
        self::assertSame(['auditlog19'], $this->auditLog);
        self::assertSame([], $commands->commands);
    }

    private function scanner(): AntivirusScanner
    {
        return new AntivirusScanner('f-secure12', 'fsecurestatus23', self::PROBE);
    }

    private function controller(
        RecordingCommandRunner $commands,
        bool $isAdministrator = true
    ): FSecureStatusController {
        return new FSecureStatusController(
            TemplateRenderer::create(\dirname(__DIR__, 4) . '/templates'),
            new FakePageFrame(),
            new PageGuard(['user_type' => $isAdministrator ? 'A' : 'U']),
            $commands,
            new FSecureReportParser(),
            function (string $message): void {
                $this->auditLog[] = $message;
            }
        );
    }
}
