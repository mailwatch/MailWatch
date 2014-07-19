<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)

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

require_once("./functions.php");

session_start();
require('login.function.php');

html_start("Quarantine Viewer", 0, false, false);

if (!isset($_GET['dir']) && !isset($_GET["msgdir"])) {
    // Get the top-level list
    if (QUARANTINE_USE_FLAG) {
        // Don't use the database any more - it's too slow on big datasets
        $dates = return_quarantine_dates();
        echo '<table class="mail" cellspacing="2" align="center">' . "\n";
        echo '<tr><th>Folder</th></tr>' . "\n";
        foreach ($dates as $date) {
            $sql = "SELECT id FROM maillog WHERE " . $_SESSION['global_filter'] . " AND date='$date' AND quarantined=1";
            $result = dbquery($sql);
            $rowcnt = mysql_num_rows($result);
            $rowstr = " - ----------";
            if ($rowcnt > 0) {
                $rowstr = sprintf(" - %02d items", $rowcnt);
            }
            echo '<tr><td align="center"><a href="' . $_SERVER['PHP_SELF'] . '?dir=' . $date . '">' . translateQuarantineDate(
                    $date,
                    DATE_FORMAT
                ) . $rowstr . '</a></td></tr>' . "\n";
        }
        echo '</table>' . "\n";
    } else {
        $items = quarantine_list('/');
        if (count($items) > 0) {
            // Sort in reverse chronological order
            arsort($items);
            echo '<table class="mail" cellspacing="1" align="center">' . "\n";
            echo '<tr><th>Folder</th></tr>' . "\n";
            $count = 0;
            foreach ($items as $f) {
                //To look and see if any of the folders in the quarantine folder are strings and not numbers.
                if (is_numeric($f)) {
                    // Display the Quarantine folders and create links for them.
                    echo '<tr><td align="center"><a href="' . $_SERVER['PHP_SELF'] . '?dir=' . $f . '">' . translateQuarantineDate(
                            $f,
                            DATE_FORMAT
                        ) . '</a></td></tr>' . "\n";
                    // Skip any folders that are not dates and
                } else {
                    continue;
                }
            }
            echo '</table>' . "\n";
        } else {
            die("No quarantine directories found\n");
        }
    }
} else {
    if (QUARANTINE_USE_FLAG) {
        dbconn();
        $date = mysql_real_escape_string(translateQuarantineDate($_GET['dir'], 'sql'));
        $sql = "
SELECT
 id AS id2,
 DATE_FORMAT(timestamp, '" . DATE_FORMAT . " " . TIME_FORMAT . "') AS datetime,
 from_address,";
        if (defined('DISPLAY_IP') && DISPLAY_IP) {
            $sql .= "clientip,";
        }
        $sql .= "
 to_address,
 subject,
 size,
 sascore,
 isspam,
 ishighspam,
 spamwhitelisted,
 spamblacklisted,
 virusinfected,
 nameinfected,
 otherinfected,
 report,
 ismcp,
 ishighmcp,
 issamcp,
 mcpwhitelisted,
 mcpblacklisted,
 mcpsascore,
 '' as status
FROM
 maillog
WHERE
 " . $_SESSION['global_filter'] . "
AND
 date = '$date'
AND
 quarantined = 1
ORDER BY
 date DESC, time DESC";
        db_colorised_table($sql, 'Folder: ' . translateQuarantineDate($_GET['dir'], DATE_FORMAT), true, true);
    } else {
        // SECURITY: trim off any potential nasties
        $_GET['dir'] = preg_replace('[\.|\.\.|\/]', '', $_GET['dir']);
        $items = quarantine_list($_GET['dir']);
        // Build list of message id's to be used in SQL statement
        if (count($items) > 0) {
            $msg_ids = join($items, ",");
            $date = mysql_real_escape_string(translateQuarantineDate($_GET['dir'], 'sql'));
            $sql = "
  SELECT
   id AS id2,
   DATE_FORMAT(timestamp, '" . DATE_FORMAT . " " . TIME_FORMAT . "') AS datetime,
   from_address,";
            if (defined('DISPLAY_IP') && DISPLAY_IP) {
                $sql .= "clientip,";
            }
            $sql .= "
   to_address,
   subject,
   size,
   sascore,
   isspam,
   ishighspam,
   spamwhitelisted,
   spamblacklisted,
   virusinfected,
   nameinfected,
   otherinfected,
   report,
   ismcp,
   ishighmcp,
   issamcp,
   mcpwhitelisted,
   mcpblacklisted,
   mcpsascore,
   '' as status
  FROM
   maillog
  WHERE
   " . $_SESSION['global_filter'] . "
  AND
   date = '$date'
  AND
   BINARY id IN ($msg_ids)
  ORDER BY
   date DESC, time DESC
  ";
            db_colorised_table($sql, 'Folder: ' . translateQuarantineDate($_GET['dir']), true, true);
        } else {
            echo "No quarantined messages found\n";
        }
    }
}

html_end();
dbclose();
