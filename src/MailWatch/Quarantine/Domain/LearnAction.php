<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Domain;

/**
 * What an operator asks the SpamAssassin learner to do with a message.
 *
 * Two of the five leave the machine: reporting and revoking talk to the
 * collaborative networks through `spamassassin` itself, while the other three
 * only touch the local Bayes database through `sa-learn`.
 */
enum LearnAction: string
{
    case Ham = 'ham';
    case Spam = 'spam';
    case Forget = 'forget';
    case Report = 'report';
    case Revoke = 'revoke';

    public function usesSpamAssassin(): bool
    {
        return self::Report === $this || self::Revoke === $this;
    }

    /**
     * What the message ends up recorded as: 1 for ham, 2 for spam, and
     * nothing at all when the learner was told to forget it.
     */
    public function learnedClass(): ?int
    {
        return match ($this) {
            self::Ham, self::Revoke => 1,
            self::Spam, self::Report => 2,
            self::Forget => null,
        };
    }

    /**
     * The word the audit trail records, which names the outcome rather than
     * the command: revoking a report is telling SpamAssassin the message was
     * ham after all.
     */
    public function auditLabel(): string
    {
        return match ($this) {
            self::Report => 'spam',
            self::Revoke => 'ham',
            default => $this->value,
        };
    }
}
