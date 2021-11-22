<?php

/*
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

ini_set('error_log', 'syslog');
ini_set('html_errors', 'off');
ini_set('display_errors', 'on');
ini_set('implicit_flush', 'false');

// Edit if you changed webapp directory from default
$pathToFunctions = '/var/www/html/mailscanner/functions.php';
if (!@is_file($pathToFunctions)) {
    exit('Error: Cannot find functions.php file in "' . $pathToFunctions . '": edit ' . __FILE__ . ' and set the right path on line ' . (__LINE__ - 3) . PHP_EOL);
}
require $pathToFunctions;

// Set-up environment
set_time_limit(0);
// Limit to how long a queue id remains in queue before dropping
define('QUEUETIMEOUT', '300');
define('QUERYTIMEOUT', '60');

$idqueue = [];

function doit($input)
{
    global $fp;
    if (!$fp = popen($input, 'r')) {
        exit(__('diepipe54'));
    }

    while ($line = fgets($fp, 2096)) {
        process_entries($line);
    }

    pclose($fp);
}

function follow($file)
{
    $size = filesize($file);
    while (true) {
        clearstatcache();
        $currentSize = filesize($file);
        if ($size == $currentSize) {
            sleep(1);
            continue;
        }

        $fh = fopen($file, 'r');
        if (!$fh) {
            exit(__('diepipe56'));
        }
        fseek($fh, $size);

        while ($line = fgets($fh)) {
            process_entries($line);
        }

        fclose($fh);
        $size = $currentSize;
    }
}

// Loop through log, looking for message-ids and matching to addresses
// Populates $idqueue 2 dimensional array,
// first element is entry for a queue id
// second element are properties of the entry
// [0] -- queue id
// [1] -- message-id
// [2] -- timestamp in epoch
function process_entries($line)
{
    global $idqueue;

    // Watch for message-id's
    if (preg_match('/^.*postfix\/cleanup.*: (\S+): message-id=(\S+)$/', $line, $explode)) {
        // Add to queue and timestamp it
        $arrEntry = [];
        array_push($arrEntry, $explode[1]);
        array_push($arrEntry, $explode[2]);
        array_push($arrEntry, time());
        array_push($idqueue, $arrEntry);

    // Watch for verifications
    } elseif (preg_match('/^.*postfix\/smtp.*: (\S+):.*status=(?:deliverable|undeliverable)/', $line, $id)) {
        remove_entry($id[1]);

    // Watch for milter connections
    } elseif (preg_match('/^.*postfix\/cleanup.*: (\S+): milter/', $line, $id)) {
        remove_entry($id[1]);

    // Watch for entries removed from queue
    } elseif (preg_match('/^.*postfix\/qmgr.*: (\S+): removed$/', $line, $id)) {
        remove_entry($id[1]);

    // Watch for deliver attempts (after verification check above)
    } elseif (preg_match('/^.*postfix\/smtp.*: (\S+): to=\<(\S+)\>,/', $line, $explode)) {
        for ($i = 0; $i < count($idqueue); ++$i) {
            if (time() > $idqueue[$i][2] + QUEUETIMEOUT) {
                // Drop expired entry from queue
                array_splice($idqueue, $i, 1);
                continue;
            }

            $smtp_id = safe_value($idqueue[$i][0]);
            $smtp_id2 = safe_value($explode[1]);

            if ($smtp_id === $smtp_id2) {
                $message_id = safe_value($idqueue[$i][1]);
                $to = safe_value($explode[2]);
                $smtpd_id = null;

                for ($j = 0; $j < QUERYTIMEOUT; ++$j) {
                    $result = dbquery("SELECT id from `maillog` where messageid='" . $message_id . "' and to_address LIKE '%" . $to . "%' LIMIT 1;");
                    @$smtpd_id = $result->fetch_row()[0];

                    if (null === $smtpd_id) {
                        // Add a small delay to prevent race condition between mailwatch logger db and maillog
                        sleep(1);
                    } else {
                        break;
                    }
                }
                if (null !== $smtpd_id && $smtpd_id !== $smtp_id) {
                    dbquery("REPLACE INTO `mtalog_ids` VALUES ('" . $smtpd_id . "','" . $smtp_id . "')");
                    array_splice($idqueue, $i, 1);
                    break;
                }
            }
        }
    }
}

function remove_entry($id)
{
    global $idqueue;

    // Search queue for id
    for ($i = 0; $i < count($idqueue); ++$i) {
        if (time() > $idqueue[$i][2] + QUEUETIMEOUT) {
            // Drop expired entry from queue
            array_splice($idqueue, $i, 1);
            continue;
        }
        $smtp_id = safe_value($idqueue[$i][0]);
        $smtp_id2 = safe_value($id);
        if ($smtp_id === $smtp_id2) {
            // Drop id from array (milter connection)
            array_splice($idqueue, $i, 1);
            break;
        }
    }
}

if (isset($_SERVER['argv'][1]) && '--refresh' === $_SERVER['argv'][1]) {
    doit('cat ' . MS_LOG);
} else {
    // Refresh first
    doit('cat ' . MS_LOG);
    // Start watching the maillog
    follow(MS_LOG);
}
