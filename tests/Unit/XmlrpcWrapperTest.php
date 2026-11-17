<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The certificate checks of xmlrpc_wrapper() follow RPC_VERIFY_PEER, and upgrade.php reports the constant when missing.
 */
class XmlrpcWrapperTest extends TestCase
{
    public function verifyCases()
    {
        return [
            'not defined' => ['', [0, 0]],
            'false' => ["define('RPC_VERIFY_PEER', false);", [0, 0]],
            'true' => ["define('RPC_VERIFY_PEER', true);", [1, 2]],
            'not a boolean' => ["define('RPC_VERIFY_PEER', 'yes');", [0, 0]],
        ];
    }

    /**
     * @dataProvider verifyCases
     */
    public function testCertificateChecksFollowRpcVerifyPeer($define, array $expected)
    {
        $result = $this->runPhp(
            "define('RPC_RELATIVE_PATH', ''); define('DEBUG', false); define('RPC_SSL', true); $define\n"
            . "class xmlrpc_client {\n"
            . "    public \$peer; public \$host;\n"
            . "    public function __construct(\$path, \$server, \$port) {}\n"
            . "    public function setDebug(\$debug) {}\n"
            . "    public function setSSLVerifyPeer(\$value) { \$this->peer = \$value; }\n"
            . "    public function setSSLVerifyHost(\$value) { \$this->host = \$value; }\n"
            . "    public function send(\$msg, \$timeout, \$method) { return [\$this->peer, \$this->host]; }\n"
            . "}\n"
            . $this->functionSource('xmlrpc_wrapper')
            . "echo json_encode(xmlrpc_wrapper('node2.example.org', null));\n"
        );

        $this->assertSame($expected, $result);
    }

    public function testUpgradeReportsAMissingRpcVerifyPeer()
    {
        $missing = $this->runPhp($this->functionSource('checkConfVariables') . "echo json_encode(checkConfVariables());\n");
        $present = $this->runPhp("define('RPC_VERIFY_PEER', false);\n" . $this->functionSource('checkConfVariables') . "echo json_encode(checkConfVariables());\n");

        $this->assertArrayHasKey('RPC_VERIFY_PEER', $missing['optional']['list']);
        $this->assertStringContainsString('next release', $missing['optional']['list']['RPC_VERIFY_PEER']['description']);
        $this->assertNotContains('RPC_VERIFY_PEER', $missing['needed']['list'], 'a missing entry must not stop the pages');
        $this->assertArrayNotHasKey('RPC_VERIFY_PEER', $present['optional']['list']);
    }

    public function testConfExampleDefinesItAsFalse()
    {
        $example = file_get_contents(__DIR__ . '/../../mailscanner/conf.php.example');

        $this->assertStringContainsString("define('RPC_VERIFY_PEER', false);", $example);
        $this->assertStringContainsString('becomes true in the next release', $example);
    }

    private function functionSource($name)
    {
        $source = file_get_contents(__DIR__ . '/../../mailscanner/functions.php');
        $this->assertSame(1, preg_match('/^function ' . $name . '\(.*?^}$/ms', $source, $match));

        return $match[0] . "\n";
    }

    /**
     * Runs the code in a separate PHP, away from the XML-RPC classes other tests load.
     */
    private function runPhp($code)
    {
        $file = tempnam(sys_get_temp_dir(), 'mailwatch_wrapper_');
        file_put_contents($file, "<?php\n" . $code);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file), $output, $status);
        unlink($file);
        $this->assertSame(0, $status, implode("\n", $output));

        return json_decode(implode("\n", $output), true);
    }
}
