#!/usr/bin/php -q
<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 Copyright (C) 2014-2015  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 In addition, as a special exception, the copyright holder gives permission to link the code of this program
 with those files in the PEAR library that are licensed under the PHP License (or with modified versions of those
 files that use the same license as those files), and distribute linked combinations including the two.
 You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 your version of the program, but you are not obligated to do so.
 If you do not wish to do so, delete this exception statement from your version.

 As a special exception, you have permission to link this program with the JpGraph library and
 distribute executables, as long as you follow the requirements of the GNU GPL in regard to all of the software
 in the executable aside from JpGraph.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define('MAILWATCH_HOME', '/var/www/html/mailscanner/');

ini_set('error_log', 'syslog');
ini_set('html_errors', 'off');
ini_set('display_errors', 'on');
ini_set('implicit_flush', 'false');
set_time_limit(0);

// Prevent multiple copies running
$fl = fopen("/var/run/mailq.lock", "w+");
// Attempt to create an exclusive lock - continue if successful
if (flock($fl, LOCK_EX + LOCK_NB)) {
    require "" . MAILWATCH_HOME . "functions.php";
    date_default_timezone_set(TIME_ZONE);

    $queue['inq'] = get_conf_var('IncomingQueueDir') . '/';
    $queue['outq'] = get_conf_var('OutgoingQueueDir') . '/';
    $MTA = get_conf_var('MTA');

    foreach ($queue as $table_name => $queuedir) {
        // Clear the output array
        $output = array();
        if ($dh = @opendir($queuedir)) {
            while (false !== ($file = readdir($dh))) {
                if ($MTA == "exim") {
                    if (preg_match("/-H$/", $file)) {
                        // Get rid of the '-H' from the end of the filename to get the msgid
                        $msgid = substr($file, 0, strlen($file) - 2);
                        if (($fh = @fopen($queuedir . $file, "r"))) {
                            // Work out the total size (df+qf) of the mail
                            $output[$msgid]['size'] = (@filesize($queuedir . $msgid . "-D") + filesize(
                                    $queuedir . $msgid . "-H"
                                ));
                            $output[$msgid]['version'] = 'N/A';
                            $output[$msgid]['ctladdr'] = 'N/A';
                            $output[$msgid]['orcpt'] = 'N/A';
                            $output[$msgid]['rcpt'] = 'N/A';
                            $output[$msgid]['priority'] = 'N/A';
                            $output[$msgid]['flags'] = 'N/A';
                            $output[$msgid]['attempts'] = 'N/A';
                            $output[$msgid]['lastattempttime'] = 'N/A';
                            $output[$msgid]['message'] = 'N/A';
                            while (!@feof($fh)) {
                                if (($line = @fgets($fh, 1024))) {
                                    switch (true) {
                                        case preg_match('/^-ident (.+)$/', $line, $match):
                                            $output[$msgid]['auth'] = $match[1];
                                            break;
                                        case preg_match('/^([-\.\w]+@[-\.\w]+)$/', $line, $match):
                                            $output[$msgid]['rcpts'][] = $match[1];
                                            break;
                                        case preg_match('/^\d{3}F .*: (.+)$/', $line, $match):
                                            $output[$msgid]['sender'] = $match[1];
                                            break;
                                        case preg_match('/^(\d{10,}) \d+$/', $line, $match):
                                            $ctime = getdate($match[1]);
                                            $output[$msgid]['cdate'] = $ctime['year'] . "-" . str_pad(
                                                    $ctime['mon'],
                                                    2,
                                                    "0",
                                                    STR_PAD_LEFT
                                                ) . "-" . str_pad($ctime['mday'], 2, "0", STR_PAD_LEFT);
                                            $output[$msgid]['ctime'] = str_pad(
                                                    $ctime['hours'],
                                                    2,
                                                    "0",
                                                    STR_PAD_LEFT
                                                ) . ":" . str_pad(
                                                    $ctime['minutes'],
                                                    2,
                                                    "0",
                                                    STR_PAD_LEFT
                                                ) . ":" . str_pad($ctime['seconds'], 2, "0", STR_PAD_LEFT);
                                            break;
                                        case preg_match('/^\d{3}I Message-ID: <(.+)>$/', $line, $match):
                                            #$output[$msgid]['message'] = $match[1];
                                            break;
                                        case preg_match('/^<(.+)>$/', $line, $match):
                                            $output[$msgid]['envelopesender'] = $match[1];
                                            break;
                                        case preg_match('/^\d{3}  Subject: (.+)$/', $line, $match):
                                            $output[$msgid]['subject'] = $match[1];
                                            break;
                                    }
                                }
                            }
                            fclose($fh);
                            //  Get the message file
                            $MsgDir = preg_replace('/^(.*)\/input\/$/', '$1/msglog/', $queuedir);
                            if (($fh = @fopen($MsgDir . $msgid, "r"))) {
                                $output[$msgid]['message'] = '';
                                $output[$msgid]['attempts'] = 0;
                                // Get the current message log
                                while (!@feof($fh)) {
                                    if (($line = @fgets($fh, 1024))) {
                                        if (preg_match('/retry time not reached/', $line)) {
                                            continue;
                                        }
                                        if (preg_match('/^(\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d) (.*)/', $line, $Regs)) {
                                            if (preg_match('/ (defer|failed) /', $Regs[2])) {
                                                $output[$msgid]['attempts']++;
                                            }
                                            $output[$msgid]['lastattempttime'] = $Regs[1];
                                            $output[$msgid]['message'] = htmlentities($Regs[2]);
                                        }
                                        $output[$msgid]['message'] .= nl2br($line);
                                        $output[$msgid]['message'] = preg_replace(
                                            "/<br \/>/",
                                            "<BR>",
                                            $output[$msgid]['message']
                                        );
                                    }
                                }
                                fclose($fh);
                                if ($output[$msgid]['lastattempttime'] != 'N/A') {
                                    $output[$msgid]['lastattempttime'] = strtotime($output[$msgid]['lastattempttime']);
                                }
                            }
                        }
                    }
                } else {
                    if (preg_match("/^qf/", $file)) {
                        // Get rid of the 'qf' from the front of the filename to get the msgid
                        $msgid = substr($file, 2);
                        if (($fh = @fopen($queuedir . $file, "r"))) {
                            // Work out the total size (df+qf) of the mail
                            $output[$msgid]['size'] = (@filesize($queuedir . "df" . $msgid) + @filesize(
                                    $queuedir . "qf" . $msgid
                                ));
                            $output[$msgid]['message'] = '';
                            while (!@feof($fh)) {
                                if (($line = @fgets($fh, 1024))) {
                                    switch (true) {
                                        case preg_match('/^V(.+)$/', $line, $match):
                                            $output[$msgid]['version'] = $match[1];
                                            break;
                                        case preg_match('/^A(.+)$/', $line, $match):
                                            $output[$msgid]['auth'] = $match[1];
                                            break;
                                        case preg_match('/^C(.+)$/', $line, $match):
                                            $output[$msgid]['ctladdr'] = $match[1];
                                            break;
                                        case preg_match('/^Q(.+)$/', $line, $match):
                                            $output[$msgid]['orcpt'] = $match[1];
                                            break;
                                        case preg_match('/^r(.+)$/', $line, $match):
                                            $output[$msgid]['rcpt'] = $match[1];
                                            break;
                                        case preg_match('/^R.+<(.+)>$/', $line, $match):
                                            $output[$msgid]['rcpts'][] = $match[1];
                                            break;
                                        case preg_match('/^S<(.+)>$/', $line, $match):
                                            $output[$msgid]['sender'] = $match[1];
                                            break;
                                        case preg_match('/^T(.+)$/', $line, $match):
                                            $ctime = getdate($match[1]);
                                            $output[$msgid]['cdate'] = $ctime['year'] . "-" . str_pad(
                                                    $ctime['mon'],
                                                    2,
                                                    "0",
                                                    STR_PAD_LEFT
                                                ) . "-" . str_pad($ctime['mday'], 2, "0", STR_PAD_LEFT);
                                            $output[$msgid]['ctime'] = str_pad(
                                                    $ctime['hours'],
                                                    2,
                                                    "0",
                                                    STR_PAD_LEFT
                                                ) . ":" . str_pad(
                                                    $ctime['minutes'],
                                                    2,
                                                    "0",
                                                    STR_PAD_LEFT
                                                ) . ":" . str_pad($ctime['seconds'], 2, "0", STR_PAD_LEFT);
                                            break;
                                        case preg_match('/^P(.+)$/', $line, $match):
                                            $output[$msgid]['priority'] = $match[1];
                                            break;
                                        case preg_match('/^M(.+)$/', $line, $match):
                                            $output[$msgid]['message'] = $match[1];
                                            break;
                                        case preg_match('/^F(.+)$/', $line, $match):
                                            $output[$msgid]['flags'] = $match[1];
                                            break;
                                        case preg_match('/^N(.+)$/', $line, $match):
                                            $output[$msgid]['attempts'] = $match[1];
                                            break;
                                        case preg_match('/^K(.+)$/', $line, $match):
                                            $output[$msgid]['lastattempttime'] = $match[1];
                                            break;
                                        case preg_match('/^Z(.+)$/', $line, $match):
                                            $output[$msgid]['envelopesender'] = $match[1];
                                            break;
                                        case preg_match('/Subject: (.+)$/', $line, $match):
                                            $output[$msgid]['subject'] = $match[1];
                                            break;
                                    }
                                }
                            }
                            fclose($fh);
                        }
                    }
                }
            }
        }
        closedir($dh);
        // Get our hostname
        $sys_hostname = chop(`hostname`);
        // Drop everything from the table first
        dbquery("DELETE FROM " . $table_name . " WHERE hostname='" . $sys_hostname . "'");
        if (!empty($output)) {
            foreach ($output as $msgid => $msginfo) {
                // Insert each record
                $sql = "INSERT INTO " . $table_name . "
    (id,
     cdate,
     ctime,
     from_address,
     to_address,
     subject,
     message,
     size,
     priority,
     attempts,
     lastattempt,
     hostname)
    VALUES
    ('" . mysql_real_escape_string($msgid) . "','" .
                    mysql_real_escape_string($msginfo['cdate']) . "','" .
                    mysql_real_escape_string($msginfo['ctime']) . "','" .
                    mysql_real_escape_string($msginfo['sender']) . "','" .
                    mysql_real_escape_string(@join(",", $msginfo['rcpts'])) . "','" .
                    mysql_real_escape_string($msginfo['subject']) . "','" .
                    mysql_real_escape_string($msginfo['message']) . "','" .
                    mysql_real_escape_string($msginfo['size']) . "','" .
                    mysql_real_escape_string($msginfo['priority']) . "','" .
                    mysql_real_escape_string($msginfo['attempts']) . "','" .
                    mysql_real_escape_string($msginfo['lastattempttime']) . "','" .
                    mysql_real_escape_string($sys_hostname) . "')";
                dbquery($sql);
            }
        }
    }
    // Unlock the file
    flock($fl, LOCK_UN);
} else {
    // Lock was not successful - drop out
    // echo "Unable to lock - not running.\n";
}
// Close the file
fclose($fl);
