<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Application;

use MailWatch\Quarantine\Domain\LearnAction;
use MailWatch\Shared\Application\Port\ProcessResult;

/**
 * Teaches SpamAssassin about one stored message.
 *
 * The result is returned rather than thrown: a learner that fails on the
 * third of five messages must not stop the other two, and the operator is
 * shown what each one said.
 */
interface SpamLearner
{
    public function learn(LearnAction $action, string $path): ProcessResult;
}
