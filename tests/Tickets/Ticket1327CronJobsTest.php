<?php

namespace App\Tests\Tickets;

use PHPUnit\Framework\TestCase;

/**
 * The cron scripts end with a failure status whenever they stop before their work is done.
 */
class Ticket1327CronJobsTest extends TestCase
{
    private const SCRIPTS = [
        'mailwatch_db_clean.php',
        'mailwatch_geoip_update.php',
        'mailwatch_quarantine_report.php',
        'mailwatch_update_sarules.php',
    ];

    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mailwatch_1327_cron_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/tools/Cron_jobs', 0777, true);
        mkdir($this->dir . '/mailscanner');
        mkdir($this->dir . '/rules');
        file_put_contents($this->dir . '/rules/test.cf', "describe MW_TEST_RULE A rule for the test\n");
        foreach (self::SCRIPTS as $script) {
            copy(__DIR__ . '/../../tools/Cron_jobs/' . $script, $this->dir . '/tools/Cron_jobs/' . $script);
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    /**
     * @dataProvider provideScripts
     */
    public function testFailsWhenFunctionsFileIsMissing($script)
    {
        list($status, $output) = $this->runScript($script);

        $this->assertSame(1, $status);
        $this->assertStringStartsWith('Error: Cannot find functions.php', $output);
    }

    public static function provideScripts()
    {
        return array_map(static function ($script) {
            return [$script];
        }, self::SCRIPTS);
    }

    /**
     * @dataProvider provideScriptsThatQueryTheDatabase
     */
    public function testFailsWhenFunctionsStopTheScriptWithAMessage($script)
    {
        // as dbquery() does when the connection or the query fails
        $this->writeFunctions(null, 'exit("Invalid query");');

        list($status, $output) = $this->runScript($script);

        $this->assertSame(1, $status);
        $this->assertSame('Invalid query', $output);
    }

    public static function provideScriptsThatQueryTheDatabase()
    {
        return [
            'db clean' => ['mailwatch_db_clean.php'],
            'update sa rules' => ['mailwatch_update_sarules.php'],
        ];
    }

    public function testDbCleanDeletesTheExpiredRows()
    {
        $this->writeFunctions();

        list($status, $output) = $this->runScript('mailwatch_db_clean.php');

        $this->assertSame(0, $status);
        $this->assertSame('', $output);
        $queries = $this->queries();
        $this->assertStringStartsWith('DELETE LOW_PRIORITY FROM maillog', $queries[0]);
        // mtalog_ids is empty, as with an MTA that does not fill it
        $this->assertContains('DELETE LOW_PRIORITY FROM mtalog WHERE timestamp < (NOW() - INTERVAL 60 DAY) LIMIT 10000', $queries);
        $this->assertSame("SHOW TABLES LIKE 'login_failures'", end($queries));
    }

    public function testDbCleanCleansTheMtalogIdsWithoutTheMtaInADistributedSetup()
    {
        // get_conf_var() answers false, as in a distributed setup; the Postfix relays filled mtalog_ids
        $this->writeFunctions(null, 'file_put_contents(' . var_export($this->dir . '/queries.log', true) . ', $sql . "\n", FILE_APPEND);
    return (object) ["num_rows" => "SELECT 1 FROM mtalog_ids LIMIT 1" === $sql ? 1 : 0];');

        list($status, $output) = $this->runScript('mailwatch_db_clean.php');

        $this->assertSame(0, $status);
        $this->assertSame('', $output);
        $this->assertContains('SELECT mtalog_id, msg_id FROM mtalog WHERE timestamp < (NOW() - INTERVAL 60 DAY) LIMIT 10000', $this->queries());
    }

    /**
     * @dataProvider provideMissingDaysToKeep
     */
    public function testDbCleanFailsWithoutTheDaysToKeep($defines, $variable)
    {
        $this->writeFunctions($defines);

        list($status, $output) = $this->runScript('mailwatch_db_clean.php');

        $this->assertSame(1, $status);
        $this->assertStringStartsWith('The variable ' . $variable . ' is empty', $output);
        $this->assertSame([], $this->queries());
    }

    public static function provideMissingDaysToKeep()
    {
        return [
            'record days not defined' => ['', 'RECORD_DAYS_TO_KEEP'],
            'record days zero' => ['define("RECORD_DAYS_TO_KEEP", 0);', 'RECORD_DAYS_TO_KEEP'],
            'audit days not defined' => ['define("RECORD_DAYS_TO_KEEP", 60);', 'AUDIT_DAYS_TO_KEEP'],
        ];
    }

    public function testUpdateSaRulesStoresTheRuleDescriptions()
    {
        $this->writeFunctions();

        list($status) = $this->runScript('mailwatch_update_sarules.php');

        $this->assertSame(0, $status);
        // the script also reads the system directories, that may hold real rules
        $this->assertContains("REPLACE INTO sa_rules VALUES ('MW_TEST_RULE','A rule for the test')", $this->queries());
    }

    public function testQuarantineReportFailsWhenTheRequirementsAreNotMet()
    {
        $this->writeFunctions();
        $this->writeQuarantineReport('"requirement missing\n"', 'false');

        list($status, $output) = $this->runScript('mailwatch_quarantine_report.php');

        $this->assertSame(1, $status);
        $this->assertSame('requirement missing', $output);
    }

    /**
     * @dataProvider provideSendResults
     */
    public function testQuarantineReportStatusFollowsTheSendResult($result, $expectedStatus)
    {
        $this->writeFunctions();
        $this->writeQuarantineReport('true', var_export($result, true));

        list($status, $output) = $this->runScript('mailwatch_quarantine_report.php');

        $this->assertSame($expectedStatus, $status);
        $this->assertSame('', $output);
    }

    public static function provideSendResults()
    {
        return [
            'reports sent' => [['succ' => 2, 'failed' => 0, 'empty' => 1], 0],
            'no user asks for a report' => [-2, 0],
            'a report failed' => [['succ' => 1, 'failed' => 1, 'empty' => 0], 1],
        ];
    }

    public function testGeoipUpdateFailsWithoutALicenseKey()
    {
        $this->writeFunctions('');

        list($status, $output) = $this->runScript('mailwatch_geoip_update.php');

        $this->assertSame(1, $status);
        $this->assertStringStartsWith('geoipnokey15', $output);
    }

    public function testGeoipUpdateFailsWhenTheDownloadFails()
    {
        $this->writeFunctions('define("MAXMIND_LICENSE_KEY", "test");');
        $this->writeGeoipUpdate('"downbad15 GeoLite2"');

        list($status, $output) = $this->runScript('mailwatch_geoip_update.php');

        $this->assertSame(1, $status);
        $this->assertSame('downbad15 GeoLite2', $output);
    }

    public function testGeoipUpdateSucceeds()
    {
        $this->writeFunctions('define("MAXMIND_LICENSE_KEY", "test");');
        $this->writeGeoipUpdate('true');

        list($status, $output) = $this->runScript('mailwatch_geoip_update.php');

        $this->assertSame(0, $status);
        $this->assertStringContainsString('processok52', $output);
    }

    /**
     * @dataProvider provideDailyJobResults
     */
    public function testDailyWrapperRunsEveryJobAndFailsWhenOneFails($failing, $expectedStatus)
    {
        $jobs = ['mailwatch_quarantine_maint.php', 'mailwatch_quarantine_report.php', 'mailwatch_db_clean.php'];
        foreach ($jobs as $job) {
            $this->writeJob($job, $job === $failing ? 1 : 0);
        }

        $status = $this->runWrapper('mailwatch');

        $this->assertSame($expectedStatus, $status);
        $this->assertSame($jobs, file($this->dir . '/jobs.log', FILE_IGNORE_NEW_LINES));
    }

    public static function provideDailyJobResults()
    {
        return [
            'every job succeeds' => [null, 0],
            'quarantine maintenance fails' => ['mailwatch_quarantine_maint.php', 1],
            'quarantine report fails' => ['mailwatch_quarantine_report.php', 1],
            'db clean fails' => ['mailwatch_db_clean.php', 1],
        ];
    }

    /**
     * @dataProvider provideJobStatuses
     */
    public function testMonthlyWrapperEndsWithTheStatusOfTheGeoipUpdate($jobStatus)
    {
        $this->writeJob('mailwatch_geoip_update.php', $jobStatus);

        $this->assertSame($jobStatus, $this->runWrapper('mailwatch.cron.monthly'));
    }

    public static function provideJobStatuses()
    {
        return [
            'update succeeds' => [0],
            'update fails' => [1],
        ];
    }

    /**
     * A job that logs its name and ends with $status, in place of the script installed in /usr/local/bin.
     */
    private function writeJob($name, $status)
    {
        @mkdir($this->dir . '/bin');
        file_put_contents($this->dir . '/bin/' . $name, "#!/bin/sh\necho " . $name . ' >> ' . escapeshellarg($this->dir . '/jobs.log') . "\necho output\nexit " . $status . "\n");
        chmod($this->dir . '/bin/' . $name, 0755);
    }

    /**
     * Runs a copy of the wrapper that calls the jobs in bin/, with a logger that writes nowhere and the shortest delay.
     */
    private function runWrapper($wrapper)
    {
        @mkdir($this->dir . '/bin');
        file_put_contents($this->dir . '/bin/logger', "#!/bin/sh\ncat >/dev/null\n");
        chmod($this->dir . '/bin/logger', 0755);
        $script = str_replace(
            ['/usr/local/bin/', 'UPDATEMAXDELAY=3600'],
            [$this->dir . '/bin/', 'UPDATEMAXDELAY=1'],
            file_get_contents(__DIR__ . '/../../tools/Cron_jobs/' . $wrapper)
        );
        file_put_contents($this->dir . '/' . $wrapper, $script);

        exec('PATH=' . escapeshellarg($this->dir . '/bin' . PATH_SEPARATOR . getenv('PATH')) . ' bash ' . escapeshellarg($this->dir . '/' . $wrapper) . ' 2>&1', $output, $status);

        return $status;
    }

    /**
     * Stands in for mailscanner/functions.php: $defines are extra constants, $dbquery is the body of dbquery().
     */
    private function writeFunctions($defines = null, $dbquery = null)
    {
        if (null === $defines) {
            $defines = 'define("RECORD_DAYS_TO_KEEP", 60);
define("AUDIT_DAYS_TO_KEEP", 60);
define("SA_RULES_DIR", ' . var_export($this->dir . '/rules', true) . ');
define("SA_PREFS", ' . var_export($this->dir . '/missing.conf', true) . ');';
        }
        if (null === $dbquery) {
            $dbquery = 'file_put_contents(' . var_export($this->dir . '/queries.log', true) . ', $sql . "\n", FILE_APPEND);
    return (object) ["num_rows" => 0];';
        }

        file_put_contents($this->dir . '/mailscanner/functions.php', '<?php
define("MAILWATCH_HOME", __DIR__);
define("DEBUG", false);
' . $defines . '

function __($string, $useSystemLang = false)
{
    return $string;
}

function audit_log()
{
}

function safe_value($value)
{
    return $value;
}

function get_conf_var($name, $force = false)
{
    return false;
}

function validateInput($input, $type)
{
    return true;
}

function dbquery($sql)
{
    ' . $dbquery . '
}

function dbconn()
{
    return (object) ["affected_rows" => 0];
}
');
    }

    private function writeGeoipUpdate($result)
    {
        file_put_contents(
            $this->dir . '/mailscanner/geoip_update.inc.php',
            '<?php function geoip_update_download($extract_dir, $eol) { return ' . $result . '; }'
        );
    }

    private function writeQuarantineReport($requirements, $sendResult)
    {
        file_put_contents($this->dir . '/mailscanner/quarantine_report.inc.php', '<?php
class Quarantine_Report
{
    public static function check_quarantine_report_requirements()
    {
        return ' . $requirements . ';
    }

    public function send_quarantine_reports($usersForReport = [])
    {
        return ' . $sendResult . ';
    }
}
');
    }

    private function runScript($script)
    {
        exec(escapeshellarg(PHP_BINARY) . ' -q ' . escapeshellarg($this->dir . '/tools/Cron_jobs/' . $script) . ' 2>&1', $output, $status);

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
