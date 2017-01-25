#!/user/bin/php -q
<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2017  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
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
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
$mailscannerRoot = "/var/www/html/mailscanner/";
if (count($argv) > 1) {
    $mailscannerRoot = $argv[1];
}

header("Content-type: text/plain\n\n");

//load the config file to be able to see the defined values
require($mailscannerRoot . "conf.php");

echo "Checking your conf.php if it contains all necessary constants" . PHP_EOL;

//read the example config for constants that are missing in conf.php
$missingConfig = '';
$missingKeys = '';
$fh = fopen($mailscannerRoot . 'conf.php.example', 'r');
while ($line = fgets($fh)) {
    $tl = trim($line);
    if (substr($tl, 0, 8) == "define('") {
        $arr = explode("'", $tl);
        if (count($arr) < 3) {
            continue;
        }
        $key = $arr[1];
        if (!defined($key)) {
            //constant does not exist yet
            $missingConfig .= PHP_EOL . $line;
            $missingKeys .= $key . ', ';
        }
    }
}
fclose($fh);

//append the missing constants to the config file
file_put_contents($mailscannerRoot . "conf.php", $missingConfig, FILE_APPEND);
if ($missingConfig != '') {
    echo "The constants $missingKeys were missing in conf.php and were added." . PHP_EOL;
    echo "Please review the values and adjust them to your needs" . PHP_EOL;
} else {
    echo "All necessary constants were found" . PHP_EOL;
}
