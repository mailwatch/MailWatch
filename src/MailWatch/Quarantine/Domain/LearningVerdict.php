<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Domain;

/**
 * What learning a message says about the filter's original decision.
 *
 * Teaching that a message MailScanner called spam is really ham makes it a
 * false positive; teaching the opposite makes it a false negative. Forgetting
 * a message asserts neither, and clears both.
 */
final readonly class LearningVerdict
{
    public function __construct(
        public bool $falsePositive,
        public bool $falseNegative,
    ) {
    }

    public static function of(LearnAction $action, bool $classifiedAsSpam): self
    {
        return match ($action) {
            LearnAction::Ham => new self($classifiedAsSpam, false),
            LearnAction::Spam => new self(false, !$classifiedAsSpam),
            LearnAction::Report => new self(false, true),
            LearnAction::Revoke => new self(true, false),
            LearnAction::Forget => new self(false, false),
        };
    }
}
