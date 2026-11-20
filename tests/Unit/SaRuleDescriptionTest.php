<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SaRuleDescriptionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (function_exists('sa_rule_desc_html')) {
            return;
        }
        // functions.php bootstraps the whole application when required: load only this function from our own source
        $source = file_get_contents(__DIR__ . '/../../mailscanner/functions.php');
        $start = strpos($source, 'function sa_rule_desc_html(');
        $end = strpos($source, "\n}\n", $start);
        eval(substr($source, $start, $end - $start + 2));
    }

    public function testTheHtml5EntitiesStoredByTheRuleUpdateAreNotEncodedTwice()
    {
        // what sa_rules_update.php stores for BAYES_00 and RCVD_IN_DNSWL_NONE
        $this->assertSame('Bayes spam probability is 0 to 1&percnt;', sa_rule_desc_html('Bayes spam probability is 0 to 1&percnt;'));
        $this->assertSame(
            'Sender listed at https&colon;&sol;&sol;www&period;dnswl&period;org&sol;&comma; no trust',
            sa_rule_desc_html('Sender listed at https&colon;&sol;&sol;www&period;dnswl&period;org&sol;&comma; no trust')
        );
    }

    public function testPlainDescriptionsAreStillEscaped()
    {
        // the cron job stores the descriptions as plain text
        $this->assertSame('a &lt;b&gt; &amp; &quot;c&quot; &apos;d&apos;', sa_rule_desc_html('a <b> & "c" \'d\''));
    }

    public function testBothRuleDescriptionReadersUseIt()
    {
        $source = file_get_contents(__DIR__ . '/../../mailscanner/functions.php');

        $this->assertSame(2, substr_count($source, 'sa_rule_desc_html($row->rule_desc)'));
        $this->assertStringNotContainsString('htmlentities($row->rule_desc)', $source);
    }
}
