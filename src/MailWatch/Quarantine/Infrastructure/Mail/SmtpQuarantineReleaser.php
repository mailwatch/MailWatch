<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Infrastructure\Mail;

use MailWatch\Quarantine\Application\QuarantineReleaser;
use MailWatch\Quarantine\Application\ReleaseOutcome;
use MailWatch\Quarantine\Domain\ReleaseNotice;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * Wraps the selected parts in a covering message and submits it over SMTP.
 *
 * The message itself is attached as `message/rfc822` under the name the page
 * has always given it; anything else keeps its own name and media type.
 */
final readonly class SmtpQuarantineReleaser implements QuarantineReleaser
{
    private const ORIGINAL_MESSAGE = 'Original Message';

    public function __construct(
        private TransportInterface $transport,
        private ReleaseNotice $notice,
    ) {
    }

    public function release(string $recipients, array $parts): ReleaseOutcome
    {
        $email = (new Email())
            ->subject($this->notice->subject)
            ->text($this->notice->body);

        try {
            $email->from($this->notice->from);
            foreach (self::addresses($recipients) as $address) {
                $email->addTo($address);
            }

            foreach ($parts as $part) {
                $isMessage = str_contains($part->type, 'message/rfc822');
                $email->attachFromPath(
                    $part->path,
                    $isMessage ? self::ORIGINAL_MESSAGE : $part->file,
                    $isMessage ? 'message/rfc822' : self::mediaType($part->type),
                );
            }

            // Asked before submitting rather than left to the transport: a
            // maillog row with no recipient must be a refusal we can report,
            // not a message handed over with nobody to deliver it to.
            $email->ensureValidity();
            $this->transport->send($email);
        } catch (\Throwable $exception) {
            return ReleaseOutcome::refused($exception->getMessage());
        }

        return ReleaseOutcome::delivered();
    }

    /**
     * The recipient column holds every address of the original message, and
     * each one is a recipient of the release.
     *
     * @return list<string>
     */
    private static function addresses(string $recipients): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $recipients)),
            static fn(string $address): bool => '' !== $address,
        ));
    }

    /**
     * `file -bi` answers with parameters attached — `application/pdf;
     * charset=binary` — and only the type belongs in the part's own header.
     */
    private static function mediaType(string $reported): string
    {
        $type = trim(explode(';', $reported)[0]);

        return str_contains($type, '/') ? $type : 'application/octet-stream';
    }
}
