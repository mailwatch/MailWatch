<?php

declare(strict_types=1);

namespace MailWatch\Shared\Presentation;

/**
 * Builds the context the shared page frame is rendered with.
 *
 * Both routes into the layout use this class: html_start() and html_end() for
 * the page scripts, and layout.html.twig for controllers. Keeping the context
 * in one place is what lets a converted page keep the frame it had.
 *
 * It calls the functions in mailscanner/functions.php, which remain the source
 * of the session, the configuration and the status panels. Those calls move
 * behind proper collaborators as each panel is extracted.
 */
final readonly class PageLayout implements PageFrame
{
    /**
     * @param array<string, mixed> $session
     */
    public function __construct(
        private array $session,
        private string $projectDirectory
    ) {
    }

    /**
     * The whole frame, for a controller rendering layout.html.twig.
     *
     * The title is prefixed the way html_start() prefixes it, so a converted
     * page keeps the title it had.
     *
     * @return array<string, mixed>
     */
    public function context(string $title, int $refresh = 0, string $footer = ''): array
    {
        return array_merge(
            $this->headContext(__('mwforms03') . $title, $refresh),
            $this->pageHeaderContext(),
            $this->footerContext($footer)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function headContext(string $title, int $refresh = 0): array
    {
        return [
            'clock_script' => $this->capture('java_time'),
            'title' => $title,
            'has_skin' => is_file($this->projectDirectory . '/public_html/skin.css'),
            'refresh' => $refresh,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pageHeaderContext(): array
    {
        $isAdministrator = $this->isAdministrator();
        $statusPanels = null;
        $trafficGraph = '';

        if ($isAdministrator || 'D' === ($this->session['user_type'] ?? null)) {
            $statusPanels = $this->capture(static function() use ($isAdministrator): void {
                printServiceStatus();
                printAverageLoad();

                if ($isAdministrator) {
                    printMTAQueue();
                    printFreeDiskSpace();
                }
            });
            $trafficGraph = $this->capture('printTrafficGraph');
        }

        return [
            'logo_src' => IMAGES_DIR . MW_LOGO,
            'logo_alt' => __('mailwatchtitle03'),
            'jump_label' => __('jumpmessage03'),
            'message_id' => $this->requestedMessageId(),
            'token' => $this->session['token'] ?? '',
            'user_heading' => __('cuser03'),
            'clock_heading' => __('cst03'),
            'full_name' => $this->session['fullname'] ?? '',
            'status_heading' => __('status03'),
            'status_panels' => $statusPanels,
            'traffic_graph' => $trafficGraph,
            'today_statistics' => $this->capture('printTodayStatistics'),
            'navigation' => $this->capture('printNavBar'),
            'colspan' => $isAdministrator ? '5' : '4',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function footerContext(string $footer = ''): array
    {
        return [
            'extra' => $footer,
            'timer' => \defined('DEBUG') && DEBUG ? page_creation_timer() : null,
            'footer_text' => __('footer03'),
            'version' => mailwatch_version(),
            'year' => date('Y'),
        ];
    }

    private function isAdministrator(): bool
    {
        return 'A' === ($this->session['user_type'] ?? null);
    }

    /**
     * The identifier the jump form was submitted with.
     *
     * It is validated in the form the user typed it, not an escaped copy: the
     * template escapes it on the way out.
     */
    private function requestedMessageId(): string
    {
        if (!isset($_GET['id'])) {
            return '';
        }

        $requested = trim((string)sanitizeInput($_GET['id']), ' ');

        return validateInput($requested, 'msgid') ? $requested : '';
    }

    private function capture(callable $printer): string
    {
        return mailwatch_capture($printer);
    }
}
