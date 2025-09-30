<?php

/**
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2021  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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

/*
 * Parser for the MailScanner configuration files, kept apart from functions.php so that it can be tested.
 */

/**
 * @param string $conf_dir
 *
 * @return array
 */
function parse_conf_dir($conf_dir)
{
    if (!realpath($conf_dir)) {
        $conf_dir = rtrim(MS_CONFIG_DIR, '/') . '/' . ltrim($conf_dir, '/');
    }

    $array_output1 = [];
    // sorted like the glob of the MailScanner include, so that %var% definitions are read in the same order
    if (false !== ($files = scandir($conf_dir))) {
        foreach ($files as $file) {
            // ignore subfolders and hidden files so that it doesn't throw an error when parsing files
            if (strlen($file) > 0 && !str_starts_with($file, '.') && is_file($conf_dir . $file)) {
                $array_output1 = array_merge($array_output1, parse_conf_file($conf_dir . $file));
            }
        }
    }

    return $array_output1;
}

/**
 * Parse conf files.
 *
 * @param string $name
 *
 * @return array
 */
function parse_conf_file($name)
{
    static $conf_file_cache;
    if (null !== $conf_file_cache && isset($conf_file_cache[$name])) {
        return $conf_file_cache[$name];
    }

    // check if file can be read
    if (!is_file($name) || !is_readable($name)) {
        $exitString = __('dienomsconf03');
        if (defined('DEBUG') && DEBUG === true) {
            $exitString .= ' (' . $name . ')';
        }
        exit($exitString);
    }

    // %var% definitions are global in MailScanner: the ones in MailScanner.conf are also used in the conf.d files
    static $var = [];

    $array_output = [];
    // open each file and read it
    $fileContent = array_filter(
        file($name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
        fn($value) => !('#' === $value[0])
    );

    foreach ($fileContent as $line) {
        // echo "line: ".$line."\n"; // only use for troubleshooting lines

        // find all lines that match
        if (preg_match("/^(?P<name>[^#].+[^\s*$])\s*=\s*(?P<value>[^#]*)/", $line, $regs)) {
            // Strip trailing comments
            $regs['value'] = preg_replace('/#.*$/', '', $regs['value']);

            // store %var% variables
            if (preg_match('/%.+%/', $regs['name'])) {
                $var[$regs['name']] = $regs['value'];
            }

            // expand %var% variables, leave the undefined ones as they are
            $regs['value'] = preg_replace_callback('/%[^%]+%/', fn($matches) => $var[$matches[0]] ?? $matches[0], $regs['value']);

            // Remove any html entities from the code
            $key = htmlentities($regs['name']);
            // $string = htmlentities($regs['value']);
            $string = $regs['value'];

            // Stuff all of the data to an array
            $array_output[$key] = $string;
        }
    }
    unset($fileContent);

    $conf_file_cache[$name] = $array_output;

    return $conf_file_cache[$name];
}
