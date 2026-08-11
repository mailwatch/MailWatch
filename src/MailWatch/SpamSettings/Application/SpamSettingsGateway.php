<?php

declare(strict_types=1);

namespace MailWatch\SpamSettings\Application;

interface SpamSettingsGateway
{
    /**
     * @return list<array{
     *     username: string,
     *     spam_score: float,
     *     high_spam_score: float,
     *     no_scan: int
     * }>
     */
    public function settings(): array;
}
