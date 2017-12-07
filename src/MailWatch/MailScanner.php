<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2017  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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

class MailScanner
{
    /**
     * Parse conf files
     *
     * @param string $name
     * @return array
     */
    public static function parseConfFile($name)
    {
        static $conf_file_cache;
        if (null !== $conf_file_cache && isset($conf_file_cache[$name])) {
            return $conf_file_cache[$name];
        }

        // check if file can be read
        if (!is_file($name) || !is_readable($name)) {
            die(__('dienomsconf03'));
        }

        $array_output = [];
        $var = [];
        // open each file and read it
        $fileContent = array_filter(
            file($name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
            function ($value) {
                return !($value[0] === '#');
            }
        );

        foreach ($fileContent as $line) {
            //echo "line: ".$line."\n"; // only use for troubleshooting lines

            // find all lines that match
            if (preg_match("/^(?P<name>[^#].+[^\s*$])\s*=\s*(?P<value>[^#]*)/", $line, $regs)) {

                // Strip trailing comments
                $regs['value'] = preg_replace('/#.*$/', '', $regs['value']);

                // store %var% variables
                if (preg_match('/%.+%/', $regs['name'])) {
                    $var[$regs['name']] = $regs['value'];
                }

                // expand %var% variables
                if (preg_match('/(%[^%]+%)/', $regs['value'], $matches)) {
                    array_shift($matches);
                    foreach ($matches as $varname) {
                        $regs['value'] = str_replace($varname, $var[$varname], $regs['value']);
                    }
                }

                // Remove any html entities from the code
                $key = htmlentities($regs['name']);
                //$string = htmlentities($regs['value']);
                $string = $regs['value'];

                // Stuff all of the data to an array
                $array_output[$key] = $string;
            }
        }
        unset($fileContent);

        $conf_file_cache[$name] = $array_output;
        return $conf_file_cache[$name];
    }

    /**
     * @param string $name MailScanner config parameter name
     * @param bool $force
     * @return bool
     */
    public static function getConfVar($name, $force = false)
    {
        if (DISTRIBUTED_SETUP && !$force) {
            return false;
        }
        $conf_dir = get_conf_include_folder($force);
        $MailScanner_conf_file = MS_CONFIG_DIR . 'MailScanner.conf';

        $array_output1 = static::parseConfFile($MailScanner_conf_file);
        $array_output2 = parse_conf_dir($conf_dir);

        $array_output = $array_output1;
        if (is_array($array_output2)) {
            $array_output = array_merge($array_output1, $array_output2);
        }

        foreach ($array_output as $parameter_name => $parameter_value) {
            $parameter_name = preg_replace('/ */', '', $parameter_name);

            if (strtolower($parameter_name) === strtolower($name)) {
                if (is_file($parameter_value)) {
                    return read_ruleset_default($parameter_value);
                }

                return $parameter_value;
            }
        }

        die(__('dienoconfigval103') . " $name " . __('dienoconfigval203') . " $MailScanner_conf_file\n");
    }
}
