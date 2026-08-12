<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Infrastructure\Mail;

use MailWatch\Quarantine\Application\QuarantineReleaser;
use MailWatch\Quarantine\Application\ReleaseOutcome;
use MailWatch\Quarantine\Domain\ReleaseNotice;
use MailWatch\Shared\Application\Port\ProcessRunner;

/**
 * Hands the original message to the local MTA, unchanged.
 *
 * Only a stored part that is itself a message can be released this way, so
 * anything else the operator selected is ignored — as it always has been.
 */
final readonly class SendmailQuarantineReleaser implements QuarantineReleaser
{
    public function __construct(
        private ProcessRunner $processes,
        private ReleaseNotice $notice,
        private string $sendmailPath,
    ) {
    }

    public function release(string $recipients, array $parts): ReleaseOutcome
    {
        foreach ($parts as $part) {
            if (!str_contains($part->type, 'message/rfc822')) {
                continue;
            }

            // The recipient list goes to sendmail as one argument, which is
            // what the page script's single quoted string amounted to.
            $result = $this->processes->run(
                [$this->sendmailPath, '-i', '-f', $this->notice->from, $recipients],
                $part->path,
            );

            return $result->succeeded()
                ? ReleaseOutcome::delivered()
                : ReleaseOutcome::failed($result->exitCode, $result->output);
        }

        return ReleaseOutcome::refused('No valid message found');
    }
}
