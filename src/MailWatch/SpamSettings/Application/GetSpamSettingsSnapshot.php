<?php

declare(strict_types=1);

namespace MailWatch\SpamSettings\Application;

final readonly class GetSpamSettingsSnapshot
{
    public function __construct(private SpamSettingsGateway $gateway)
    {
    }

    public function get(): SpamSettingsSnapshot
    {
        $spamScores = [];
        $highSpamScores = [];
        $noScan = [];

        foreach ($this->gateway->settings() as $setting) {
            $username = strtolower($setting['username']);
            if ($setting['spam_score'] > 0) {
                $spamScores[$username] = $setting['spam_score'];
            }
            if ($setting['high_spam_score'] > 0) {
                $highSpamScores[$username] = $setting['high_spam_score'];
            }
            if ($setting['no_scan'] > 0) {
                $noScan[] = $username;
            }
        }

        ksort($spamScores, SORT_STRING);
        ksort($highSpamScores, SORT_STRING);
        sort($noScan, SORT_STRING);

        return new SpamSettingsSnapshot($spamScores, $highSpamScores, $noScan);
    }
}
