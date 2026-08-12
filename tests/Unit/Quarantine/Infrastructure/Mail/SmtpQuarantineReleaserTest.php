<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine\Infrastructure\Mail;

use App\Tests\Support\RecordingMailTransport;
use MailWatch\Quarantine\Domain\QuarantineItem;
use MailWatch\Quarantine\Domain\ReleaseNotice;
use MailWatch\Quarantine\Infrastructure\Mail\SmtpQuarantineReleaser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final class SmtpQuarantineReleaserTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $directory = tempnam(sys_get_temp_dir(), 'mailwatch-release');
        self::assertIsString($directory);
        unlink($directory);
        mkdir($directory, 0o700);
        $this->directory = $directory;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testTheHeldMessageTravelsInsideACoveringMessage(): void
    {
        $transport = new RecordingMailTransport();
        $path = $this->storedFile('message', "Subject: held\r\n\r\nbody\r\n");

        $outcome = $this->releaser($transport)->release('owner@example.test', [
            new QuarantineItem('message', $path, 'message/rfc822'),
        ]);

        self::assertTrue($outcome->delivered);
        $email = $this->sentEmail($transport);
        self::assertSame('Released from quarantine', $email->getSubject());
        self::assertSame('Your message is attached.', $email->getTextBody());
        self::assertSame('postmaster@example.test', $email->getFrom()[0]->getAddress());
        self::assertSame(['owner@example.test'], array_map(
            static fn(object $address): string => $address->getAddress(),
            $email->getTo(),
        ));
    }

    public function testEveryRecipientOfTheOriginalGetsTheRelease(): void
    {
        $transport = new RecordingMailTransport();
        $path = $this->storedFile('message', 'held');

        $this->releaser($transport)->release(' one@example.test , two@example.test ,', [
            new QuarantineItem('message', $path, 'message/rfc822'),
        ]);

        self::assertSame(['one@example.test', 'two@example.test'], array_map(
            static fn(object $address): string => $address->getAddress(),
            $this->sentEmail($transport)->getTo(),
        ));
    }

    public function testTheMessageIsAttachedUnderTheNameThePageHasAlwaysUsed(): void
    {
        $transport = new RecordingMailTransport();
        $path = $this->storedFile('message', 'held');

        $this->releaser($transport)->release('owner@example.test', [
            new QuarantineItem('message', $path, 'message/rfc822'),
        ]);

        $attachment = $this->sentEmail($transport)->getAttachments()[0];
        self::assertInstanceOf(DataPart::class, $attachment);
        self::assertSame('Original Message', $attachment->getFilename());
        self::assertSame('message', $attachment->getMediaType());
        self::assertSame('rfc822', $attachment->getMediaSubtype());
    }

    /**
     * `file -bi` answers with parameters attached, and only the media type
     * itself belongs in the part's header.
     */
    public function testAStoredPartKeepsItsOwnNameAndTypeWithoutTheDetectorsParameters(): void
    {
        $transport = new RecordingMailTransport();
        $path = $this->storedFile('report.pdf', '%PDF-1.4');

        $this->releaser($transport)->release('owner@example.test', [
            new QuarantineItem('report.pdf', $path, 'application/pdf; charset=binary'),
        ]);

        $attachment = $this->sentEmail($transport)->getAttachments()[0];
        self::assertSame('report.pdf', $attachment->getFilename());
        self::assertSame('application', $attachment->getMediaType());
        self::assertSame('pdf', $attachment->getMediaSubtype());
    }

    public function testATypeTheDetectorCouldNotNameFallsBackToBytes(): void
    {
        $transport = new RecordingMailTransport();
        $path = $this->storedFile('unknown.bin', 'data');

        $this->releaser($transport)->release('owner@example.test', [
            new QuarantineItem('unknown.bin', $path, 'cannot open'),
        ]);

        $attachment = $this->sentEmail($transport)->getAttachments()[0];
        self::assertSame('application', $attachment->getMediaType());
        self::assertSame('octet-stream', $attachment->getMediaSubtype());
    }

    public function testAServerThatRefusesTheMessageIsReportedAndNotThrown(): void
    {
        $transport = new RecordingMailTransport(new TransportException('Connection refused'));
        $path = $this->storedFile('message', 'held');

        $outcome = $this->releaser($transport)->release('owner@example.test', [
            new QuarantineItem('message', $path, 'message/rfc822'),
        ]);

        self::assertFalse($outcome->delivered);
        self::assertNull($outcome->exitCode);
        self::assertSame(['Connection refused'], $outcome->detail);
    }

    public function testAReleaseWithoutARecipientIsRefusedRatherThanFatal(): void
    {
        $transport = new RecordingMailTransport();
        $path = $this->storedFile('message', 'held');

        $outcome = $this->releaser($transport)->release('  ', [
            new QuarantineItem('message', $path, 'message/rfc822'),
        ]);

        self::assertFalse($outcome->delivered);
        self::assertNotSame([], $outcome->detail);
        self::assertSame([], $transport->sent);
    }

    private function releaser(RecordingMailTransport $transport): SmtpQuarantineReleaser
    {
        return new SmtpQuarantineReleaser($transport, new ReleaseNotice(
            'postmaster@example.test',
            'Released from quarantine',
            'Your message is attached.',
        ));
    }

    private function storedFile(string $name, string $contents): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function sentEmail(RecordingMailTransport $transport): Email
    {
        self::assertCount(1, $transport->sent);
        $email = $transport->sent[0];
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }
}
