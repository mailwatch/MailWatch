<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 helper for postfix_relay.php derived from original sendmail_relay.php by Kai Schaetzl, 12/2011

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

ini_set('error_log', 'syslog');
ini_set('html_errors', 'off');
ini_set('display_errors', 'on');
ini_set('implicit_flush', 'false');

// Edit this to reflect the full path to functions.php
require("functions.php");

// Set-up environment
set_time_limit(0);

function doit($input)
{
    global $fp;
    if (!$fp = popen($input, 'r')) {
        die("Cannot open pipe");
    }

    $lines = 1;
    while ($line = fgets($fp, 2096)) {
        if (preg_match('/^.*MailScanner.*: Requeue: (\S+\.\S+) to (\S+)\s$/', $line, $explode)) {
            $smtpd_id = $explode[1];
            $smtp_id = $explode[2];
            dbquery("REPLACE INTO mtalog_ids VALUES ('$smtpd_id','$smtp_id')");
        }
        $lines++;
    }
    pclose($fp);
}

if ($_SERVER['argv'][1] == "--refresh") {
    doit('cat ' . MS_LOG);
} else {
    // Refresh first
    doit('cat ' . MS_LOG);
    // Start watching the maillog
    doit('tail -F -n0 ' . MS_LOG);
}
