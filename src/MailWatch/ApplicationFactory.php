<?php

declare(strict_types=1);

namespace MailWatch;

use MailWatch\Antivirus\Domain\AntivirusScanner;
use MailWatch\Antivirus\Domain\FSecureReportParser;
use MailWatch\Antivirus\Http\AntivirusStatusController;
use MailWatch\Antivirus\Http\FSecureStatusController;
use MailWatch\Antivirus\Infrastructure\AntivirusScannerRegistry;
use MailWatch\Lists\Http\AllowBlockListController;
use MailWatch\MailLog\Http\MessageController;
use MailWatch\Shared\Application\Port\CommandRunner;
use MailWatch\Shared\Http\PageGuard;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfigurationLoader;
use MailWatch\Shared\Infrastructure\Security\ApiKeyAuthenticator;
use MailWatch\Shared\Infrastructure\System\ShellCommandRunner;
use MailWatch\Shared\Presentation\PageLayout;
use MailWatch\Shared\Presentation\TemplateRenderer;
use MailWatch\SpamSettings\Http\SpamSettingsController;

final readonly class ApplicationFactory
{
    public function __construct(
        private ApiConfiguration $apiConfiguration
    ) {
    }

    public static function create(): self
    {
        return new self((new ApiConfigurationLoader())->load());
    }

    public function apiConfiguration(): ApiConfiguration
    {
        return $this->apiConfiguration;
    }

    public function apiKeyAuthenticator(): ApiKeyAuthenticator
    {
        return new ApiKeyAuthenticator($this->apiConfiguration);
    }

    public function messageController(): MessageController
    {
        return new MessageController(
            $this->apiConfiguration,
            $this->apiKeyAuthenticator(),
            $this->databaseConnector(),
        );
    }

    public function allowBlockListController(): AllowBlockListController
    {
        return new AllowBlockListController(
            $this->apiConfiguration,
            $this->apiKeyAuthenticator(),
            $this->databaseConnector(),
        );
    }

    public function spamSettingsController(): SpamSettingsController
    {
        return new SpamSettingsController(
            $this->apiConfiguration,
            $this->apiKeyAuthenticator(),
            $this->databaseConnector(),
        );
    }

    /**
     * Static because rendering depends on nothing the factory is constructed
     * with: the page scripts need a renderer without a valid API configuration.
     */
    public static function templateRenderer(): TemplateRenderer
    {
        return TemplateRenderer::create(
            self::projectDirectory() . '/templates',
            self::templateCacheDirectory(),
            \defined('DEBUG') && true === DEBUG
        );
    }

    /**
     * The antivirus status page, wired for one product.
     *
     * Static for the same reason as the renderer: the page scripts reach it
     * without an API configuration.
     */
    public static function antivirusStatusController(): AntivirusStatusController
    {
        return new AntivirusStatusController(
            self::templateRenderer(),
            new PageLayout($_SESSION ?? [], self::projectDirectory()),
            new PageGuard($_SESSION ?? []),
            self::commandRunner(),
            static function(string $message): void {
                audit_log($message);
            }
        );
    }

    public static function fSecureStatusController(): FSecureStatusController
    {
        return new FSecureStatusController(
            self::templateRenderer(),
            new PageLayout($_SESSION ?? [], self::projectDirectory()),
            new PageGuard($_SESSION ?? []),
            self::commandRunner(),
            new FSecureReportParser(),
            static function(string $message): void {
                audit_log($message);
            }
        );
    }

    public static function antivirusScanner(string $id): AntivirusScanner
    {
        return (new AntivirusScannerRegistry(
            self::commandRunner(),
            self::projectDirectory() . '/mailscanner'
        ))->get($id);
    }

    private static function commandRunner(): CommandRunner
    {
        return new ShellCommandRunner();
    }

    /**
     * The compiled template cache is an optimisation, not a requirement: an
     * installation unpacked into a read-only directory still renders, it just
     * compiles the templates on every request.
     */
    private static function templateCacheDirectory(): string|false
    {
        $cacheDirectory = self::projectDirectory() . '/var/cache/twig';

        if (!is_dir($cacheDirectory) && !@mkdir($cacheDirectory, 0o750, true) && !is_dir($cacheDirectory)) {
            return false;
        }

        return is_writable($cacheDirectory) ? $cacheDirectory : false;
    }

    private static function projectDirectory(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * @return \Closure(): object
     */
    private function databaseConnector(): \Closure
    {
        return static fn(): object => \Database::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    }
}
