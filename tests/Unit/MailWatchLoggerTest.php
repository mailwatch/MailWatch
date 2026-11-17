<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MailWatchLoggerTest extends TestCase
{
    private static function source()
    {
        return file_get_contents(__DIR__ . '/../../MailScanner_perl_scripts/MailWatch.pm');
    }

    public function testTheInfectedCountersFitTheirTinyintColumns()
    {
        $source = self::source();
        foreach (['virusinfected', 'nameinfected', 'otherinfected'] as $counter) {
            $this->assertStringContainsString("\$msg{{$counter}} = TinyintCount(\$message->{{$counter}});", $source);
        }

        $this->assertSame(1, preg_match('/^sub TinyintCount \{.*?^\}/ms', $source, $sub));
        $script = 'use strict; ' . $sub[0]
            . ' print join(",", map { defined $_ ? $_ : "undef" } map { TinyintCount($_) } (0, 3, 127, 128, 200, undef)), "\n";';
        $output = [];
        exec('perl -e ' . escapeshellarg($script), $output, $status);

        $this->assertSame(0, $status);
        $this->assertSame(['0,3,127,127,127,undef'], $output);
    }

    public function testARowThatKeepsFailingIsDroppedAfterALimitedNumberOfAttempts()
    {
        $source = self::source();

        $this->assertStringContainsString('my $max_insert_attempts = 3;', $source);
        $this->assertSame(1, preg_match('/\$attempts\+\+;.*?if \(\$attempts >= \$max_insert_attempts\) \{.*?last;.*?close\(SERVER\);/s', $source));
    }
}
