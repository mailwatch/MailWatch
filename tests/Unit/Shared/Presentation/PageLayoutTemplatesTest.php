<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation;

use MailWatch\Shared\Presentation\TemplateRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Characterises the markup of the shared page layout.
 *
 * The page scripts still call html_start() and html_end(); these tests cover
 * the templates those functions now render, so that converting a page to a
 * controller cannot silently change the frame around it.
 */
final class PageLayoutTemplatesTest extends TestCase
{
    public function testTheHeadCarriesTheTitleAndTheClockScript(): void
    {
        $head = $this->renderer()->render('partials/_head.html.twig', [
            'clock_script' => 'function updateClock() {}',
            'title' => 'MailWatch - Quarantine',
            'has_skin' => false,
            'refresh' => 0,
        ]);

        self::assertStringStartsWith('<!DOCTYPE HTML>', $head);
        self::assertStringContainsString('<title>MailWatch - Quarantine</title>', $head);
        self::assertStringContainsString('<script type="text/javascript">function updateClock() {}</script>', $head);
        self::assertStringContainsString('href="/style.css"', $head);
        self::assertStringContainsString("<body onload=\"updateClock(); setInterval('updateClock()', 1000 )\">", $head);

        self::assertStringNotContainsString('skin.css', $head);
        self::assertStringNotContainsString('http-equiv="refresh"', $head);
    }

    public function testTheHeadLinksTheSkinAndRefreshesOnlyWhenAsked(): void
    {
        $head = $this->renderer()->render('partials/_head.html.twig', [
            'clock_script' => '',
            'title' => 'MailWatch',
            'has_skin' => true,
            'refresh' => 30,
        ]);

        self::assertStringContainsString('<link rel="stylesheet" href="/skin.css" type="text/css">', $head);
        self::assertStringContainsString('<meta http-equiv="refresh" content="30">', $head);
    }

    public function testTheHeadEscapesTheTitle(): void
    {
        $head = $this->renderer()->render('partials/_head.html.twig', [
            'clock_script' => '',
            'title' => 'Report </title><script>alert(1)</script>',
            'has_skin' => false,
            'refresh' => 0,
        ]);

        self::assertStringNotContainsString('<script>alert(1)</script>', $head);
        self::assertStringContainsString('&lt;script&gt;', $head);
    }

