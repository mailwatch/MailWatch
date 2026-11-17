<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../mailscanner/lib/xmlrpc/xmlrpc.inc';

/**
 * Every method of rpcserver.php, called by the XML-RPC client the other nodes use, against a stand-in functions.php.
 */
class RpcServerTest extends TestCase
{
    const MESSAGE = '1A2B3C4D5E.A1B2C';
    const REMOTE = '2B3C4D5E6F.B2C3D';
    const UNKNOWN = '3C4D5E6F7A.C3D4E';

    private $dir;
    private $server;
    private $port;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mailwatch_rpc_' . bin2hex(random_bytes(4));
        foreach (['mailscanner', 'quarantine/20261111/spam', 'etc', 'lib/wrapper', 'sa'] as $directory) {
            mkdir($this->dir . '/' . $directory, 0777, true);
        }
        copy(__DIR__ . '/../../mailscanner/rpcserver.php', $this->dir . '/mailscanner/rpcserver.php');
        $this->writeFunctions();

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $this->server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, '-t', $this->dir],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        for ($i = 0; $i < 50; ++$i) {
            $connection = @fsockopen('127.0.0.1', $this->port);
            if (false !== $connection) {
                fclose($connection);
                break;
            }
            usleep(100000);
        }
    }

    protected function tearDown(): void
    {
        proc_terminate($this->server);
        proc_close($this->server);
        exec('chmod -R u+rwx ' . escapeshellarg($this->dir) . '; rm -rf ' . escapeshellarg($this->dir));
    }

    public function testReturnsAQuarantinedFile()
    {
        $content = "From: a@example.com\nSubject: \xc3\xa8\n\n\x00\xff body\n";
        file_put_contents($this->dir . '/quarantine/20261111/spam/' . self::MESSAGE, $content);

        // the client decodes the base64 type, the server encoded the file once more
        $this->assertSame($content, base64_decode($this->call('return_quarantined_file', [self::MESSAGE]), true));
    }

    public function testReturnsAMessageStoredInItsOwnDirectory()
    {
        mkdir($this->dir . '/quarantine/20261111/' . self::MESSAGE);
        file_put_contents($this->dir . '/quarantine/20261111/' . self::MESSAGE . '/message', 'whole message');

        $this->assertSame('whole message', base64_decode($this->call('return_quarantined_file', [self::MESSAGE]), true));
    }

    public function testRejectsAnIdThatIsNotAMessageId()
    {
        $this->assertFault('dievalidate99', 'return_quarantined_file', ['../../etc/passwd']);
    }

    public function testRejectsAMessageThatIsNotInTheQuarantine()
    {
        $this->assertFault('paratype160 NULL paratype260', 'return_quarantined_file', [self::MESSAGE]);
    }

    public function testRejectsAQuarantinePathThatIsNotAFile()
    {
        mkdir($this->dir . '/quarantine/20261111/spam/' . self::MESSAGE);

        $this->assertFault('/20261111/spam/' . self::MESSAGE . ' notfile60', 'return_quarantined_file', [self::MESSAGE]);
    }

    public function testRejectsAnUnreadableFile()
    {
        if (function_exists('posix_geteuid') && 0 === posix_geteuid()) {
            $this->markTestSkipped('root reads every file');
        }
        $file = $this->dir . '/quarantine/20261111/spam/' . self::MESSAGE;
        file_put_contents($file, 'secret');
        chmod($file, 0000);

        $this->assertFault('permdenied60', 'return_quarantined_file', [self::MESSAGE]);
    }

    public function testListsTheItemsOfAMessage()
    {
        $this->assertSame([
            ['id' => '0', 'file' => 'message', 'path' => '/q/message'],
            ['id' => '1', 'file' => 'virus.exe'],
        ], $this->call('quarantine_list_items', [self::MESSAGE]));
    }

    public function badListItems()
    {
        return [
            'not a message id' => ['../x', 'dievalidate99'],
            'unknown message, no plain text exit' => [self::UNKNOWN, 'dievalidate99'],
            'error from the node holding it' => [self::REMOTE, 'XML-RPC Error: node3 is down'],
        ];
    }

    /**
     * @dataProvider badListItems
     */
    public function testListItemsFaults($id, $message)
    {
        $this->assertFault($message, 'quarantine_list_items', [$id]);
    }

    public function testReleasesLearnsAndDeletesWithTheArgumentsSent()
    {
        $items = [['id' => '0', 'path' => '/q/message', 'to' => 'a@example.com']];

        $this->assertSame('released', $this->call('quarantine_release', [$items, ['0'], 'b@example.com']));
        $this->assertSame(['quarantine_release', $items, ['0'], 'b@example.com'], $this->lastOperation());
        $this->assertSame('learned', $this->call('quarantine_learn', [$items, ['0'], 'spam']));
        $this->assertSame(['quarantine_learn', $items, ['0'], 'spam'], $this->lastOperation());
        $this->assertSame('deleted', $this->call('quarantine_delete', [$items, ['0']]));
        $this->assertSame(['quarantine_delete', $items, ['0']], $this->lastOperation());
    }

    public function callsTheSignatureRejects()
    {
        return [
            'release without recipient' => ['quarantine_release', [[], []]],
            'release with a string list' => ['quarantine_release', ['items', ['0'], 'b@example.com']],
            'delete with too many arguments' => ['quarantine_delete', [[], [], 'x']],
            'list items with an array' => ['quarantine_list_items', [[self::MESSAGE]]],
            'quarantined file without id' => ['return_quarantined_file', []],
            'conf var with a number' => ['get_conf_var', [7]],
        ];
    }

    /**
     * @dataProvider callsTheSignatureRejects
     */
    public function testTheSignatureRejectsWrongArguments($method, array $parameters)
    {
        $this->assertFault('ncorrect parameters', $method, $parameters);
        $this->assertFileDoesNotExist($this->dir . '/operation.json');
    }

    public function testUnknownMethod()
    {
        $this->assertFault('nknown method', 'system_shutdown', []);
    }

    public function testMalformedRequest()
    {
        $response = file_get_contents(
            'http://127.0.0.1:' . $this->port . '/mailscanner/rpcserver.php',
            false,
            stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: text/xml', 'content' => '<methodCall><methodName>', 'ignore_errors' => true]])
        );

        $this->assertStringContainsString('<name>faultCode</name>', $response);
    }

    public function testRefusesAClientThatIsNotAllowed()
    {
        touch($this->dir . '/deny');

        $this->assertFault('client160 127.0.0.1 client260', 'quarantine_list_items', [self::MESSAGE]);
    }

    public function testSophosStatus()
    {
        $this->assertSame('', $this->call('sophos_status', []), 'no wrapper');

        file_put_contents($this->dir . '/lib/wrapper/sophos-wrapper', "#!/bin/sh\necho \"Sophos 1.2 \$1 \$2\"\n");
        chmod($this->dir . '/lib/wrapper/sophos-wrapper', 0755);
        $this->assertSame("Sophos 1.2 /usr/local/Sophos -v\n", $this->call('sophos_status', []));
    }

    public function testConfVar()
    {
        $this->assertSame('/var/spool/MailScanner/quarantine', $this->call('get_conf_var', ['QuarantineDir']));
        $this->assertSame('', $this->call('get_conf_var', ['Nonexistent']));
    }

    public function testDumpsTheMailScannerConfigurationExpandingVariables()
    {
        file_put_contents($this->dir . '/etc/MailScanner.conf', implode("\n", [
            '# a comment = ignored',
            '%org-name% = example',
            '%report-dir% = /usr/share/MailScanner/reports/en',
            'Hostname = mail.%org-name%.com # trailing comment',
            'Sign Messages Already Processed = no',
            'Deleted Bad Content Message Report = %report-dir%/deleted.content.message.txt',
            'Unknown = %not-defined%',
        ]));

        $this->assertSame([
            '%org-name%' => 'example',
            '%report-dir%' => '/usr/share/MailScanner/reports/en',
            'Hostname' => 'mail.example.com ',
            'Sign Messages Already Processed' => 'no',
            'Deleted Bad Content Message Report' => '/usr/share/MailScanner/reports/en/deleted.content.message.txt',
            'Unknown' => '%not-defined%',
        ], $this->call('dump_mailscanner_conf', []));
    }

    public function testDumpWithoutAConfigurationFile()
    {
        $this->assertFault('MailScanner.conf notfile60', 'dump_mailscanner_conf', []);
    }

    public function testBayesInfo()
    {
        $this->assertSame([], $this->call('get_bayes_info', []), 'no sa-learn');

        file_put_contents($this->dir . '/sa/sa-learn', implode("\n", [
            '#!/bin/sh',
            'echo "0.000          0          3          0  non-token data: bayes db version"',
            'echo "0.000          0       1234          0  non-token data: nspam"',
            'echo "0.000          0         56          0  non-token data: nham"',
            'echo "0.000          0     150000          0  non-token data: ntokens"',
            'echo "0.000          0          0          0  non-token data: last expire reduction count"',
        ]) . "\n");
        chmod($this->dir . '/sa/sa-learn', 0755);

        $this->assertSame([
            'Number of Spam Messages:' => '1,234',
            'Number of Ham Messages:' => '56',
            'Number of Tokens:' => '150,000',
            'Last Expiry Reduction Count:' => '0',
        ], $this->call('get_bayes_info', []));
    }

    public function testPostfixQueues()
    {
        $this->assertSame(['inq' => '3', 'outq' => '4'], $this->call('postfix_queues', []));
    }

    private function send($method, array $parameters)
    {
        $values = [];
        foreach ($parameters as $parameter) {
            $values[] = php_xmlrpc_encode($parameter);
        }
        $client = new \xmlrpc_client('/mailscanner/rpcserver.php', '127.0.0.1', $this->port);

        return $client->send(new \xmlrpcmsg($method, $values), 10);
    }

    private function call($method, array $parameters)
    {
        $response = $this->send($method, $parameters);
        $this->assertSame(0, $response->faultCode(), $response->faultString());

        return php_xmlrpc_decode($response->value());
    }

    private function assertFault($message, $method, array $parameters)
    {
        $response = $this->send($method, $parameters);
        $this->assertNotSame(0, $response->faultCode(), 'no fault');
        $this->assertStringContainsString($message, $response->faultString());
    }

    private function lastOperation()
    {
        return json_decode(file_get_contents($this->dir . '/operation.json'), true);
    }

    /**
     * Stand-in for mailscanner/functions.php: the real XML-RPC library and fakes of what rpcserver.php calls.
     */
    private function writeFunctions()
    {
        $lib = var_export(realpath(__DIR__ . '/../../mailscanner/lib/xmlrpc'), true);
        $dir = var_export($this->dir, true);
        $known = var_export([self::MESSAGE, self::REMOTE], true);
        file_put_contents($this->dir . '/mailscanner/functions.php', <<<PHP
<?php
define('MEMORY_LIMIT', '128M');
define('MS_LIB_DIR', $dir . '/lib/');
define('MS_CONFIG_DIR', $dir . '/etc/');
define('SA_DIR', $dir . '/sa/');
define('SA_PREFS', '/etc/MailScanner/spamassassin.conf');
require_once $lib . '/xmlrpc.inc';
require_once $lib . '/xmlrpcs.inc';
function mailwatch_load_xmlrpc(\$server = false) {}
function get_conf_var(\$name, \$force = false) {
    if ('QuarantineDir' === \$name && \$force) { return $dir . '/quarantine'; }
    \$values = ['QuarantineDir' => '/var/spool/MailScanner/quarantine'];
    return isset(\$values[\$name]) ? \$values[\$name] : false;
}
function dbconn() {}
function dbquery(\$sql) {
    foreach ($known as \$id) {
        if (false !== strpos(\$sql, "id='\$id'")) { return (object)['num_rows' => 1]; }
    }
    return (object)['num_rows' => 0];
}
function safe_value(\$value) { return \$value; }
function validateInput(\$input, \$type) { return 1 === preg_match('/^[A-F0-9]{10}\\.[A-F0-9]{5}\$/', \$input); }
function __(\$string) { return \$string; }
function is_rpc_client_allowed() { return !file_exists($dir . '/deny'); }
function quarantine_list_items(\$id) {
    if ('2B3C4D5E6F.B2C3D' === \$id) { return 'XML-RPC Error: node3 is down'; }
    return [['id' => 0, 'file' => 'message', 'path' => '/q/message'], ['id' => 1, 'file' => 'virus.exe']];
}
function record(array \$operation, \$answer) { file_put_contents($dir . '/operation.json', json_encode(\$operation)); return \$answer; }
function quarantine_release(\$items, \$num, \$to) { return record(['quarantine_release', \$items, \$num, \$to], 'released'); }
function quarantine_learn(\$items, \$num, \$type) { return record(['quarantine_learn', \$items, \$num, \$type], 'learned'); }
function quarantine_delete(\$items, \$num) { return record(['quarantine_delete', \$items, \$num], 'deleted'); }
function postfixinq() { return 3; }
function postfixallq() { return 7; }
class database { public static function mysqli_result(\$result, \$row) { return '20261111'; } }
PHP
        );
    }
}
