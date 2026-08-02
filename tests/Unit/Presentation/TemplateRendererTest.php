<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation;

use MailWatch\Presentation\TemplateRenderer;
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
        foreach (glob($this->templateDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->templateDirectory);
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

        // Without the page bootstrap there is no __() to call, and the renderer
        // falls back to the key itself rather than failing.
        self::assertSame(
            '<title>mwforms03</title>',
            $this->renderer()->render('page.html.twig')
        );
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
