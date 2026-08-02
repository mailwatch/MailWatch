<?php

declare(strict_types=1);

namespace MailWatch\Antivirus\Http;

use MailWatch\Antivirus\Domain\AntivirusScanner;
use MailWatch\Antivirus\Domain\FSecureReportParser;
use MailWatch\Shared\Application\Port\CommandRunner;
use MailWatch\Shared\Http\PageGuard;
use MailWatch\Shared\Http\Response;
use MailWatch\Shared\Presentation\PageFrame;
use MailWatch\Shared\Presentation\TemplateRenderer;

/**
 * The F-Secure 12 status page.
 *
 * It is not served by AntivirusStatusController because this product reports
 * its engines as prose rather than through an awk script: the output is parsed
 * here and the table is a template.
 */
final readonly class FSecureStatusController implements StatusController
{
    /**
     * @param \Closure(string): void $auditLog
     */
    public function __construct(
        private TemplateRenderer $renderer,
        private PageFrame $layout,
        private PageGuard $guard,
        private CommandRunner $commands,
        private FSecureReportParser $parser,
        private \Closure $auditLog
    ) {
    }

    public function handle(AntivirusScanner $scanner): Response
    {
        if (!$this->guard->isAdministrator()) {
            ($this->auditLog)(__('auditlog19', true));

            return Response::redirect('/');
        }

        $this->guard->enforce(false, 0);

        $engines = null === $scanner->reportCommand
            ? []
            : $this->parser->parse($this->commands->run($scanner->reportCommand));

        return Response::html($this->renderer->render(
            'status/fsecure12.html.twig',
            array_merge($this->layout->context(__($scanner->titleKey)), [
                'engines' => $engines,
                'available' => null !== $scanner->reportCommand,
            ])
        ));
    }
}
