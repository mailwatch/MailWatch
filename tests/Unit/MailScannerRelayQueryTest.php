<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MailScannerRelayQueryTest extends TestCase
{
    public function testTheRelayEscapesTheQueueIdsReadFromTheMailLog()
    {
        $source = file_get_contents(__DIR__ . '/../../tools/Postfix_relay/mailwatch_mailscanner_relay.php');

        $this->assertStringContainsString('$smtpd_id = safe_value($explode[1]);', $source);
        $this->assertStringContainsString('$smtp_id = safe_value($explode[2]);', $source);
    }
}
