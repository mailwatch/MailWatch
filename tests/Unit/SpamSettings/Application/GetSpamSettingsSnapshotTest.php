<?php

declare(strict_types=1);

namespace App\Tests\Unit\SpamSettings\Application;

use MailWatch\SpamSettings\Application\GetSpamSettingsSnapshot;
use MailWatch\SpamSettings\Application\SpamSettingsGateway;
use PHPUnit\Framework\TestCase;

final class GetSpamSettingsSnapshotTest extends TestCase
{
    public function testItBuildsNormalizedSortedPositiveSettings(): void
    {
        $gateway = new class implements SpamSettingsGateway {
            public function settings(): array
            {
                return [
                    ['username' => 'Zulu@EXAMPLE.COM', 'spam_score' => 3.5, 'high_spam_score' => 0.0, 'no_scan' => 1],
                    ['username' => 'alpha@example.com', 'spam_score' => 0.0, 'high_spam_score' => 7.0, 'no_scan' => 0],
                    ['username' => 'ignored@example.com', 'spam_score' => -1.0, 'high_spam_score' => 0.0, 'no_scan' => -1],
                ];
            }
        };

        $snapshot = (new GetSpamSettingsSnapshot($gateway))->get()->toArray();

        self::assertEquals((object)['zulu@example.com' => 3.5], $snapshot['spam_scores']);
        self::assertEquals((object)['alpha@example.com' => 7.0], $snapshot['high_spam_scores']);
        self::assertSame(['zulu@example.com'], $snapshot['no_scan']);
    }
}
