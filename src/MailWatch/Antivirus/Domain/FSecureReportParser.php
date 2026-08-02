<?php

declare(strict_types=1);

namespace MailWatch\Antivirus\Domain;

/**
 * Reads the engine versions out of what F-Secure 12 prints.
 *
 * The product has no version switch: it reports its engines while refusing to
 * scan a file that does not exist, on a single line among ordinary output.
 *
 * When nothing matches - the product is not installed, or a later release
 * changed the wording - the result is empty and the page shows no table. It
 * used to index into the match unconditionally and warn four times.
 */
final readonly class FSecureReportParser
{
    private const ENGINES = '@'
        . '.*F-Secure Corporation Aquarius/(?<aquarius_version>.*)/(?<aquarius_date>.*)\s'
        . '.*F-Secure Corporation Hydra/(?<hydra_version>.*)/(?<hydra_date>.*)\s'
        . 'F-Secure Corporation FMLib/(?<fmlib_version>.*)/(?<fmlib_date>.*)\s'
        . 'fsicapd/(?<fsicapd>.*)@m';

    /**
     * @return list<AntivirusEngine>
     */
    public function parse(string $output): array
    {
        if (1 !== preg_match(self::ENGINES, $output, $matches)) {
            return [];
        }

        return [
            new AntivirusEngine('Aquarius', $matches['aquarius_version'], $matches['aquarius_date']),
            new AntivirusEngine('Hydra', $matches['hydra_version'], $matches['hydra_date']),
            new AntivirusEngine('FMLib', $matches['fmlib_version'], $matches['fmlib_date']),
            new AntivirusEngine('fsicapd', $matches['fsicapd'], null),
        ];
    }
}
