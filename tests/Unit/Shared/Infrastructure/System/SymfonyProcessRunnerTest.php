<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\System;

use MailWatch\Shared\Infrastructure\System\SymfonyProcessRunner;
use PHPUnit\Framework\TestCase;

final class SymfonyProcessRunnerTest extends TestCase
{
    public function testItReportsTheExitCodeAndBothStreams(): void
    {
        $result = (new SymfonyProcessRunner())->run([
            PHP_BINARY,
            '-r',
            'echo "learned 1 message\n"; fwrite(STDERR, "bayes db locked\n"); exit(3);',
        ]);

        self::assertFalse($result->succeeded());
        self::assertSame(3, $result->exitCode);
        self::assertSame(['learned 1 message', 'bayes db locked'], $result->output);
    }

    public function testAProgramThatSaysNothingProducesNoOutputLines(): void
    {
        $result = (new SymfonyProcessRunner())->run([PHP_BINARY, '-r', '']);

        self::assertTrue($result->succeeded());
        self::assertSame([], $result->output);
    }

    public function testItFeedsAFileOnStandardInput(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mailwatch-process');
        self::assertIsString($path);
        file_put_contents($path, "Subject: quarantined\n");

        try {
            $result = (new SymfonyProcessRunner())->run(
                [PHP_BINARY, '-r', 'echo stream_get_contents(STDIN);'],
                $path,
            );

            self::assertTrue($result->succeeded());
            self::assertSame(['Subject: quarantined'], $result->output);
        } finally {
            unlink($path);
        }
    }

    public function testAnUnreadableInputFileIsAFailureAndNotAnEmptyMessage(): void
    {
        $result = (new SymfonyProcessRunner())->run(
            [PHP_BINARY, '-r', 'echo stream_get_contents(STDIN);'],
            '/nonexistent/quarantine/message',
        );

        self::assertFalse($result->succeeded());
        self::assertSame(['Cannot read /nonexistent/quarantine/message'], $result->output);
    }

    public function testAMissingProgramIsReportedRatherThanThrown(): void
    {
        $result = (new SymfonyProcessRunner())->run(['mailwatch-no-such-program']);

        self::assertFalse($result->succeeded());
        self::assertNotSame([], $result->output);
    }

    public function testALearnerThatHangsIsStopped(): void
    {
        $result = (new SymfonyProcessRunner(0.1))->run([PHP_BINARY, '-r', 'sleep(5);']);

        self::assertFalse($result->succeeded());
        self::assertStringContainsString('exceeded the timeout', implode("\n", $result->output));
    }
}
