<?php

namespace App\Tests\Api;

use PHPUnit\Framework\TestCase;

final class LogMailEndpointTest extends TestCase
{
    private const API_KEY = 'characterisation-test-api-key';

    /** @var resource|null */
    private static $serverProcess;

    private static string $temporaryDirectory;
    private static string $baseUrl;
    private static string $capturePath;
    private static string $serverLogPath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$temporaryDirectory = sys_get_temp_dir() . '/mailwatch-logmail-' . bin2hex(random_bytes(8));
        $mailScannerDirectory = self::$temporaryDirectory . '/mailscanner';
        $publicDirectory = self::$temporaryDirectory . '/public_html';

        if (!mkdir($mailScannerDirectory, 0o700, true) && !is_dir($mailScannerDirectory)) {
            throw new \RuntimeException('Unable to create the logmail test directory');
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
        file_put_contents($mailScannerDirectory . '/index.php', "<?php\necho 'dispatched-home-page';\n");
        file_put_contents($mailScannerDirectory . '/login.php', "<?php\necho 'dispatched-login-page';\n");
        file_put_contents($publicDirectory . '/.htaccess', "private-server-configuration\n");
        file_put_contents($publicDirectory . '/style.css', "/* directly-served-static-file */\n");

        self::$capturePath = self::$temporaryDirectory . '/insert.json';
        file_put_contents(
            $mailScannerDirectory . '/conf.php',
            sprintf(
                "<?php\ndefine('API_KEY', %s);\ndefine('API_MAX_PAYLOAD_BYTES', 4096);\ndefine('TEST_CAPTURE_PATH', %s);\ndefine('DB_HOST', 'test');\ndefine('DB_USER', 'test');\ndefine('DB_PASS', 'test');\ndefine('DB_NAME', 'test');\ndefine('DB_PORT', 3306);\n",
                var_export(self::API_KEY, true),
                var_export(self::$capturePath, true)
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
        self::$serverLogPath = self::$temporaryDirectory . '/server.log';
        // error_log= keeps the endpoint's log on stderr, captured below; a
        // php.ini that sets a path would swallow what the assertions read.
        self::$serverProcess = proc_open(
            [PHP_BINARY, '-d', 'error_log=', '-S', "127.0.0.1:{$port}", '-t', $publicDirectory, $publicDirectory . '/index.php'],
            [
                0 => ['pipe', 'r'],
                1 => ['file', self::$serverLogPath, 'a'],
                2 => ['file', self::$serverLogPath, 'a'],
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

    public function testItRejectsMethodsOtherThanPost(): void
    {
        $response = $this->request('GET');

        self::assertSame(405, $response['status']);
        self::assertSame(['error' => 'Method Not Allowed'], $response['json']);
    }

    public function testTheFrontControllerRejectsAnUnknownRoute(): void
    {
        $response = $this->request('GET', path: '/api/not-found');

        self::assertSame(404, $response['status']);
        self::assertSame('mailwatch.api.error.v1', $response['json']['contract']);
        self::assertSame('route_not_found', $response['json']['error']['code']);
    }

    public function testTheFrontControllerDispatchesAnAllowlistedPage(): void
    {
        $response = $this->rawRequest('GET', '/login.php');

        self::assertSame(200, $response['status']);
        self::assertSame('dispatched-login-page', $response['body']);
    }

    public function testThePublicIndexPathIsDispatchedAsTheHomePage(): void
    {
        $response = $this->rawRequest('GET', '/index.php');

        self::assertSame(200, $response['status']);
        self::assertSame('dispatched-home-page', $response['body']);
    }

    public function testTheDevelopmentServerServesExistingStaticFilesDirectly(): void
    {
        $response = $this->rawRequest('GET', '/style.css');

        self::assertSame(200, $response['status']);
        self::assertSame("/* directly-served-static-file */\n", $response['body']);
    }

    public function testTheDevelopmentServerDoesNotServeHiddenFiles(): void
    {
        $response = $this->request('GET', path: '/.htaccess');

        self::assertSame(404, $response['status']);
        self::assertSame('route_not_found', $response['json']['error']['code']);
    }

    public function testItRejectsRequestsWithoutAnApiKey(): void
    {
        $response = $this->request('POST', '{}');

        self::assertSame(401, $response['status']);
        self::assertSame(['error' => 'Unauthorized'], $response['json']);
    }

    public function testItRejectsAnIncorrectApiKey(): void
    {
        $response = $this->request('POST', '{}', 'incorrect-api-key');

        self::assertSame(401, $response['status']);
        self::assertSame(['error' => 'Unauthorized'], $response['json']);
    }

    public function testItRejectsMalformedJson(): void
    {
        $response = $this->request('POST', '{', self::API_KEY);

        self::assertSame(400, $response['status']);
        self::assertSame(['error' => 'Invalid JSON'], $response['json']);
    }

    public function testItRejectsValidJsonWithoutRequiredMessageData(): void
    {
        $response = $this->request('POST', '{}', self::API_KEY);

        self::assertSame(400, $response['status']);
        self::assertSame(['error' => 'Invalid data'], $response['json']);
    }

    public function testItRejectsAnOversizedPayload(): void
    {
        $response = $this->request(
            'POST',
            json_encode(['id' => 'oversized', 'future_field' => str_repeat('x', 5000)], JSON_THROW_ON_ERROR),
            self::API_KEY
        );

        self::assertSame(413, $response['status']);
        self::assertSame(['error' => 'Payload Too Large'], $response['json']);
    }

    public function testItRejectsNestedDataInAKnownScalarField(): void
    {
        $fixture = $this->fixturePayload();
        $fixture['subject'] = ['unexpected' => 'nested value'];

        $response = $this->request('POST', json_encode($fixture, JSON_THROW_ON_ERROR), self::API_KEY);

        self::assertSame(400, $response['status']);
        self::assertSame(['error' => 'Invalid data'], $response['json']);
    }

    public function testItToleratesUnknownStructuredFields(): void
    {
        $fixture = $this->fixturePayload();
        $fixture['future_extension'] = ['version' => 2, 'values' => ['one', 'two']];

        $response = $this->request('POST', json_encode($fixture, JSON_THROW_ON_ERROR), self::API_KEY);

        self::assertSame(201, $response['status']);
    }

    public function testItNormalizesHistoricalScalarRepresentationsWithoutRejectingEmptyMailFields(): void
    {
        $fixture = $this->fixturePayload();
        $fixture['size'] = '0';
        $fixture['from'] = '';
        $fixture['subject'] = '';
        $fixture['clientip'] = '';
        $fixture['isspam'] = 'Y';
        $fixture['ishigh'] = 'false';
        $fixture['nameinfected'] = '12';
        $fixture['sascore'] = '6.50';
        $fixture['mcpsascore'] = '-1.25';

        $response = $this->request('POST', json_encode($fixture, JSON_THROW_ON_ERROR), self::API_KEY);

        self::assertSame(201, $response['status']);
        $insert = $this->capturedInsert();
        self::assertSame(0, $insert['size']);
        self::assertSame('', $insert['from']);
        self::assertSame('', $insert['subject']);
        self::assertSame('', $insert['clientip']);
        self::assertSame(1, $insert['isspam']);
        self::assertSame(0, $insert['ishigh']);
        self::assertSame(12, $insert['nameinfected']);
        self::assertSame(6.5, $insert['sascore']);
        self::assertSame(-1.25, $insert['mcpsascore']);
    }

    public function testCompatibilityObservationsAreLoggedWithoutTheReceivedValue(): void
    {
        $fixture = $this->fixturePayload();
        $fixture['sascore'] = 'sensitive-invalid-score';

        $response = $this->request('POST', json_encode($fixture, JSON_THROW_ON_ERROR), self::API_KEY);

        self::assertSame(201, $response['status']);
        self::assertSame(0.0, (float)$this->capturedInsert()['sascore']);
        $serverLog = (string)file_get_contents(self::$serverLogPath);
        self::assertStringContainsString('sascore contained a non-numeric scalar', $serverLog);
        self::assertStringNotContainsString('sensitive-invalid-score', $serverLog);
    }

    public function testItAcceptsTheCurrentPerlPayloadAndMapsLegacyListNames(): void
    {
        $fixture = file_get_contents(dirname(__DIR__) . '/fixtures/api/logmail-v1.json');
        self::assertNotFalse($fixture);

        $response = $this->request('POST', $fixture, self::API_KEY);

        self::assertSame(201, $response['status']);
        self::assertSame(['success' => 'Data inserted successfully'], $response['json']);

        $insert = $this->capturedInsert();
        self::assertSame(1, $insert['spamallowlisted']);
        self::assertSame(0, $insert['spamblocklisted']);
        self::assertSame(0, $insert['mcpallowlisted']);
        self::assertSame(1, $insert['mcpblocklisted']);
        self::assertSame('fixture-message-001', $insert['id']);
        self::assertSame('<fixture-message-001@example.test>', $insert['messageid']);
    }

    public function testItStoresTheReportedInstantAsUtcAndKeepsTheSendersCalendarDay(): void
    {
        $fixture = file_get_contents(dirname(__DIR__) . '/fixtures/api/logmail-v1.json');
        self::assertNotFalse($fixture);

        $response = $this->request('POST', $fixture, self::API_KEY);

        self::assertSame(201, $response['status']);
        $insert = $this->capturedInsert();
        // The fixture reports 12:34:56+02:00.
        self::assertSame('2026-08-01 10:34:56', $insert['timestamp']);
        self::assertSame('2026-08-01', $insert['date']);
        self::assertSame('12:34:56', $insert['time']);
    }

    public function testItReadsTheCalendarDayFromTheOffsetRatherThanTheServerZone(): void
    {
        $fixture = $this->fixturePayload();
        // Just after midnight in Auckland is still the previous day in UTC.
        $fixture['timestamp'] = '2026-08-02T00:30:00+12:00';

        $response = $this->request('POST', json_encode($fixture, JSON_THROW_ON_ERROR), self::API_KEY);

        self::assertSame(201, $response['status']);
        $insert = $this->capturedInsert();
        self::assertSame('2026-08-01 12:30:00', $insert['timestamp']);
        self::assertSame('2026-08-02', $insert['date']);
        self::assertSame('00:30:00', $insert['time']);
    }

    public function testItDiscardsATimestampThatCarriesNoOffset(): void
    {
        $fixture = $this->fixturePayload();
        $fixture['timestamp'] = '2026-08-01 12:34:56';

        $response = $this->request('POST', json_encode($fixture, JSON_THROW_ON_ERROR), self::API_KEY);

        self::assertSame(201, $response['status']);
        $insert = $this->capturedInsert();
        self::assertNull($insert['timestamp']);
        self::assertNull($insert['date']);
        self::assertNull($insert['time']);
        self::assertStringContainsString(
            'timestamp was not an ISO 8601 instant',
            (string)file_get_contents(self::$serverLogPath)
        );
    }

    public function testItIgnoresACalendarDaySuppliedAlongsideTheInstant(): void
    {
        $fixture = $this->fixturePayload();
        $fixture['date'] = '1999-12-31';
        $fixture['time'] = '23:59:59';

        $response = $this->request('POST', json_encode($fixture, JSON_THROW_ON_ERROR), self::API_KEY);

        self::assertSame(201, $response['status']);
        $insert = $this->capturedInsert();
        self::assertSame('2026-08-01', $insert['date']);
        self::assertSame('12:34:56', $insert['time']);
    }

    public function testCanonicalListNamesTakePrecedenceOverLegacyNames(): void
    {
        $fixture = json_decode(
            (string)file_get_contents(dirname(__DIR__) . '/fixtures/api/logmail-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $fixture['spamallowlisted'] = 0;
        $fixture['spamblocklisted'] = 1;
        $fixture['mcpallowlisted'] = 1;
        $fixture['mcpblocklisted'] = 0;

        $response = $this->request('POST', json_encode($fixture, JSON_THROW_ON_ERROR), self::API_KEY);

        self::assertSame(201, $response['status']);
        $insert = $this->capturedInsert();
        self::assertSame(0, $insert['spamallowlisted']);
        self::assertSame(1, $insert['spamblocklisted']);
        self::assertSame(1, $insert['mcpallowlisted']);
        self::assertSame(0, $insert['mcpblocklisted']);
    }

    public function testItRejectsAnIdempotencyKeyThatDoesNotMatchThePayload(): void
    {
        $fixture = file_get_contents(dirname(__DIR__) . '/fixtures/api/logmail-v1.json');
        self::assertNotFalse($fixture);

        $response = $this->request('POST', $fixture, self::API_KEY, str_repeat('0', 64));

        self::assertSame(400, $response['status']);
        self::assertSame(['error' => 'Invalid idempotency key'], $response['json']);
    }

    public function testItReturnsSuccessWithoutASecondInsertForADuplicateRequest(): void
    {
        $fixture = file_get_contents(dirname(__DIR__) . '/fixtures/api/logmail-v1.json');
        self::assertNotFalse($fixture);
        $payload = json_decode($fixture, true, 512, JSON_THROW_ON_ERROR);
        $idempotencyKey = hash(
            'sha256',
            $payload['hostname'] . "\0" . $payload['id'] . "\0" . $payload['token']
        );

        $firstResponse = $this->request('POST', $fixture, self::API_KEY, $idempotencyKey);
        self::assertSame(201, $firstResponse['status']);
        self::assertSame($idempotencyKey, $this->capturedInsert()['ingestion_id']);

        $duplicateResponse = $this->request('POST', $fixture, self::API_KEY, $idempotencyKey);
        self::assertSame(200, $duplicateResponse['status']);
        self::assertSame(
            ['success' => 'Data already inserted', 'duplicate' => true],
            $duplicateResponse['json']
        );
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    private function request(
        string $method,
        string $body = '',
        ?string $apiKey = null,
        ?string $idempotencyKey = null,
        string $path = '/api/messages',
    ): array {
        $headers = ['Content-Type: application/json'];
        if (null !== $apiKey) {
            $headers[] = 'X-MailWatch-API-Key: ' . $apiKey;
        }
        if (null !== $idempotencyKey) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $response = $this->rawRequest($method, $path, $headers, $body);

        try {
            $json = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Endpoint returned invalid JSON: ' . $response['body'], 0, $exception);
        }

        return [
            'status' => $response['status'],
            'json' => $json,
        ];
    }

    /**
     * @param list<string> $headers
     *
     * @return array{status: int, body: string}
     */
    private function rawRequest(string $method, string $path, array $headers = [], string $body = ''): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);
        $responseBody = file_get_contents(self::$baseUrl . $path, false, $context);
        self::assertNotFalse($responseBody);

        $responseHeaders = $http_response_header ?? [];
        self::assertNotEmpty($responseHeaders);
        self::assertMatchesRegularExpression('/^HTTP\/\S+ (\d{3})/', $responseHeaders[0]);
        preg_match('/^HTTP\/\S+ (\d{3})/', $responseHeaders[0], $matches);

        return [
            'status' => (int)$matches[1],
            'body' => $responseBody,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function capturedInsert(): array
    {
        return json_decode(
            (string)file_get_contents(self::$capturePath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fixturePayload(): array
    {
        return json_decode(
            (string)file_get_contents(dirname(__DIR__) . '/fixtures/api/logmail-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
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
                        public function prepare(string $query): object
                        {
                            return new class {
                                private array $values = [];

                                public function bind_param(string $types, &...$values): void
                                {
                                    if ('ssisssssssiiiiiidsiiisiiiiidsssssissss' !== $types) {
                                        throw new LogicException('Unexpected bind parameter types: ' . $types);
                                    }
                                    if (strlen($types) !== count($values)) {
                                        throw new LogicException('Bind parameter count does not match its type declaration');
                                    }
                                    $this->values = &$values;
                                }

                                public function execute(): bool
                                {
                                    $fields = [
                                        'timestamp', 'id', 'size', 'from', 'from_domain', 'to', 'to_domain', 'subject',
                                        'clientip', 'archiveplaces', 'isspam', 'ishigh', 'issaspam', 'isrblspam',
                                        'spamallowlisted', 'spamblocklisted', 'sascore', 'spamreport', 'virusinfected',
                                        'nameinfected', 'otherinfected', 'reports', 'ismcp', 'ishighmcp', 'issamcp',
                                        'mcpallowlisted', 'mcpblocklisted', 'mcpsascore', 'mcpreport', 'hostname',
                                        'date', 'time', 'headers', 'quarantined', 'rblspamreport', 'token', 'messageid',
                                        'ingestion_id',
                                    ];
                                    $insert = array_combine($fields, $this->values);
                                    if (null !== $insert['ingestion_id'] && is_file(TEST_CAPTURE_PATH)) {
                                        $existing = json_decode(file_get_contents(TEST_CAPTURE_PATH), true, 512, JSON_THROW_ON_ERROR);
                                        if ($insert['ingestion_id'] === ($existing['ingestion_id'] ?? null)) {
                                            throw new mysqli_sql_exception('Duplicate entry', 1062);
                                        }
                                    }
                                    file_put_contents(TEST_CAPTURE_PATH, json_encode($insert, JSON_THROW_ON_ERROR));

                                    return true;
                                }

                                public function close(): void
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
