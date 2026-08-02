<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation;

use MailWatch\Shared\Presentation\TemplateRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Error\RuntimeError;

final class TemplateRendererTest extends TestCase
{
    private string $templateDirectory;

    protected function setUp(): void
    {
        $directory = sys_get_temp_dir() . '/mailwatch-templates-' . bin2hex(random_bytes(6));
        mkdir($directory, 0o700, true);

        $this->templateDirectory = $directory;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->templateDirectory);
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDirectory($entry) : unlink($entry);
        }

        rmdir($directory);
    }

    public function testItRendersATemplateWithItsContext(): void
    {
        $this->writeTemplate('page.html.twig', '<h1>{{ title }}</h1>');

        self::assertSame(
            '<h1>Quarantine</h1>',
            $this->renderer()->render('page.html.twig', ['title' => 'Quarantine'])
        );
    }

    public function testItEscapesValuesByDefault(): void
    {
        $this->writeTemplate('page.html.twig', '<p>{{ subject }}</p>');

        self::assertSame(
            '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>',
            $this->renderer()->render('page.html.twig', ['subject' => '<script>alert(1)</script>'])
        );
    }

    public function testItFailsOnAVariableTheCallerForgotToPass(): void
    {
        $this->writeTemplate('page.html.twig', '<h1>{{ title }}</h1>');

        $this->expectException(RuntimeError::class);

        $this->renderer()->render('page.html.twig');
    }

    public function testItExposesTheTranslationFunction(): void
    {
        $this->writeTemplate('page.html.twig', '<title>{{ __(\'mwforms03\') }}</title>');

        // A key no language file defines renders as the key itself, and a
        // template rendered without the page bootstrap does the same rather
        // than failing.
        self::assertSame(
            '<title>mwforms03</title>',
            $this->renderer()->render('page.html.twig')
        );
    }

    /**
     * Twig only rechecks a cached template when auto_reload is on, and it must
     * be on even outside debugging: an upgrade copies new templates over an
     * existing installation, and a cache that never rechecks would go on
     * serving the previous release's markup.
     *
     * This asserts the setting rather than the behaviour, because Twig reuses a
     * compiled class already declared in the process whatever its age, so a
     * stale render cannot be reproduced in a single test run.
     */
    public function testItRechecksTemplatesEvenWhenNotDebugging(): void
    {
        $renderer = TemplateRenderer::create($this->templateDirectory, false, false);

        self::assertTrue($renderer->environment()->isAutoReload());
        self::assertFalse($renderer->environment()->isDebug());
    }

    public function testItCompilesWithoutACacheDirectory(): void
    {
        $this->writeTemplate('page.html.twig', 'plain');

        $renderer = TemplateRenderer::create($this->templateDirectory, false);

        self::assertSame('plain', $renderer->render('page.html.twig'));
    }

    private function renderer(): TemplateRenderer
    {
        return TemplateRenderer::create($this->templateDirectory);
    }

    private function writeTemplate(string $name, string $contents): void
    {
        file_put_contents($this->templateDirectory . '/' . $name, $contents);
    }
}
