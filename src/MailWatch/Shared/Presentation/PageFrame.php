<?php

declare(strict_types=1);

namespace MailWatch\Shared\Presentation;

/**
 * The shared frame a page is rendered inside.
 *
 * Controllers depend on this rather than on PageLayout, so that what a
 * controller does can be tested without the session, the configuration and the
 * status panels the real frame is built from.
 */
interface PageFrame
{
    /**
     * @return array<string, mixed>
     */
    public function context(string $title, int $refresh = 0, string $footer = ''): array;
}
