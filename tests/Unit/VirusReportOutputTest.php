<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class VirusReportOutputTest extends TestCase
{
    public function testTheVirusReportEscapesTheValuesItReadsFromTheMessageReports()
    {
        $source = file_get_contents(__DIR__ . '/../../mailscanner/rep_viruses.php');

        foreach (['$data_names[$i]', '$data_scanner[$i]', '$data_first_seen[$i]'] as $value) {
            $this->assertStringContainsString('htmlspecialchars(' . $value . ", ENT_QUOTES, 'UTF-8')", $source);
            $this->assertStringNotContainsString('<TD>' . $value, $source);
            $this->assertStringNotContainsString('">' . $value, $source);
        }
    }
}
