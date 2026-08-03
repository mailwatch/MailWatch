<?php

declare(strict_types=1);

namespace MailWatch;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use MailWatch\Antivirus\Domain\AntivirusScanner;
use MailWatch\Antivirus\Domain\FSecureReportParser;
use MailWatch\Antivirus\Http\AntivirusStatusController;
use MailWatch\Antivirus\Http\FSecureStatusController;
use MailWatch\Antivirus\Http\StatusController;
use MailWatch\Antivirus\Infrastructure\AntivirusScannerRegistry;
use MailWatch\Lists\Http\AllowBlockListController;
use MailWatch\MailLog\Http\MessageController;
use MailWatch\Shared\Application\Port\CommandRunner;
use MailWatch\Shared\Http\PageGuard;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfigurationLoader;
use MailWatch\Shared\Infrastructure\Database\DatabaseConfigurationLoader;
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
     * The status page for one antivirus product.
     *
     * Which implementation serves it is wiring, not a decision the entry point
     * should make: F-Secure 12 parses its own output, the rest are formatted by
     * an awk script.
     *
     * Static for the same reason as the renderer: it is reached without an API
     * configuration.
     */
    public static function antivirusStatusController(string $scanner): StatusController
    {
        if ('f-secure12' === $scanner) {
            return self::fSecureStatusController();
        }

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

    private static function fSecureStatusController(): FSecureStatusController
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

    /**
     * The DBAL connection, built from the constants conf.php already defines.
     *
     * Nothing serves a request through it yet: the gateways arrive next, and
     * for now it exists so the migrator can reach the configured database.
     */
    public static function databaseConnection(): Connection
    {
        return DriverManager::getConnection(
            (new DatabaseConfigurationLoader())->load()->parameters()
        );
    }

    /**
     * Migrations are wired here rather than in a configuration file so that the
     * connection comes from the same loader the application uses, instead of a
     * second copy of the credentials living beside conf.php.
     */
    public static function migrations(Connection $connection): DependencyFactory
    {
        return DependencyFactory::fromConnection(
            new ConfigurationArray([
                'migrations_paths' => [
                    'MailWatch\Migrations' => self::projectDirectory() . '/migrations',
                ],
                'table_storage' => [
                    'table_name' => 'mailwatch_migrations',
                ],
            ]),
            new ExistingConnection($connection)
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
