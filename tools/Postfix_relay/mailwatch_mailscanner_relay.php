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

ini_set('error_log', 'syslog');
ini_set('html_errors', 'off');
ini_set('display_errors', 'on');
ini_set('implicit_flush', 'false');

// Edit if you changed webapp directory from default
$pathToFunctions = '/var/www/html/mailscanner/functions.php';
if (!@is_file($pathToFunctions)) {
    die('Error: Cannot find functions.php file in "' . $pathToFunctions . '": edit ' . __FILE__ . ' and set the right path on line ' . (__LINE__ - 3) . PHP_EOL);
}
require $pathToFunctions;

// Set-up environment
set_time_limit(0);

function doit($input)
{
    global $fp;
    if (!$fp = popen($input, 'r')) {
        die(__('diepipe54'));
    }

    $lines = 1;
    while ($line = fgets($fp, 2096)) {
        if (preg_match('/^.*MailScanner.*: Requeue: (\S+\.\S+) to (\S+)\s$/', $line, $explode)) {
            $smtpd_id = $explode[1];
            $smtp_id = $explode[2];
            dbquery("REPLACE INTO `mtalog_ids` VALUES ('" . $smtpd_id . "','" . $smtp_id . "')");
        }
        $lines++;
    }
    pclose($fp);
}

if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] === '--refresh') {
    doit('cat ' . MS_LOG);
} else {
    // Refresh first
    doit('cat ' . MS_LOG);
    // Start watching the maillog
    doit('tail -F -n0 ' . MS_LOG);
}
