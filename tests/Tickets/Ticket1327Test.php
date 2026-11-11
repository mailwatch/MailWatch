<?php

namespace App\Tests\Tickets;

use PHPUnit\Framework\TestCase;

/**
 * mailwatch_quarantine_maint.php works on the quarantine of its own host, also when DISTRIBUTED_SETUP is true,
 * and ends with a failure status whenever it stops before its work is done.
 */
class Ticket1327Test extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mailwatch_1327_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/tools/Cron_jobs', 0777, true);
        mkdir($this->dir . '/mailscanner');
        mkdir($this->dir . '/quarantine');
        copy(
            __DIR__ . '/../../tools/Cron_jobs/mailwatch_quarantine_maint.php',
            $this->dir . '/tools/Cron_jobs/mailwatch_quarantine_maint.php'
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testCleanDeletesTheExpiredDaysInADistributedSetup()
    {
        $expired = date('Ymd', strtotime('-40 days'));
        $today = date('Ymd');
        mkdir($this->dir . '/quarantine/' . $expired . '/spam/1ABC-EXPIRED', 0777, true);
        mkdir($this->dir . '/quarantine/' . $today . '/spam/2ABC-RECENT', 0777, true);
        $this->writeFunctions($this->dir . '/quarantine');

        list($status, $output) = $this->runScript(['--clean']);

        $this->assertSame('', $output);
        $this->assertSame(0, $status);
        $this->assertDirectoryDoesNotExist($this->dir . '/quarantine/' . $expired);
        $this->assertDirectoryExists($this->dir . '/quarantine/' . $today . '/spam/2ABC-RECENT');
        $this->assertSame(["UPDATE maillog SET quarantined = NULL WHERE id='1ABC-EXPIRED'"], $this->queries());
    }

    public function testReconcileFlagsTheQuarantinedMessagesInADistributedSetup()
    {
        mkdir($this->dir . '/quarantine/' . date('Ymd') . '/nonspam/3ABC-STORED', 0777, true);
        $this->writeFunctions($this->dir . '/quarantine');

        list($status, $output) = $this->runScript(['--reconcile']);

        $this->assertSame('', $output);
        $this->assertSame(0, $status);
        $this->assertSame(["UPDATE maillog SET quarantined=1 WHERE id='3ABC-STORED'"], $this->queries());
    }

    public function testFailsWhenTheQuarantineDirectoryCannotBeOpened()
    {
        $this->writeFunctions($this->dir . '/missing');

        list($status, $output) = $this->runScript(['--clean']);

        $this->assertSame(1, $status);
        $this->assertSame('diequarantine303 "' . $this->dir . '/missing"', $output);
    }

    public function testFailsWhenFunctionsFileIsMissing()
    {
        // an option that does nothing, in case the script finds a MailWatch installed in the default path
        list($status, $output) = $this->runScript(['--none']);

        $this->assertSame(1, $status);
        $this->assertStringStartsWith('Error: Cannot find functions.php', $output);
    }

    public function testFailsWhenARequiredConstantIsMissing()
    {
        $this->writeFunctions($this->dir . '/quarantine', null, '');

        list($status, $output) = $this->runScript(['--clean']);

        $this->assertSame(1, $status);
        $this->assertSame('message62', $output);
    }

    /**
     * @dataProvider provideArgumentsThatAreNotOneOption
     */
    public function testFailsWithoutOneValidOption(array $arguments)
    {
        $this->writeFunctions($this->dir . '/quarantine');

        list($status, $output) = $this->runScript($arguments);

        $this->assertSame(1, $status);
        $this->assertStringStartsWith('Usage: ', $output);
    }

    public static function provideArgumentsThatAreNotOneOption()
    {
        return [
            'no option' => [[]],
            'unknown option' => [['--purge']],
            'two options' => [['--clean', '--reconcile']],
        ];
    }

    public function testFailsWhenADayCannotBeDeleted()
    {
        $expired = date('Ymd', strtotime('-40 days'));
        mkdir($this->dir . '/quarantine/' . $expired . '/spam', 0777, true);
        $this->writeFunctions($this->dir . '/quarantine');
        // an rm that always fails, found first in the PATH
        mkdir($this->dir . '/bin');
        file_put_contents($this->dir . '/bin/rm', "#!/bin/sh\necho cannot remove\nexit 1\n");
        chmod($this->dir . '/bin/rm', 0755);

        list($status, $output) = $this->runScript(['--clean'], $this->dir . '/bin');

        $this->assertSame(1, $status);
        $this->assertSame('error62 cannot remove', $output);
        $this->assertDirectoryExists($this->dir . '/quarantine/' . $expired);
    }

    public function testFailsWhenFunctionsStopTheScriptWithAMessage()
    {
        mkdir($this->dir . '/quarantine/' . date('Ymd') . '/spam/4ABC-STORED', 0777, true);
        // as dbquery() does when the connection or the query fails
        $this->writeFunctions($this->dir . '/quarantine', 'exit("Invalid query");');

        list($status, $output) = $this->runScript(['--reconcile']);

        $this->assertSame(1, $status);
        $this->assertSame('Invalid query', $output);
    }

    /**
     * Stands in for mailscanner/functions.php in a distributed setup, where get_conf_var() answers only with $force.
     */
    private function writeFunctions($quarantineDir, $dbquery = null, $daysToKeep = 'define("QUARANTINE_DAYS_TO_KEEP", 30);')
    {
        if (null === $dbquery) {
            $dbquery = 'file_put_contents(' . var_export($this->dir . '/queries.log', true) . ', $sql . "\n", FILE_APPEND);';
        }

        file_put_contents($this->dir . '/mailscanner/functions.php', '<?php
define("TIME_ZONE", "UTC");
' . $daysToKeep . '
define("DISTRIBUTED_SETUP", true);
define("DEBUG", false);

function __($string)
{
    return $string;
}

function php_errormsg()
{
    $e = error_get_last();

    return null === $e ? null : $e["message"];
}

function get_conf_var($name, $force = false)
{
    if (DISTRIBUTED_SETUP && !$force) {
        return false;
    }

    return ' . var_export($quarantineDir, true) . ';
}

function dbquery($sql)
{
    ' . $dbquery . '
}
');
    }

    private function runScript(array $arguments, $path = null)
    {
        $command = escapeshellarg(PHP_BINARY) . ' -q ' . escapeshellarg($this->dir . '/tools/Cron_jobs/mailwatch_quarantine_maint.php');
        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg($argument);
        }
        if (null !== $path) {
            $command = 'PATH=' . escapeshellarg($path . PATH_SEPARATOR . getenv('PATH')) . ' ' . $command;
        }
        exec($command . ' 2>&1', $output, $status);

        return [$status, implode("\n", $output)];
    }

    private function queries()
    {
        if (!is_file($this->dir . '/queries.log')) {
            return [];
        }

        return file($this->dir . '/queries.log', FILE_IGNORE_NEW_LINES);
    }
}
