<?php

namespace App\Tests\Tickets;

use PHPUnit\Framework\TestCase;

/**
 * rpcserver.php serves the quarantine of its own host, also when DISTRIBUTED_SETUP is true.
 */
class Ticket1327RpcServerTest extends TestCase
{
    private $dir;
    private $server;
    private $port;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mailwatch_1327_rpc_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/mailscanner', 0777, true);
        mkdir($this->dir . '/quarantine/20261111/spam', 0777, true);
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
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testReturnsAQuarantinedFileInADistributedSetup()
    {
        $content = "From: a@example.com\nSubject: test\n\nbody\n";
        file_put_contents($this->dir . '/quarantine/20261111/spam/1A2B3C4D5E.A1B2C', $content);

        $response = $this->call('return_quarantined_file', '1A2B3C4D5E.A1B2C');

        // the server encodes the file and the base64 type encodes it once more
        $this->assertStringContainsString('<base64>' . base64_encode(base64_encode($content)) . '</base64>', $response);
    }

    public function testReturnsAFaultWhenTheFileIsNotInTheQuarantine()
    {
        $response = $this->call('return_quarantined_file', '1A2B3C4D5E.A1B2C');

        $this->assertStringContainsString('<name>faultCode</name>', $response);
        $this->assertStringContainsString('paratype160 NULL paratyper260', $response);
    }

    private function call($method, $argument)
    {
        $request = '<?xml version="1.0"?><methodCall><methodName>' . $method . '</methodName>'
            . '<params><param><value><string>' . $argument . '</string></value></param></params></methodCall>';
        $response = file_get_contents(
            'http://127.0.0.1:' . $this->port . '/mailscanner/rpcserver.php',
            false,
            stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: text/xml', 'content' => $request]])
        );
        $this->assertIsString($response);

        return $response;
    }

    /**
     * Stand-in for mailscanner/functions.php: the real XML-RPC library, and a get_conf_var() that answers like the
     * real one when DISTRIBUTED_SETUP is true.
     */
    private function writeFunctions()
    {
        $lib = var_export(realpath(__DIR__ . '/../../mailscanner/lib/xmlrpc'), true);
        $quarantine = var_export($this->dir . '/quarantine', true);
        file_put_contents($this->dir . '/mailscanner/functions.php', <<<PHP
<?php
define('MEMORY_LIMIT', '128M');
require_once $lib . '/xmlrpc.inc';
require_once $lib . '/xmlrpcs.inc';
function mailwatch_load_xmlrpc(\$server = false) {}
function get_conf_var(\$name, \$force = false) { return \$force ? $quarantine : false; }
function dbconn() {}
function dbquery(\$sql) { return null; }
function safe_value(\$value) { return \$value; }
function validateInput(\$input, \$type) { return true; }
function __(\$string) { return \$string; }
function is_rpc_client_allowed() { return true; }
class database { public static function mysqli_result(\$result, \$row) { return '20261111'; } }
PHP
        );
    }
}
