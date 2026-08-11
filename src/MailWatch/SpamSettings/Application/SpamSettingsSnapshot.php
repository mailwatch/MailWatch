<?php

declare(strict_types=1);

namespace MailWatch\SpamSettings\Application;

final readonly class SpamSettingsSnapshot
{
    /**
     * @param array<string, float> $spamScores
     * @param array<string, float> $highSpamScores
     * @param list<string>         $noScan
     */
    public function __construct(
        private array $spamScores,
        private array $highSpamScores,
        private array $noScan,
    ) {
    }

    /**
     * @return array{
     *     spam_scores: object,
     *     high_spam_scores: object,
     *     no_scan: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'spam_scores' => (object)$this->spamScores,
            'high_spam_scores' => (object)$this->highSpamScores,
            'no_scan' => $this->noScan,
        ];
    }
}
