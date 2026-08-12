<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine\Infrastructure\Mail;

use App\Tests\Support\RecordingProcessRunner;
use MailWatch\Quarantine\Domain\QuarantineItem;
use MailWatch\Quarantine\Domain\ReleaseNotice;
use MailWatch\Quarantine\Infrastructure\Mail\SendmailQuarantineReleaser;
use PHPUnit\Framework\TestCase;

final class SendmailQuarantineReleaserTest extends TestCase
{
    public function testItPipesTheOriginalMessageToTheLocalMta(): void
    {
        $processes = new RecordingProcessRunner();

        $outcome = $this->releaser($processes)->release('owner@example.test', [
            new QuarantineItem('message', '/quarantine/20260811/nonspam/message-001', 'message/rfc822'),
        ]);

        self::assertTrue($outcome->delivered);
        self::assertSame(
            [['/usr/sbin/sendmail', '-i', '-f', 'postmaster@example.test', 'owner@example.test']],
            $processes->commands,
        );
        self::assertSame(['/quarantine/20260811/nonspam/message-001'], $processes->standardInputFiles);
    }

    /**
     * The recipient column arrives as one comma-separated string and has
     * always been handed to sendmail as a single argument.
     */
    public function testSeveralRecipientsStayOneArgument(): void
    {
        $processes = new RecordingProcessRunner();

        $this->releaser($processes)->release('one@example.test,two@example.test', [
            new QuarantineItem('message', '/quarantine/message', 'message/rfc822'),
        ]);

        self::assertSame('one@example.test,two@example.test', $processes->commands[0][4]);
    }

    public function testItSkipsAnythingThatIsNotItselfAMessage(): void
    {
        $processes = new RecordingProcessRunner();

        $this->releaser($processes)->release('owner@example.test', [
            new QuarantineItem('report.pdf', '/quarantine/message-001/report.pdf', 'application/pdf'),
            new QuarantineItem('message', '/quarantine/message-001/message', 'message/rfc822; charset=us-ascii'),
        ]);

        self::assertSame(['/quarantine/message-001/message'], $processes->standardInputFiles);
    }

    public function testWithoutAMessageThereIsNothingToRelease(): void
    {
        $processes = new RecordingProcessRunner();

        $outcome = $this->releaser($processes)->release('owner@example.test', [
            new QuarantineItem('report.pdf', '/quarantine/message-001/report.pdf', 'application/pdf'),
        ]);

        self::assertFalse($outcome->delivered);
        self::assertNull($outcome->exitCode);
        self::assertSame(['No valid message found'], $outcome->detail);
        self::assertSame([], $processes->commands);
    }

    public function testAnMtaThatRefusesTheMessageIsReportedWithItsStatus(): void
    {
        $processes = new RecordingProcessRunner(75, ['sendmail: deferred: connection refused']);

        $outcome = $this->releaser($processes)->release('owner@example.test', [
            new QuarantineItem('message', '/quarantine/message', 'message/rfc822'),
        ]);

        self::assertFalse($outcome->delivered);
        self::assertSame(75, $outcome->exitCode);
        self::assertSame(['sendmail: deferred: connection refused'], $outcome->detail);
    }

    private function releaser(RecordingProcessRunner $processes): SendmailQuarantineReleaser
    {
        return new SendmailQuarantineReleaser(
            $processes,
            new ReleaseNotice('postmaster@example.test', 'Released', 'Body'),
            '/usr/sbin/sendmail',
        );
    }
}
