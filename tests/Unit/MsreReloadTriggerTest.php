<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MsreReloadTriggerTest extends TestCase
{
    public function testTheRuleEditorAndTheReloadScriptShareTheTriggerInTheRulesFolder()
    {
        $editor = file_get_contents(__DIR__ . '/../../mailscanner/msre_edit.php');
        $script = file_get_contents(__DIR__ . '/../../tools/MailScanner_rule_editor/msre_reload.sh');

        $this->assertStringContainsString("fopen(MSRE_RULESET_DIR . '/.msre_reload', 'wb')", $editor);
        $this->assertStringContainsString('TRIGGER="${1:-/etc/MailScanner/rules}/.msre_reload"', $script);
        $this->assertStringNotContainsString('/tmp/msre_reload', $editor . $script);
    }
}
