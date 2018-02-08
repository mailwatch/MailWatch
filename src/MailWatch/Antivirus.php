<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2018  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * In addition, as a special exception, the copyright holder gives permission to link the code of this program with
 * those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
 * that use the same license as those files), and distribute linked combinations including the two.
 * You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 * PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 * your version of the program, but you are not obligated to do so.
 * If you do not wish to do so, delete this exception statement from your version.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace MailWatch;

class Antivirus
{
    public static function getAllScanners()
    {
        return explode(' ', MailScanner::getConfVar('VirusScanners'));
    }

    /**
     * @return string
     */
    public static function getPrimaryScanner()
    {
        // Might be more than one scanner defined - pick the first as the primary
        $scanners = static::getAllScanners();

        return $scanners[0];
    }

    /**
     *
     * For reporting of Virus names and statistics a regular expression matching
     * the output of your virus scanner is required.  As Virus names vary across
     * the vendors and are therefore impossible to match - you can only define one
     * scanner as your primary scanner - this should be the scanner you wish to
     * report against.  It defaults to the first scanner found in MailScanner.conf.
     *
     * Please submit any new regular expressions to the MailWatch mailing-list or
     * open an issue on GitHub.
     *
     * If you are running MailWatch in DISTRIBUTED_MODE or you wish to override the
     * selection of the regular expression - you will need to add one of the following
     * statements to conf.php and set the regular expression manually.
     *
     *    define('VIRUS_REGEX', '<<your regexp here>>');
     *    define('VIRUS_REGEX', '/(\S+) was infected by (\S+)/');
     *
     * @param string|null $scanner
     * @throws \RuntimeException
     * @return null|string
     */
    public static function getVirusRegex($scanner = null)
    {
        if ($scanner === null) {
            $scanner = self::getPrimaryScanner();
        }
        if (!defined('VIRUS_REGEX') && DISTRIBUTED_SETUP === true) {
            // Have to set manually as running in DISTRIBUTED_MODE
            throw new \RuntimeException('<B>' . \MailWatch\Translation::__('dieerror03') . "</B><BR>\n&nbsp;" . \MailWatch\Translation::__('dievirus03') . "\n");
        }

        if (!defined('VIRUS_REGEX')) {
            $regex = null;
            switch ($scanner) {
                case 'none':
                    $regex = '/^Dummy$/';
                    break;
                case 'sophos':
                    $regex = '/(>>>) Virus \'(\S+)\' found/';
                    break;
                case 'sophossavi':
                    $regex = '/(\S+) was infected by (\S+)/';
                    break;
                case 'clamav':
                    $regex = '/(.+) contains (\S+)/';
                    break;
                case 'clamd':
                    $regex = '/(.+) was infected: (\S+)/';
                    break;
                case 'clamavmodule':
                    $regex = '/(.+) was infected: (\S+)/';
                    break;
                case 'f-prot':
                    $regex = '/(.+) Infection: (\S+)/';
                    break;
                case 'f-prot-6':
                    $regex = '/(.+) Infection: (\S+)/';
                    break;
                case 'f-protd-6':
                    $regex = '/(.+) Infection: (\S+)/';
                    break;
                case 'mcafee':
                    $regex = '/(.+) Found the (\S+) virus !!!/';
                    break;
                case 'mcafee6':
                    $regex = '/(.+) Found the (\S+) virus !!!/';
                    break;
                case 'f-secure':
                    $regex = '/(.+) Infected: (\S+)/';
                    break;
                case 'trend':
                    $regex = '/(Found virus) (\S+) in file (\S+)/';
                    break;
                case 'bitdefender':
                    $regex = '/(\S+) Found virus (\S+)/';
                    break;
                case 'kaspersky-4.5':
                    $regex = '/(.+) INFECTED (\S+)/';
                    break;
                case 'etrust':
                    $regex = '/(\S+) is infected by virus: (\S+)/';
                    break;
                case 'avg':
                    $regex = '/(Found virus) (\S+) in file (\S+)/';
                    break;
                case 'norman':
                    $regex = '/(Found virus) (\S+) in file (\S+)/';
                    break;
                case 'nod32-1.99':
                    $regex = '/(Found virus) (\S+) in (\S+)/';
                    break;
                case 'antivir':
                    $regex = '/(ALERT:) \[(\S+) \S+\]/';
                    break;
                //default:
                // die("<B>" . \MailWatch\Translation::__('dieerror03') . "</B><BR>\n&nbsp;" . \MailWatch\Translation::__('diescanner03' . "\n");
                // break;
            }
            return $regex;
        }

        return VIRUS_REGEX;
    }

