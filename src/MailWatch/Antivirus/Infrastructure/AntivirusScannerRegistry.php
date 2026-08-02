<?php

declare(strict_types=1);

namespace MailWatch\Antivirus\Infrastructure;

use MailWatch\Antivirus\Domain\AntivirusScanner;
use MailWatch\Shared\Application\Port\CommandRunner;

/**
 * The antivirus products with a status page, and the command each one reports
 * through.
 *
 * Every command pipes the product's own version output through the matching
 * awk script, which is what turns it into the table the page shows. The scanner
 * paths come from the MailScanner configuration, a root-owned file, and are
 * passed to the shell unquoted exactly as the page scripts did: several
 * installations configure a scanner as a command with arguments.
 *
 * A product that is not installed, or that the web server user cannot run,
 * yields no command at all. The page then says so, rather than running a
 * pipeline whose only output is the header its awk script prints regardless of
 * input.
 */
final readonly class AntivirusScannerRegistry
{
    /** @var list<string> */
    public const SCANNERS = ['clamav', 'sophos', 'mcafee', 'f-prot', 'f-secure', 'f-secure12'];

    private const FSECURE_SCANNER = '/opt/f-secure/fsav/bin/fsav';

    /**
     * F-Secure 12 has no version switch: it prints its engine versions while
     * refusing to scan a file that does not exist.
     */
    private const FSECURE12_PROBE = '/opt/f-secure/linuxsecurity/bin/fsanalyze';

    public function __construct(
        private CommandRunner $commands,
        private string $scriptDirectory
    ) {
    }

    public function get(string $id): AntivirusScanner
    {
        return match ($id) {
            'clamav' => new AntivirusScanner(
                $id,
                'avclamavstatus19',
                $this->report('clamscan', ' -V', 'clamav')
            ),
            'sophos' => new AntivirusScanner(
                $id,
                'sophos53',
                $this->report(get_virus_conf('sophos'), ' -v', 'sophos')
            ),
            'mcafee' => new AntivirusScanner(
                $id,
                'mcafeestatus25',
                $this->report(get_virus_conf('mcafee'), ' --version', 'mcafee')
            ),
            'f-prot' => new AntivirusScanner(
                $id,
                'fprotstatus22',
                $this->report(get_virus_conf('f-prot'), $this->fProtOption(), 'f-prot')
            ),
            'f-secure' => new AntivirusScanner(
                $id,
                'fsecurestatus23',
                $this->report(self::FSECURE_SCANNER, ' --version', 'f-secure')
            ),
            'f-secure12' => new AntivirusScanner(
                $id,
                'fsecurestatus23',
                $this->report(
                    self::FSECURE12_PROBE,
                    ' ' . escapeshellarg($this->scriptDirectory . '/notexistingfile.txt'),
                    null
                )
            ),
            default => throw new \InvalidArgumentException(sprintf('Unknown antivirus scanner "%s"', $id)),
        };
    }

    /**
     * The command that produces the report, or null when the product cannot be
     * run here.
     *
     * @param string|false $scanner   the configured scanner, which may carry arguments;
     *                                get_virus_conf() answers false when the product is
     *                                absent from virus.scanners.conf
     * @param string       $arguments what makes it print its version
     * @param string|null  $script    the awk script that formats the output, or null when the caller parses it
     */
    private function report(string|false $scanner, string $arguments, ?string $script): ?string
    {
        if (false === $scanner || !$this->isRunnable($scanner)) {
            return null;
        }

        $command = $scanner . $arguments;

        return null === $script
            ? $command
            : $command . ' | awk -f ' . escapeshellarg($this->scriptDirectory . '/' . $script . '.awk');
    }

    /**
     * Whether the executable at the head of a configured scanner command exists
     * and can be run.
     *
     * `command -v` answers for both a bare name on the path and an absolute
     * path, and is a shell builtin, so this does not depend on `which` being
     * installed.
     */
    private function isRunnable(string $scanner): bool
    {
        $executable = strtok(trim($scanner), " \t");

        // strtok() answers false when the configured scanner is empty.
        if (false === $executable) {
            return false;
        }

        $resolved = $this->commands->run('command -v ' . escapeshellarg($executable) . ' 2>/dev/null');

        return '' !== trim($resolved);
    }

    /**
     * F-Prot version 6 renamed the option that prints the version.
     */
    private function fProtOption(): string
    {
        return str_contains((string)get_conf_var('VirusScanners'), '/-6/') ? ' -virno' : ' -verno';
    }
}
