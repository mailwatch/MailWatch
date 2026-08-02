<?php

declare(strict_types=1);

namespace MailWatch\Antivirus\Http;

use MailWatch\Antivirus\Domain\AntivirusScanner;
use MailWatch\Shared\Application\Port\CommandRunner;
use MailWatch\Shared\Http\PageGuard;
use MailWatch\Shared\Http\Response;
use MailWatch\Shared\Presentation\PageFrame;
use MailWatch\Shared\Presentation\TemplateRenderer;

/**
 * The status page shared by every antivirus product.
 *
 * Five page scripts used to hold a copy of this, differing only in the command
 * they ran and the title they showed.
 */
final readonly class AntivirusStatusController
{
    /**
     * @param \Closure(string): void $auditLog
     */
    public function __construct(
        private TemplateRenderer $renderer,
        private PageFrame $layout,
        private PageGuard $guard,
        private CommandRunner $commands,
        private \Closure $auditLog
    ) {
    }

    public function handle(AntivirusScanner $scanner): Response
    {
        if (!$this->guard->isAdministrator()) {
            ($this->auditLog)(__('auditlog19', true));

            return Response::redirect('index.php');
        }

        $this->guard->enforce(false, 0);

        $report = null === $scanner->reportCommand
            ? ''
            : $this->commands->run($scanner->reportCommand);

        return Response::html($this->renderer->render(
            'status/antivirus.html.twig',
            array_merge($this->layout->context(__($scanner->titleKey)), [
                'report' => $report,
                'available' => null !== $scanner->reportCommand,
            ])
        ));
    }
}