    /**
     * @param string $scanner
     * @return string|false
     */
    public static function getAntivirusConf($scanner)
    {
        $fh = fopen(MS_CONFIG_DIR . 'virus.scanners.conf', 'rb');
        while (!feof($fh)) {
            $line = rtrim(fgets($fh, 1048576));
            if (preg_match("/(^[^#]\S+)\s+(\S+)\s+(\S+)/", $line, $regs)) {
                if ($regs[1] === $scanner) {
                    fclose($fh);

                    return $regs[2] . ' ' . $regs[3];
                }
            }
        }
        // Not found
        fclose($fh);

        return false;
    }

    /**
     * @param string $report virus report message
     * @return string|null
     * @throws \RuntimeException
     */
    public static function getVirus($report)
    {
        $match = null;
        if (defined('VIRUS_REGEX')) {
            preg_match(VIRUS_REGEX, $report, $match);
        } else {
            $scanners = explode(' ', MailScanner::getConfVar('VirusScanners'));
            foreach ($scanners as $scanner) {
                $scannerRegex = static::getVirusRegex($scanner);
                if ($scannerRegex === null || $scannerRegex === '') {
                    error_log('Could not find regex for virus scanner ' . $scanner);
                    continue;
                }
                if (preg_match($scannerRegex, $report, $match) === 1) {
                    break;
                }
            }
        }
        if (isset($match[2]) && count($match) > 2) {
            return $match[2];
        }
        return $report;
    }

    /**
     * @param string $virus
     * @return string
     */
    public static function getVirusLink($virus)
    {
        $virus = htmlentities($virus);
        if (defined('VIRUS_INFO') && VIRUS_INFO !== false) {
            $link = sprintf(VIRUS_INFO, $virus);

            return sprintf('<a href="%s">%s</a>', $link, $virus);
        }

        return $virus;
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    public static function return_todays_top_virus()
    {
        if (self::getVirusRegex() === null) {
            return \MailWatch\Translation::__('unknownvirusscanner03');
        }
        $sql = '
SELECT
 report
FROM
 maillog
WHERE
 virusinfected>0
AND
 date = CURRENT_DATE()
';
        $result = Db::query($sql);
        $virus_array = [];
        while ($row = $result->fetch_object()) {
            $virus = self::getVirus($row->report);
            if ($virus !== null) {
                $virus = self::getVirusLink($virus);
                if (!isset($virus_array[$virus])) {
                    $virus_array[$virus] = 1;
                } else {
                    $virus_array[$virus]++;
                }
            }
        }
        if (count($virus_array) === 0) {
            return \MailWatch\Translation::__('none03');
        }
        arsort($virus_array);
        reset($virus_array);

        // Get the topmost entry from the array
        $top = null;
        $count = 0;
        foreach ($virus_array as $key => $val) {
            if ($top === null) {
                $top = $val;
            } elseif ($val !== $top) {
                break;
            }
            $count++;
        }
        $topvirus_arraykeys = array_keys($virus_array);
        $topvirus = $topvirus_arraykeys[0];
        if ($count > 1) {
            // and ... others
            $topvirus .= sprintf(' ' . \MailWatch\Translation::__('moretopviruses03'), $count - 1);
        }
        return $topvirus;
    }
}
