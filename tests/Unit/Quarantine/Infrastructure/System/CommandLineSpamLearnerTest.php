<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine\Infrastructure\System;

use App\Tests\Support\RecordingProcessRunner;
use MailWatch\Quarantine\Domain\LearnAction;
use MailWatch\Quarantine\Infrastructure\System\CommandLineSpamLearner;
use PHPUnit\Framework\TestCase;

final class CommandLineSpamLearnerTest extends TestCase
{
    private const PREFS = '/etc/MailScanner/spam.assassin.prefs.conf';

    public function testTheLocalActionsRunSaLearnAgainstTheStoredFile(): void
    {
        $processes = new RecordingProcessRunner();
        $learner = new CommandLineSpamLearner($processes, '/usr/bin/', self::PREFS, null);

        foreach ([LearnAction::Ham, LearnAction::Spam, LearnAction::Forget] as $action) {
            $learner->learn($action, '/var/spool/quarantine/20260811/message-001/message');
        }

        self::assertSame([
            ['/usr/bin/sa-learn', '-p', self::PREFS, '--ham', '--file', '/var/spool/quarantine/20260811/message-001/message'],
            ['/usr/bin/sa-learn', '-p', self::PREFS, '--spam', '--file', '/var/spool/quarantine/20260811/message-001/message'],
            ['/usr/bin/sa-learn', '-p', self::PREFS, '--forget', '--file', '/var/spool/quarantine/20260811/message-001/message'],
        ], $processes->commands);
        self::assertSame([null, null, null], $processes->standardInputFiles);
    }

    public function testASizeLimitIsPassedOnWhenTheInstallationSetsOne(): void
    {
        $processes = new RecordingProcessRunner();

        (new CommandLineSpamLearner($processes, '/usr/bin/', 'prefs', 256000))
            ->learn(LearnAction::Ham, '/quarantine/message');

        self::assertSame(
            [['/usr/bin/sa-learn', '-p', 'prefs', '--ham', '--file', '/quarantine/message', '--max-size', '256000']],
            $processes->commands,
        );
    }

    /**
     * Reporting and revoking read the message from standard input, which the
     * page script wrote as a shell redirection.
     */
    public function testReportingAndRevokingRunSpamAssassinOnItsInput(): void
    {
        $processes = new RecordingProcessRunner();
        $learner = new CommandLineSpamLearner($processes, '/usr/bin/', 'prefs', null);

        $learner->learn(LearnAction::Report, '/quarantine/message');
        $learner->learn(LearnAction::Revoke, '/quarantine/message');

        self::assertSame([
            ['/usr/bin/spamassassin', '-p', 'prefs', '-r'],
            ['/usr/bin/spamassassin', '-p', 'prefs', '-k'],
        ], $processes->commands);
        self::assertSame(['/quarantine/message', '/quarantine/message'], $processes->standardInputFiles);
    }

    /**
     * The stored file name comes from the message, and the page script pasted
     * it into a shell command. Here it is one argument, whatever is in it.
     */
    public function testAStoredNameIsOneArgumentAndNotShellSyntax(): void
    {
        $processes = new RecordingProcessRunner();

        (new CommandLineSpamLearner($processes, '', 'prefs', null))
            ->learn(LearnAction::Spam, '/quarantine/message; rm -rf /');

        self::assertSame(
            [['sa-learn', '-p', 'prefs', '--spam', '--file', '/quarantine/message; rm -rf /']],
            $processes->commands,
        );
    }

    public function testItReportsHowTheLearnerEnded(): void
    {
        $learner = new CommandLineSpamLearner(
            new RecordingProcessRunner(2, ['sa-learn: cannot open bayes databases']),
            '/usr/bin/',
            'prefs',
            null,
        );

        $result = $learner->learn(LearnAction::Ham, '/quarantine/message');

        self::assertFalse($result->succeeded());
        self::assertSame(2, $result->exitCode);
        self::assertSame(['sa-learn: cannot open bayes databases'], $result->output);
    }
}
