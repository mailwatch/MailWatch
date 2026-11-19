<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuarantineCommandTest extends TestCase
{
    public function testQuarantinedFilePathsAreShellArguments()
    {
        $source = file_get_contents(__DIR__ . '/../../mailscanner/functions.php');
        $start = strpos($source, 'function quarantine_release(');
        $functions = substr($source, $start, strpos($source, 'function quarantine_delete(') - $start);

        // an attachment name with a space broke the release and the learning commands
        $this->assertStringContainsString("exec(\$cmd . escapeshellarg(\$list[\$val]['path']) . ' 2>&1'", $functions);
        $this->assertStringContainsString("' < ' . escapeshellarg(\$list[\$val]['path']) . ' 2>&1'", $functions);
        $this->assertStringContainsString("' --file ' . escapeshellarg(\$list[\$val]['path'])", $functions);
        $this->assertSame(0, preg_match("/(?<!escapeshellarg\\()\\\$list\\[\\\$val\\]\\['path'\\]\\s*\\.\\s*'/", $functions));
    }

    public function testSendmailRecipientCannotBeAnOption()
    {
        $source = file_get_contents(__DIR__ . '/../../mailscanner/functions.php');
        $start = strpos($source, 'function quarantine_release(');
        $function = substr($source, $start, strpos($source, 'function quarantine_learn(') - $start);

        // an alternate recipient such as -be or -X/path was read by sendmail as an option
        $this->assertStringContainsString("MAILWATCH_FROM_ADDR . ' -- ' . escapeshellarg(stripslashes(\$to)) . ' < '", $function);
    }
}
