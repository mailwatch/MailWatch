<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuarantineRemoteItemsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../mailscanner/quarantine_items.inc.php';
    }

    /**
     * @return object
     */
    private function row()
    {
        return (object)[
            'hostname' => 'node.example.com',
            'id' => 'ABC123.A1B2C',
            'to_address' => 'user@example.com',
            'isspam' => 'Y',
        ];
    }

    /**
     * @return array
     */
    private function item(array $override = [])
    {
        return $override + [
            'id' => 0,
            'host' => 'node.example.com',
            'msgid' => 'ABC123.A1B2C',
            'to' => 'user@example.com',
            'file' => 'message',
            'type' => 'message/rfc822',
            'path' => '/var/spool/MailScanner/quarantine/20261119/spam/ABC123.A1B2C',
            'md5' => 'd41d8cd98f00b204e9800998ecf8427e',
            'dangerous' => 'N',
            'isspam' => 'Y',
        ];
    }

    public function testAnHonestAnswerIsKept()
    {
        $this->assertSame([$this->item()], quarantine_remote_items([$this->item()], $this->row(), 'N'));
    }

    public function testTheNodeCannotChooseHostRecipientOrFlags()
    {
        // the answer names the master as the host, to have a file of the master sent to the attacker
        $forged = $this->item([
            'id' => 7,
            'host' => 'master.example.com',
            'msgid' => "x' OR '1'='1",
            'to' => 'attacker@evil.example',
            'path' => '/opt/mailwatch/mailscanner/conf.php',
            'dangerous' => 'N',
            'isspam' => 'N',
            'extra' => 'dropped',
        ]);

        $items = quarantine_remote_items([$forged, $this->item()], $this->row(), 'Y');

        // the path stays: it only goes back to the node that gave it
        $this->assertEquals(
            $this->item(['path' => '/opt/mailwatch/mailscanner/conf.php', 'dangerous' => 'Y']),
            $items[0]
        );
        $this->assertSame(1, $items[1]['id']);
        $this->assertSame('Y', $items[1]['dangerous']);
    }

    /**
     * @dataProvider malformedAnswers
     *
     * @param mixed $response
     */
    public function testAMalformedAnswerIsAnError($response)
    {
        $this->assertSame('XML-RPC Error: invalid response', quarantine_remote_items($response, $this->row(), 'N'));
    }

    public function malformedAnswers()
    {
        $noPath = $this->item();
        unset($noPath['path']);

        return [
            'markup instead of a list' => ['<script>alert(1)</script>'],
            'item that is not a struct' => [[$this->item(), 'message']],
            'item without path' => [[$noPath]],
            'path that is not a string' => [[$this->item(['path' => ['/etc/passwd']])]],
        ];
    }

    public function testTheListCallerUsesTheFilterAndTheErrorsAreEscaped()
    {
        $functions = file_get_contents(__DIR__ . '/../../mailscanner/functions.php');
        $this->assertStringContainsString('quarantine_remote_items(php_xmlrpc_decode($rsp->value()), $row, $infected)', $functions);

        $detail = file_get_contents(__DIR__ . '/../../mailscanner/detail.php');
        $this->assertStringNotContainsString("'<br>' . \$quarantined", $detail);

        $ops = file_get_contents(__DIR__ . '/../../mailscanner/do_message_ops.php');
        $this->assertStringNotContainsString('echo $quarantined;', $ops);
        $this->assertStringNotContainsString("'<td class=\"error\">' . \$items", $ops);
    }
}
