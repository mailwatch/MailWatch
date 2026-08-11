<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use MailWatch\Shared\Infrastructure\Database\RestApiSchema;
use PHPUnit\Framework\TestCase;

final class SpamSettingsEndpointTest extends TestCase
{
    private const API_KEY = 'spam-settings-api-key';

    /** @var resource|null */
    private static $serverProcess;

    private static string $temporaryDirectory;
    private static string $baseUrl;
    private static string $databasePath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$temporaryDirectory = sys_get_temp_dir() . '/mailwatch-spam-settings-' . bin2hex(random_bytes(8));
        $mailScannerDirectory = self::$temporaryDirectory . '/mailscanner';
        $publicDirectory = self::$temporaryDirectory . '/public_html';
        if (!mkdir($mailScannerDirectory, 0o700, true) && !is_dir($mailScannerDirectory)) {
            throw new \RuntimeException('Unable to create the spam settings test directory');
        }
        if (!mkdir($publicDirectory, 0o700) && !is_dir($publicDirectory)) {
            throw new \RuntimeException('Unable to create the public test directory');
        }

        $projectRoot = dirname(__DIR__, 2);
        $vendorDirectory = self::$temporaryDirectory . '/vendor';
        if (!mkdir($vendorDirectory, 0o700) && !is_dir($vendorDirectory)) {
            throw new \RuntimeException('Unable to create the Composer test directory');
        }
        $databaseConfigurationLoader = self::$temporaryDirectory . '/DatabaseConfigurationLoader.php';
        file_put_contents($databaseConfigurationLoader, self::databaseConfigurationLoaderStub());
        file_put_contents(
            $vendorDirectory . '/autoload.php',
            sprintf(
                "<?php\nrequire %s;\nrequire %s;\n",
                var_export($projectRoot . '/vendor/autoload.php', true),
                var_export($databaseConfigurationLoader, true)
            )
        );
        copy($projectRoot . '/public_html/index.php', $publicDirectory . '/index.php');
        self::$databasePath = self::$temporaryDirectory . '/mailwatch.sqlite';
        file_put_contents(
            $mailScannerDirectory . '/conf.php',
            sprintf(
                "<?php\ndefine('API_KEY', %s);\ndefine('API_MAX_SNAPSHOT_BYTES', 5242880);\ndefine('TEST_DATABASE_PATH', %s);\ndefine('DB_HOST', 'test');\ndefine('DB_USER', 'test');\ndefine('DB_PASS', 'test');\ndefine('DB_NAME', 'test');\ndefine('DB_PORT', 3306);\n",
                var_export(self::API_KEY, true),
                var_export(self::$databasePath, true),
            )
        );
        self::createDatabase();

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (false === $socket) {
            throw new \RuntimeException("Unable to allocate a test port: {$errorCode} {$errorMessage}");
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (false === $address) {
            throw new \RuntimeException('Unable to determine the allocated test port');
        }

        $port = (int)substr(strrchr($address, ':'), 1);
        self::$baseUrl = "http://127.0.0.1:{$port}";
        self::$serverProcess = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $publicDirectory, $publicDirectory . '/index.php'],
            [
                0 => ['pipe', 'r'],
                1 => ['file', self::$temporaryDirectory . '/server.log', 'a'],
                2 => ['file', self::$temporaryDirectory . '/server.log', 'a'],
            ],
            $pipes
        );
        if (!is_resource(self::$serverProcess)) {
            throw new \RuntimeException('Unable to start the PHP test server');
        }
        fclose($pipes[0]);
        self::waitForServer();
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
        self::removeDirectory(self::$temporaryDirectory);
        parent::tearDownAfterClass();
    }

    public function testItRequiresGetAndTheApiKey(): void
    {
        $methodResponse = $this->request('POST', self::API_KEY);
        self::assertSame(405, $methodResponse['status']);
        self::assertSame('method_not_allowed', $methodResponse['json']['error']['code']);

        self::assertSame(401, $this->request('GET')['status']);
        self::assertSame(401, $this->request('GET', 'incorrect-api-key')['status']);
    }

    public function testItRejectsAnUnsupportedContractVersion(): void
    {
        $response = $this->request('GET', self::API_KEY, null, '2');

        self::assertSame(406, $response['status']);
        self::assertSame('unsupported_contract_version', $response['json']['error']['code']);
    }

    public function testItReturnsOnlyTheEffectiveSpamSettings(): void
    {
        $response = $this->request('GET', self::API_KEY, requestId: 'spam-settings-request-123');

        self::assertSame(200, $response['status']);
        self::assertSame('mailwatch.spam-settings.v1', $response['json']['contract']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response['json']['snapshot_version']);
        self::assertSame('mailwatch.spam-settings.v1', $response['headers']['x-mailwatch-contract']);
        self::assertSame('spam-settings-request-123', $response['headers']['x-request-id']);
        self::assertSame('"' . $response['json']['snapshot_version'] . '"', $response['headers']['etag']);
        self::assertEquals(3.5, $response['json']['spam_scores']['recipient@example.com']);
        self::assertEquals(7.0, $response['json']['high_spam_scores']['recipient@example.com']);
        self::assertContains('recipient@example.com', $response['json']['no_scan']);
        self::assertArrayNotHasKey('zero@example.net', $response['json']['spam_scores']);
        self::assertArrayNotHasKey('negative@example.net', $response['json']['spam_scores']);
        self::assertNotContains('zero@example.net', $response['json']['no_scan']);
        self::assertNotContains('negative@example.net', $response['json']['no_scan']);
        self::assertSame(
            ['contract', 'generated_at', 'snapshot_version', 'spam_scores', 'high_spam_scores', 'no_scan'],
            array_keys($response['json']),
            'unrelated user data is not exposed',
        );
    }

    public function testItReturnsNotModifiedForTheCurrentEtag(): void
    {
        $first = $this->request('GET', self::API_KEY);
        $second = $this->request('GET', self::API_KEY, $first['headers']['etag']);

        self::assertSame(304, $second['status']);
        self::assertSame('', $second['body']);
        self::assertSame($first['headers']['etag'], $second['headers']['etag']);
    }

    /**
     * @return array{status: int, body: string, json: array<string, mixed>, headers: array<string, string>}
     */
    private function request(
        string $method,
        ?string $apiKey = null,
        ?string $etag = null,
        string $contractVersion = '1',
        ?string $requestId = null,
    ): array {
        $headers = ['X-MailWatch-Contract-Version: ' . $contractVersion];
        if (null !== $apiKey) {
            $headers[] = 'X-MailWatch-API-Key: ' . $apiKey;
        }
        if (null !== $etag) {
            $headers[] = 'If-None-Match: ' . $etag;
        }
        if (null !== $requestId) {
            $headers[] = 'X-Request-ID: ' . $requestId;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);
        $body = file_get_contents(self::$baseUrl . '/api/spam-settings', false, $context);
        self::assertNotFalse($body);
        $responseHeaders = $http_response_header ?? [];
        self::assertNotEmpty($responseHeaders);
        preg_match('/^HTTP\/\S+ (\d{3})/', $responseHeaders[0], $matches);

        $normalizedHeaders = [];
        foreach (array_slice($responseHeaders, 1) as $header) {
            if (!str_contains($header, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $header, 2);
            $normalizedHeaders[strtolower(trim($name))] = trim($value);
        }

        return [
            'status' => (int)$matches[1],
            'body' => $body,
            'json' => '' === $body ? [] : json_decode($body, true, 512, JSON_THROW_ON_ERROR),
            'headers' => $normalizedHeaders,
        ];
    }

    private static function waitForServer(): void
    {
        $deadline = microtime(true) + 5;
        do {
            $connection = @fsockopen('127.0.0.1', (int)parse_url(self::$baseUrl, PHP_URL_PORT));
            if (false !== $connection) {
                fclose($connection);

                return;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);

        throw new \RuntimeException('The PHP test server did not start');
    }

    private static function createDatabase(): void
    {
        $connection = self::databaseConnection();
        $schema = new Schema();
        RestApiSchema::define($schema);
        $connection->createSchemaManager()->createSchemaObjects($schema);
        $fixture = json_decode(
            (string)file_get_contents(dirname(__DIR__) . '/fixtures/api/spam-settings-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        foreach ($fixture['users'] as $user) {
            $connection->insert('users', $user);
        }
        $connection->close();
    }

    private static function databaseConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => self::$databasePath,
        ]);
    }

    private static function databaseConfigurationLoaderStub(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace MailWatch\Shared\Infrastructure\Database;

            final class DatabaseConfigurationLoader
            {
                public function load(): DatabaseConfiguration
                {
                    return DatabaseConfiguration::fromDsn('sqlite:///' . TEST_DATABASE_PATH);
                }
            }
            PHP;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
