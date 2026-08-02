<?php

declare(strict_types=1);

namespace App\Tests\Support;

use MailWatch\Shared\Presentation\PageFrame;

/**
 * A page frame with a fixed context, so a controller can be rendered without
 * the session, the configuration and the status panels behind the real one.
 */
final class FakePageFrame implements PageFrame
{
    /** @var list<string> */
    public array $titles = [];

    public function context(string $title, int $refresh = 0, string $footer = ''): array
    {
        $this->titles[] = $title;

        return [
            'clock_script' => '',
            'title' => 'MailWatch - ' . $title,
            'has_skin' => false,
            'refresh' => $refresh,
            'logo_src' => './images/mailwatch.png',
            'logo_alt' => 'MailWatch',
            'jump_label' => 'Jump to message',
            'message_id' => '',
            'token' => 'a-session-token',
            'user_heading' => 'User',
            'clock_heading' => 'Server time',
            'full_name' => 'Test Administrator',
            'status_heading' => 'Status',
            'status_panels' => null,
            'traffic_graph' => '',
            'today_statistics' => '',
            'navigation' => '<tr><td><ul id="menu"></ul></td></tr>',
            'colspan' => '5',
            'extra' => $footer,
            'timer' => null,
            'footer_text' => 'MailWatch ',
            'version' => '2.0.0-dev',
            'year' => '2026',
        ];
    }
}
