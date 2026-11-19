<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class OutputHardeningTest extends TestCase
{
    /**
     * @param string $file
     *
     * @return string
     */
    private function source($file)
    {
        return file_get_contents(__DIR__ . '/../../mailscanner/' . $file);
    }

    public function testSortLinksEncodeTheRequestParameters()
    {
        $source = $this->source('functions.php');

        // the parameter names and values went into the href as they came
        $this->assertStringContainsString("http_build_query(array_diff_key(\$_GET, array_flip(\$preserve)), '', '&amp;')", $source);
        $this->assertStringNotContainsString('"$k=$v"', $source);

        $_GET = ['orderby' => 'date', 'token' => 'abc', 'x"><script>' => '"><b>', 'list' => ['a']];
        $query = http_build_query(array_diff_key($_GET, array_flip(['orderby', 'orderdir'])), '', '&amp;');
        $_GET = [];
        $this->assertSame(0, preg_match('/["<>]/', $query));
        $this->assertStringStartsWith('token=abc&amp;', $query);
    }

    public function testTheMessageViewerSandboxesTheBodyFrame()
    {
        // a link in the message could load a page without the sandbox of viewpart.php in the frame
        $this->assertStringContainsString('<iframe sandbox ', $this->source('viewmail.php'));
    }

    public function testRuleEditorAndConfigurationPageEscapeTheFileContents()
    {
        $editor = $this->source('msre_edit.php');
        $this->assertStringContainsString("'<pre>' . htmlspecialchars((string)\$file_contents,", $editor);
        $this->assertStringContainsString('$value = htmlspecialchars((string)$value,', $editor);

        $config = $this->source('msconfig.php');
        $this->assertStringContainsString('$value = htmlspecialchars($value,', $config);
        $this->assertStringContainsString("'<a href=\"msrule.php?file=' . rawurlencode(\$file) . '\">'", $config);
    }

    public function testTheMilterQueueViewerQuotesTheFoldersAndEscapesTheListing()
    {
        $source = $this->source('msmail.inc.php');

        $this->assertSame(2, substr_count($source, "exec('ls -l1 ' . escapeshellarg("));
        $this->assertSame(2, substr_count($source, 'echo htmlspecialchars($row,'));
    }

    public function testTheAutoReleaseQueriesQuoteTheirValues()
    {
        $source = $this->source('quarantine_report.inc.php');

        $this->assertStringNotContainsString("'\$id'", $source);
        $this->assertStringNotContainsString("'\$rand'", $source);
    }
}
