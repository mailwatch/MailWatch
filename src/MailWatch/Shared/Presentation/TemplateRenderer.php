<?php

declare(strict_types=1);

namespace MailWatch\Shared\Presentation;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Renders Twig templates.
 *
 * This is the single place where the template engine is configured. Callers
 * receive rendered markup as a string and decide themselves whether to send it,
 * which is what allows a page to become a controller later on.
 */
final readonly class TemplateRenderer
{
    public function __construct(
        private Environment $twig
    ) {
    }

    /**
     * @param string       $templateDirectory directory holding the .html.twig files
     * @param string|false $cacheDirectory    compiled template cache, or false to compile on every request
     * @param bool         $debug             reload changed templates and keep debugging information
     */
    public static function create(
        string $templateDirectory,
        string|false $cacheDirectory = false,
        bool $debug = false
    ): self {
        $twig = new Environment(new FilesystemLoader($templateDirectory), [
            'cache' => $cacheDirectory,
            'debug' => $debug,
            // Always, not only when debugging: an upgrade copies new templates
            // over an existing installation, and a cache that never rechecks
            // would go on serving the markup of the previous release with
            // nothing to indicate it.
            'auto_reload' => true,
            'strict_variables' => true,
        ]);

        self::addTranslationFunction($twig);

        return new self($twig);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function display(string $template, array $context = []): void
    {
        $this->twig->display($template, $context);
    }

    public function environment(): Environment
    {
        return $this->twig;
    }

    /**
     * Exposes the existing translation function to templates as {{ __('key') }}.
     *
     * The result is marked as HTML-safe because the language files are part of
     * the installation rather than user input, and because __() itself wraps
     * missing keys in markup when DEBUG is enabled. Never pass request data
     * through it.
     */
    private static function addTranslationFunction(Environment $twig): void
    {
        $twig->addFunction(new TwigFunction(
            '__',
            static function(string $key, bool $useSystemLang = false): string {
                if (!\function_exists('__')) {
                    return $key;
                }

                return (string)__($key, $useSystemLang);
            },
            ['is_safe' => ['html']]
        ));
    }
}
