<?php

namespace App\Tests\Tickets;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../mailscanner/conf_parser.inc.php';

/**
 * %var% defined in MailScanner.conf must be expanded in the conf.d files too.
 */
class Ticket1245Test extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mailwatch_1245_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/conf.d', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/conf.d/*'));
        rmdir($this->dir . '/conf.d');
        unlink($this->dir . '/MailScanner.conf');
        rmdir($this->dir);
    }

    public function testExpandsVariablesAcrossFiles()
    {
        // var names are unique per test because the parser keeps them for the whole request
        file_put_contents($this->dir . '/MailScanner.conf', "%t1-org% = ACME\n%t1-rules% = /etc/MailScanner/rules\n");
        file_put_contents($this->dir . '/conf.d/b.conf', "Subject Text = {%t1-org%} %t1-confd%\nRules = %t1-rules%/x.rules %t1-rules%/y.rules\n");
        file_put_contents($this->dir . '/conf.d/a.conf', "%t1-confd% = from-a\n");

        $conf = $this->parse();

        $this->assertSame('{ACME} from-a', trim($conf['Subject Text']));
        $this->assertSame('/etc/MailScanner/rules/x.rules /etc/MailScanner/rules/y.rules', trim($conf['Rules']));
    }

    public function testLeavesUndefinedVariablesUnchanged()
    {
        file_put_contents($this->dir . '/MailScanner.conf', "Max Children = 5\n");
        file_put_contents($this->dir . '/conf.d/a.conf', "Unknown = %t2-undefined%/file\n");

        $conf = $this->parse();

        $this->assertSame('%t2-undefined%/file', trim($conf['Unknown']));
    }

    private function parse()
    {
        return array_merge(
            parse_conf_file($this->dir . '/MailScanner.conf'),
            parse_conf_dir($this->dir . '/conf.d/')
        );
    }
}