    public function testThePageHeaderEscapesTheSignedInUserAndTheRequestedMessageId(): void
    {
        $header = $this->renderer()->render('partials/_page_header.html.twig', $this->pageHeaderContext([
            'full_name' => '<script>alert(1)</script>',
            'message_id' => '"><script>alert(2)</script>',
        ]));

        self::assertStringNotContainsString('<script>alert(1)</script>', $header);
        self::assertStringNotContainsString('<script>alert(2)</script>', $header);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $header);
    }

    public function testThePageHeaderOmitsTheStatusColumnForOrdinaryUsers(): void
    {
        $header = $this->renderer()->render('partials/_page_header.html.twig', $this->pageHeaderContext([
            'status_panels' => null,
            'traffic_graph' => '',
            'colspan' => '4',
        ]));

        self::assertStringNotContainsString('status03', $header);
        self::assertStringNotContainsString('traffic', $header);
        self::assertStringContainsString('<td colspan="4">', $header);
    }

    public function testThePageHeaderShowsTheStatusPanelsForAdministrators(): void
    {
        $header = $this->renderer()->render('partials/_page_header.html.twig', $this->pageHeaderContext([
            'status_panels' => '<tr><td>MailScanner</td></tr>',
            'traffic_graph' => '<td id="traffic"></td>',
            'colspan' => '5',
        ]));

        self::assertStringContainsString('<tr><td>MailScanner</td></tr>', $header);
        self::assertStringContainsString('<td id="traffic"></td>', $header);
        self::assertStringContainsString('<td colspan="5">', $header);
    }

    public function testTheNavigationMarksTheCurrentPage(): void
    {
        $navigation = $this->renderer()->render('partials/_navigation.html.twig', [
            'items' => [
                ['url' => 'status.php', 'label' => 'Recent messages', 'active' => false],
                ['url' => 'reports.php', 'label' => 'Reports', 'active' => true],
            ],
            'languages' => [],
            'colspan' => '4',
        ]);

        self::assertStringContainsString('<li><a href="status.php">Recent messages</a></li>', $navigation);
        self::assertStringContainsString('<li class="active"><a href="reports.php">Reports</a></li>', $navigation);
        self::assertStringNotContainsString('langSelect', $navigation);
    }

    public function testTheNavigationOffersTheLanguagesWhenThereIsMoreThanOne(): void
    {
        $navigation = $this->renderer()->render('partials/_navigation.html.twig', [
            'items' => [],
            'languages' => [
                ['code' => 'en', 'label' => 'English', 'selected' => false],
                ['code' => 'it', 'label' => 'Italiano', 'selected' => true],
            ],
            'colspan' => '4',
        ]);

        self::assertStringContainsString('<option value="en">English</option>', $navigation);
        self::assertStringContainsString('<option value="it" selected>Italiano</option>', $navigation);
    }

    public function testTheFooterClosesTheLayoutAndHidesTheTimerUnlessDebugging(): void
    {
        $footer = $this->renderer()->render('partials/_footer.html.twig', $this->footerContext());

        self::assertStringStartsWith("</td>\n</tr>\n</table>", $footer);
        self::assertStringContainsString('MailWatch 2.0.0-dev - &copy; 2006-2026', $footer);
        self::assertStringContainsString('</body>', $footer);
        self::assertStringNotContainsString('<i>', $footer);
    }

    public function testTheFooterShowsThePageTimerWhenDebugging(): void
    {
        $footer = $this->renderer()->render('partials/_footer.html.twig', $this->footerContext([
            'timer' => 'Page generated in 0.123456 seconds',
        ]));

        self::assertStringContainsString('<i>', $footer);
        self::assertStringContainsString('Page generated in 0.123456 seconds', $footer);
    }

    public function testTheLayoutWrapsTheContentBlockBetweenHeaderAndFooter(): void
    {
        $page = $this->renderer()->render(
            'layout.html.twig',
            array_merge(
                ['clock_script' => '', 'title' => 'MailWatch', 'has_skin' => false, 'refresh' => 0],
                $this->pageHeaderContext(),
                $this->footerContext()
            )
        );

        self::assertStringStartsWith('<!DOCTYPE HTML>', $page);
        self::assertStringEndsWith("</html>\n", $page);
        self::assertGreaterThan(strpos($page, '<ul id="menu"'), strpos($page, '</body>'));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function pageHeaderContext(array $overrides = []): array
    {
        return array_merge([
            'logo_src' => './images/mailwatch.png',
            'logo_alt' => 'MailWatch',
            'jump_label' => 'Jump to message',
            'message_id' => '1A2B3C4D5E6F.A1B2C',
            'token' => 'a-session-token',
            'user_heading' => 'User',
            'clock_heading' => 'Server time',
            'full_name' => 'Test User',
            'status_heading' => 'status03',
            'status_panels' => '<tr><td>MailScanner</td></tr>',
            'traffic_graph' => '<td id="traffic"></td>',
            'today_statistics' => '<table id="today"></table>',
            'navigation' => '<tr><td><ul id="menu"></ul></td></tr>',
            'colspan' => '5',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function footerContext(array $overrides = []): array
    {
        return array_merge([
            'extra' => '',
            'timer' => null,
            'footer_text' => 'MailWatch ',
            'version' => '2.0.0-dev',
            'year' => '2026',
        ], $overrides);
    }

    private function renderer(): TemplateRenderer
    {
        return TemplateRenderer::create(\dirname(__DIR__, 4) . '/templates');
    }
}
