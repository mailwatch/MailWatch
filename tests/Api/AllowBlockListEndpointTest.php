<?php

namespace App\Tests\Api;

use PHPUnit\Framework\TestCase;

final class AllowBlockListEndpointTest extends TestCase
{
    private const API_KEY = 'snapshot-api-key';

    /** @var resource|null */
    private static $serverProcess;

    private static string $temporaryDirectory;
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$temporaryDirectory = sys_get_temp_dir() . '/mailwatch-list-snapshot-' . bin2hex(random_bytes(8));
        $mailScannerDirectory = self::$temporaryDirectory . '/mailscanner';
        $apiDirectory = $mailScannerDirectory . '/api';
        $publicDirectory = self::$temporaryDirectory . '/public_html';
        if (!mkdir($apiDirectory, 0o700, true) && !is_dir($apiDirectory)) {
            throw new \RuntimeException('Unable to create the list snapshot test directory');
        }
        if (!mkdir($publicDirectory, 0o700) && !is_dir($publicDirectory)) {
            throw new \RuntimeException('Unable to create the public test directory');
        }

        $projectRoot = dirname(__DIR__, 2);
        $vendorDirectory = self::$temporaryDirectory . '/vendor';
        if (!mkdir($vendorDirectory, 0o700) && !is_dir($vendorDirectory)) {
            throw new \RuntimeException('Unable to create the Composer test directory');
        }
        file_put_contents(
            $vendorDirectory . '/autoload.php',
            sprintf("<?php\nrequire %s;\n", var_export($projectRoot . '/vendor/autoload.php', true))
        );
        copy($projectRoot . '/public_html/index.php', $publicDirectory . '/index.php');
        copy($projectRoot . '/mailscanner/api/allow-block-list.php', $apiDirectory . '/allow-block-list.php');
        copy($projectRoot . '/mailscanner/api/MailWatchApi.php', $apiDirectory . '/MailWatchApi.php');
        copy($projectRoot . '/tests/fixtures/api/allow-block-list-v1.json', self::$temporaryDirectory . '/fixture.json');
        file_put_contents(
            $mailScannerDirectory . '/conf.php',
            sprintf(
                "<?php\ndefine('API_KEY', %s);\ndefine('API_MAX_SNAPSHOT_BYTES', 5242880);\ndefine('TEST_FIXTURE_PATH', %s);\ndefine('DB_HOST', 'test');\ndefine('DB_USER', 'test');\ndefine('DB_PASS', 'test');\ndefine('DB_NAME', 'test');\ndefine('DB_PORT', 3306);\n",
                var_export(self::API_KEY, true),
                var_export(self::$temporaryDirectory . '/fixture.json', true),
            )
        );
        file_put_contents($mailScannerDirectory . '/Database.php', self::databaseStub());

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

        $missingKeyResponse = $this->request('GET');
        self::assertSame(401, $missingKeyResponse['status']);

        $wrongKeyResponse = $this->request('GET', 'incorrect-api-key');
        self::assertSame(401, $wrongKeyResponse['status']);
    }

    public function testItRejectsAnUnsupportedContractVersion(): void
    {
        $response = $this->request('GET', self::API_KEY, null, '2');

        self::assertSame(406, $response['status']);
        self::assertSame('unsupported_contract_version', $response['json']['error']['code']);
    }

    public function testItReturnsTheCompleteEffectiveSnapshot(): void
    {
        $response = $this->request('GET', self::API_KEY);

        self::assertSame(200, $response['status']);
        self::assertSame('mailwatch.allow-block-list.v1', $response['json']['contract']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response['json']['snapshot_version']);
        self::assertSame('mailwatch.allow-block-list.v1', $response['headers']['x-mailwatch-contract']);
        self::assertSame('"' . $response['json']['snapshot_version'] . '"', $response['headers']['etag']);

        self::assertContains(
            ['to_address' => 'filtered.example.net', 'from_address' => 'filtered-sender@example.com'],
            $response['json']['allowlist'],
        );
        self::assertContains(
            ['to_address' => 'historically-inactive.example.net', 'from_address' => 'filtered-sender@example.com'],
            $response['json']['allowlist'],
            'the endpoint preserves the current join semantics, including inactive filters',
        );
        self::assertContains(
            ['to_address' => 'default', 'from_address' => 'blocked@example.com'],
            $response['json']['blocklist'],
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
        string $contractVersion = '1'
    ): array {
        $headers = ['X-MailWatch-Contract-Version: ' . $contractVersion];
        if (null !== $apiKey) {
            $headers[] = 'X-MailWatch-API-Key: ' . $apiKey;
        }
        if (null !== $etag) {
            $headers[] = 'If-None-Match: ' . $etag;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);
        $body = file_get_contents(self::$baseUrl . '/api/allow-block-list', false, $context);
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

    private static function databaseStub(): string
    {
        return <<<'PHP'
            <?php

            final class Database
            {
                public static function connect(...$arguments): object
                {
                    return new class {
                        public function query(string $query): object
                        {
                            $fixture = json_decode(file_get_contents(TEST_FIXTURE_PATH), true, 512, JSON_THROW_ON_ERROR);
                            $table = str_contains($query, 'FROM allowlist') ? 'allowlist' : 'blocklist';
                            $rows = $fixture[$table];
                            foreach ($fixture['user_filters'] as $filter) {
                                foreach ($fixture[$table] as $entry) {
                                    if (strtolower($entry['to_address']) === strtolower($filter['username'])) {
                                        $rows[] = [
                                            'to_address' => $filter['filter'],
                                            'from_address' => $entry['from_address'],
                                        ];
                                    }
                                }
                            }

                            return new class($rows) {
                                private int $offset = 0;

                                public function __construct(private array $rows)
                                {
                                }

                                public function fetch_assoc(): ?array
                                {
                                    return $this->rows[$this->offset++] ?? null;
                                }

                                public function free(): void
                                {
                                }
                            };
                        }

                        public function close(): void
                        {
                        }
                    };
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
