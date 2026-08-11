<?php

declare(strict_types=1);

namespace App\Tests\Unit\Quarantine\Domain;

use MailWatch\Quarantine\Domain\LearnAction;
use MailWatch\Quarantine\Domain\LearningVerdict;
use PHPUnit\Framework\TestCase;

final class LearnActionTest extends TestCase
{
    public function testOnlyReportingAndRevokingLeaveTheMachine(): void
    {
        self::assertTrue(LearnAction::Report->usesSpamAssassin());
        self::assertTrue(LearnAction::Revoke->usesSpamAssassin());
        self::assertFalse(LearnAction::Ham->usesSpamAssassin());
        self::assertFalse(LearnAction::Spam->usesSpamAssassin());
        self::assertFalse(LearnAction::Forget->usesSpamAssassin());
    }

    public function testTheLearnedClassFollowsTheOutcomeAndNotTheCommand(): void
    {
        self::assertSame(1, LearnAction::Ham->learnedClass());
        self::assertSame(1, LearnAction::Revoke->learnedClass());
        self::assertSame(2, LearnAction::Spam->learnedClass());
        self::assertSame(2, LearnAction::Report->learnedClass());
        self::assertNull(LearnAction::Forget->learnedClass());
    }

    public function testTheAuditTrailNamesTheOutcome(): void
    {
        self::assertSame('spam', LearnAction::Report->auditLabel());
        self::assertSame('ham', LearnAction::Revoke->auditLabel());
        self::assertSame('forget', LearnAction::Forget->auditLabel());
        self::assertSame('ham', LearnAction::Ham->auditLabel());
        self::assertSame('spam', LearnAction::Spam->auditLabel());
    }

    public function testTeachingHamAboutASpamIsAFalsePositive(): void
    {
        $verdict = LearningVerdict::of(LearnAction::Ham, true);

        self::assertTrue($verdict->falsePositive);
        self::assertFalse($verdict->falseNegative);
    }

    public function testTeachingHamAboutAMessageAlreadyCalledHamAssertsNothing(): void
    {
        $verdict = LearningVerdict::of(LearnAction::Ham, false);

        self::assertFalse($verdict->falsePositive);
        self::assertFalse($verdict->falseNegative);
    }

    public function testTeachingSpamAboutAHamIsAFalseNegative(): void
    {
        $verdict = LearningVerdict::of(LearnAction::Spam, false);

        self::assertFalse($verdict->falsePositive);
        self::assertTrue($verdict->falseNegative);
    }

    public function testReportingAlwaysClaimsAFalseNegativeAndRevokingAFalsePositive(): void
    {
        self::assertTrue(LearningVerdict::of(LearnAction::Report, false)->falseNegative);
        self::assertFalse(LearningVerdict::of(LearnAction::Report, true)->falsePositive);
        self::assertTrue(LearningVerdict::of(LearnAction::Revoke, true)->falsePositive);
        self::assertFalse(LearningVerdict::of(LearnAction::Revoke, false)->falseNegative);
    }

    public function testForgettingClearsBothFlags(): void
    {
        $verdict = LearningVerdict::of(LearnAction::Forget, true);

        self::assertFalse($verdict->falsePositive);
        self::assertFalse($verdict->falseNegative);
    }
}
