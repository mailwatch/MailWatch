<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Infrastructure\System;

use MailWatch\Quarantine\Application\SpamLearner;
use MailWatch\Quarantine\Domain\LearnAction;
use MailWatch\Shared\Application\Port\ProcessResult;
use MailWatch\Shared\Application\Port\ProcessRunner;

/**
 * The learner as SpamAssassin ships it: `sa-learn` for the local Bayes
 * database, `spamassassin` itself for reporting to and revoking from the
 * collaborative networks.
 *
 * The two are fed differently. `sa-learn` is told where the message is;
 * `spamassassin` reads it from standard input, which the page script wrote as
 * a shell redirection and which is now the process's own input.
 */
final readonly class CommandLineSpamLearner implements SpamLearner
{
    public function __construct(
        private ProcessRunner $processes,
        private string $directory,
        private string $preferences,
        private ?int $maximumSize,
    ) {
    }

    public function learn(LearnAction $action, string $path): ProcessResult
    {
        if ($action->usesSpamAssassin()) {
            return $this->processes->run([
                $this->directory . 'spamassassin',
                '-p', $this->preferences,
                LearnAction::Report === $action ? '-r' : '-k',
            ], $path);
        }

        $command = [
            $this->directory . 'sa-learn',
            '-p', $this->preferences,
            '--' . $action->value,
            '--file', $path,
        ];
        if (null !== $this->maximumSize) {
            $command[] = '--max-size';
            $command[] = (string)$this->maximumSize;
        }

        return $this->processes->run($command);
    }
}
