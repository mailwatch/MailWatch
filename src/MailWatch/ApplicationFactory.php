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
use MailWatch\Lists\Application\GetAllowBlockListSnapshot;
use MailWatch\Lists\Application\ManageAllowBlockLists;
use MailWatch\Lists\Http\AllowBlockListController;
use MailWatch\Lists\Infrastructure\Database\DbalAllowBlockListGateway;
use MailWatch\Lists\Infrastructure\Database\DbalListAdministrationGateway;
use MailWatch\MailLog\Application\IngestMailLog;
use MailWatch\MailLog\Http\MessageController;
use MailWatch\MailLog\Infrastructure\Database\DbalMailLogGateway;
use MailWatch\Quarantine\Application\QuarantineMessageGateway;
use MailWatch\Quarantine\Application\QuarantineReleaser;
use MailWatch\Quarantine\Application\QuarantineStorage;
use MailWatch\Quarantine\Application\RemoteQuarantineNode;
use MailWatch\Quarantine\Application\SpamLearner;
use MailWatch\Quarantine\Domain\MessageScope;
use MailWatch\Quarantine\Domain\QuarantineAccess;
use MailWatch\Quarantine\Domain\ReleaseNotice;
use MailWatch\Quarantine\Infrastructure\Database\DbalQuarantineMessageGateway;
use MailWatch\Quarantine\Infrastructure\Mail\SendmailQuarantineReleaser;
use MailWatch\Quarantine\Infrastructure\Mail\SmtpQuarantineReleaser;
use MailWatch\Quarantine\Infrastructure\Rpc\XmlRpcQuarantineNode;
use MailWatch\Quarantine\Infrastructure\Storage\FilesystemQuarantineStorage;
use MailWatch\Quarantine\Infrastructure\System\CommandLineSpamLearner;
use MailWatch\Shared\Application\Port\CommandRunner;
use MailWatch\Shared\Http\ApiRequestContext;
use MailWatch\Shared\Http\ApiTelemetry;
use MailWatch\Shared\Http\PageGuard;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfiguration;
use MailWatch\Shared\Infrastructure\Configuration\ApiConfigurationLoader;
use MailWatch\Shared\Infrastructure\Database\DatabaseConfigurationLoader;
use MailWatch\Shared\Infrastructure\Logging\ErrorLogLogger;
use MailWatch\Shared\Infrastructure\Security\ApiKeyAuthenticator;
use MailWatch\Shared\Infrastructure\Security\NativePasswordHasher;
use MailWatch\Shared\Infrastructure\System\ShellCommandRunner;
use MailWatch\Shared\Infrastructure\System\SymfonyProcessRunner;
use MailWatch\Shared\Presentation\PageLayout;
use MailWatch\Shared\Presentation\TemplateRenderer;
use MailWatch\SpamSettings\Application\GetSpamSettingsSnapshot;
use MailWatch\SpamSettings\Http\SpamSettingsController;
use MailWatch\SpamSettings\Infrastructure\Database\DbalSpamSettingsGateway;
use MailWatch\Users\Application\AuthenticateUser;
use MailWatch\Users\Application\ManageLocalAccounts;
use MailWatch\Users\Application\ManageOwnProfile;
use MailWatch\Users\Application\ManageSavedFilters;
use MailWatch\Users\Infrastructure\Authentication\ImapCredentialVerifier;
use MailWatch\Users\Infrastructure\Authentication\LdapCredentialVerifier;
use MailWatch\Users\Infrastructure\Database\DbalAccountAdministrationGateway;
use MailWatch\Users\Infrastructure\Database\DbalLoginAccountGateway;
use MailWatch\Users\Infrastructure\Database\DbalSavedFilterAdministrationGateway;
use MailWatch\Users\Infrastructure\Database\DbalUserProfileGateway;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

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

    public function messageController(ApiRequestContext $request, ApiTelemetry $telemetry): MessageController
    {
        return new MessageController(
            $this->apiConfiguration,
            $this->apiKeyAuthenticator(),
            new IngestMailLog(new DbalMailLogGateway(self::databaseConnection())),
            $request,
            $telemetry,
        );
    }

    public function allowBlockListController(ApiRequestContext $request, ApiTelemetry $telemetry): AllowBlockListController
    {
        return new AllowBlockListController(
            $this->apiConfiguration,
            $this->apiKeyAuthenticator(),
            new GetAllowBlockListSnapshot(new DbalAllowBlockListGateway(self::databaseConnection())),
            $request,
            $telemetry,
        );
    }

    public function spamSettingsController(ApiRequestContext $request, ApiTelemetry $telemetry): SpamSettingsController
    {
        return new SpamSettingsController(
            $this->apiConfiguration,
            $this->apiKeyAuthenticator(),
            new GetSpamSettingsSnapshot(new DbalSpamSettingsGateway(self::databaseConnection())),
            $request,
            $telemetry,
        );
    }

    public static function apiTelemetry(): ApiTelemetry
    {
        return new ApiTelemetry(new ErrorLogLogger());
    }

    public static function listAdministration(): ManageAllowBlockLists
    {
        return new ManageAllowBlockLists(
            new DbalListAdministrationGateway(self::databaseConnection()),
        );
    }

    public static function savedFilterAdministration(): ManageSavedFilters
    {
        return new ManageSavedFilters(
            new DbalSavedFilterAdministrationGateway(self::databaseConnection()),
        );
    }

    public static function ownProfileAdministration(): ManageOwnProfile
    {
        return new ManageOwnProfile(
            new DbalUserProfileGateway(self::databaseConnection()),
            new NativePasswordHasher(),
        );
    }

    public static function localAccountAdministration(): ManageLocalAccounts
    {
        return new ManageLocalAccounts(
            new DbalAccountAdministrationGateway(self::databaseConnection()),
            new NativePasswordHasher(),
        );
    }

    public static function userAuthentication(): AuthenticateUser
    {
        $providers = [];
        if (\defined('USE_LDAP') && true === USE_LDAP) {
            $providers[] = new LdapCredentialVerifier();
        }
        if (\defined('USE_IMAP') && true === USE_IMAP) {
            $providers[] = new ImapCredentialVerifier();
        }
        $passwords = new NativePasswordHasher();

        return new AuthenticateUser(
            new DbalLoginAccountGateway(self::databaseConnection()),
            $passwords,
            $passwords,
            $providers,
            \defined('SESSION_TIMEOUT') ? (int)SESSION_TIMEOUT : null,
        );
    }

    /**
     * The gateway is kept for the request because the legacy pages ask for it
     * once per message inside their bulk loops, and a fresh instance would mean
     * a fresh database connection each time round.
     */
    public static function quarantineMessages(): QuarantineMessageGateway
    {
        static $gateway = null;

        return $gateway ??= new DbalQuarantineMessageGateway(self::databaseConnection());
    }

    /**
     * The quarantine tree, which MailScanner writes and MailWatch only reads
     * and prunes. Its location comes from the MailScanner configuration, so
     * the caller passes it rather than the factory reading it.
     */
    public static function quarantineStorage(string $quarantineDirectory): QuarantineStorage
    {
        return new FilesystemQuarantineStorage($quarantineDirectory, self::commandRunner());
    }

    /**
     * The quarantine of another MailScanner node.
     *
     * Port and scheme follow the page script's own precedence: SSL_ONLY wins,
     * then RPC_SSL, and a configured RPC_PORT applies to either.
     *
     * Certificate verification defaults to on. An installation whose nodes
     * carry self-signed certificates says so with RPC_VERIFY_PEER.
     */
    public static function quarantineRemoteNode(): RemoteQuarantineNode
    {
        $secure = (\defined('SSL_ONLY') && true === SSL_ONLY)
            || (\defined('RPC_SSL') && true === RPC_SSL);

        return new XmlRpcQuarantineNode(
            \defined('RPC_RELATIVE_PATH') ? (string)RPC_RELATIVE_PATH : '',
            \defined('RPC_PORT') ? RPC_PORT : ($secure ? 443 : 80),
            $secure ? 'https' : 'http',
            \defined('DEBUG') && true === DEBUG,
            !\defined('RPC_VERIFY_PEER') || false !== RPC_VERIFY_PEER,
        );
    }

    /**
     * How a released message leaves MailWatch.
     *
     * The covering message comes from the caller because its text is still
     * put through the vendored encoding fix-up that lives on the legacy side.
     */
    public static function quarantineReleaser(ReleaseNotice $notice): QuarantineReleaser
    {
        if (\defined('QUARANTINE_USE_SENDMAIL') && true === QUARANTINE_USE_SENDMAIL) {
            return new SendmailQuarantineReleaser(
                new SymfonyProcessRunner(),
                $notice,
                \defined('QUARANTINE_SENDMAIL_PATH') ? (string)QUARANTINE_SENDMAIL_PATH : '/usr/sbin/sendmail',
            );
        }

        return new SmtpQuarantineReleaser(self::mailTransport(), $notice);
    }

    /**
     * The submission server every outgoing MailWatch message goes through.
     *
     * Shared deliberately: the quarantine report and the password reset still
     * open their own PEAR connection to the same host, and this is what they
     * will use when their own slices are extracted.
     */
    public static function mailTransport(): TransportInterface
    {
        $transport = new EsmtpTransport(
            \defined('MAILWATCH_MAIL_HOST') ? (string)MAILWATCH_MAIL_HOST : 'localhost',
            \defined('MAILWATCH_MAIL_PORT') ? (int)MAILWATCH_MAIL_PORT : 25,
        );

        if (\defined('MAILWATCH_SMTP_HOSTNAME')) {
            $transport->setLocalDomain((string)MAILWATCH_SMTP_HOSTNAME);
        }

        return $transport;
    }

    /**
     * The SpamAssassin learner, with the paths and the size limit the
     * installation configured. A limit is only passed on when it is a
     * meaningful one, which is the condition the page script applied.
     */
    public static function quarantineLearner(): SpamLearner
    {
        return new CommandLineSpamLearner(
            new SymfonyProcessRunner(),
            \defined('SA_DIR') ? (string)SA_DIR : '',
            \defined('SA_PREFS') ? (string)SA_PREFS : '',
            \defined('SA_MAXSIZE') && SA_MAXSIZE >= 0 ? (int)SA_MAXSIZE : null,
        );
    }

    /**
     * The scope of maillog rows the signed-in account may act on.
     *
     * The session list was escaped on the way in, for the SQL fragment the
     * login used to build; the escaping is undone here so that a bound
     * parameter carries the address itself.
     *
     * @param array<string, mixed> $session
     */
    public static function quarantineMessageScope(array $session): MessageScope
    {
        $filters = [];
        foreach ((array)($session['global_array'] ?? []) as $filter) {
            if (\is_string($filter)) {
                $filters[] = stripslashes($filter);
            }
        }

        return MessageScope::forAccount(
            stripslashes((string)($session['myusername'] ?? '')),
            (string)($session['user_type'] ?? ''),
            $filters,
            \defined('FILTER_TO_ONLY') && true === FILTER_TO_ONLY,
        );
    }

    public static function quarantineAccess(string $role): QuarantineAccess
    {
        return new QuarantineAccess(
            $role,
            \defined('DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS')
                && true === DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS,
            \defined('DOMAINADMIN_CAN_RELEASE_DANGEROUS_CONTENTS')
                && true === DOMAINADMIN_CAN_RELEASE_DANGEROUS_CONTENTS,
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
     * The connection is shared by the migrator and the DBAL gateways. Legacy
     * page scripts still use Database while their own slices are extracted.
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
}
