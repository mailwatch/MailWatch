<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Application;

use MailWatch\Quarantine\Domain\MessageScope;
use MailWatch\Quarantine\Domain\QuarantinedMessage;

/**
 * Everything the quarantine operations need from maillog.
 *
 * The lookup answers "may this account act on this message, and where is it",
 * in one question, so that authorisation cannot be forgotten by a caller that
 * only wanted the location.
 */
interface QuarantineMessageGateway
{
    public function messageInScope(string $messageId, MessageScope $scope): ?QuarantinedMessage;

    public function markReleased(string $messageId): void;

    /**
     * The verdict a learner run produced: a released spam is a false positive,
     * a reported ham a false negative.
     */
    public function recordLearningVerdict(string $messageId, bool $falsePositive, bool $falseNegative): void;

    /**
     * What the message was finally learned as: 1 for ham, 2 for spam.
     */
    public function recordLearnedClass(string $messageId, int $class): void;

    /**
     * Forget where the message was stored, after its files have been deleted.
     */
    public function clearQuarantineLocation(string $messageId): void;
}
