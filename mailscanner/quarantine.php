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

require_once __DIR__ . '/functions.php';

require __DIR__ . '/login.function.php';

html_start(__('qviewer08'), 0, false, false);

if (!isset($_GET['dir'])) {
    // Get the top-level list
    if (QUARANTINE_USE_FLAG) {
        // Don't use the database any more - it's too slow on big datasets
        $dates = return_quarantine_dates();
        echo '<table class="mail" cellspacing="2" align="center">' . "\n";
        echo '<tr><th colspan=2>' . __('folder08') . '</th></tr>' . "\n";
        foreach ($dates as $date) {
            $sql = 'SELECT id FROM maillog WHERE ' . $_SESSION['global_filter'] . " AND date='$date' AND quarantined=1";
            $result = dbquery($sql);
            $rowcnt = $result->num_rows;
            $rowstr = '  ----------';
            if ($rowcnt > 0) {
                $rowstr = sprintf('  %02d ' . __('items08'), $rowcnt);
            }
            echo '<tr><td align="right"><a href="quarantine.php?token=' . $_SESSION['token'] . '&amp;dir=' . $date . '">' . translateQuarantineDate(
                $date,
                DATE_FORMAT
            ) .  '</a></td>' . "\n";
            echo '<td align="left">' . $rowstr . '</a></td></tr>' . "\n";
        }
        echo '</table>' . "\n";
    } else {
        $items = quarantine_list('/');
        if (count($items) > 0) {
            // Sort in reverse chronological order
            arsort($items);
            echo '<table class="mail" width="100%" cellspacing="2" align="center">' . "\n";
            echo '<tr><th colspan=2>' . __('folder_0308') . '</th></tr>' . "\n";
            $count = 0;
            foreach ($items as $f) {
                //To look and see if any of the folders in the quarantine folder are strings and not numbers.
                if (is_numeric($f)) {
                    // Display the Quarantine folders and create links for them.
                    echo '<tr><td align="center"><a href="quarantine.php?token=' . $_SESSION['token'] . '&amp;dir=' . $f . '">' . translateQuarantineDate(
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
            die(__('dienodir08') . "\n");
        }
    }
} else {
    if (false === checkToken($_GET['token'])) {
        header('Location: login.php?error=pagetimeout');
        die();
    }
    $dir = deepSanitizeInput($_GET['dir'], 'url');
    if (!validateInput($dir, 'quardir')) {
        die(__('dievalidate99'));
    }

    if (isset($_GET['pageID']) && !validateInput(deepSanitizeInput($_GET['pageID'], 'num'), 'num')) {
        die(__('dievalidate99'));
    }
    
    if (QUARANTINE_USE_FLAG) {
        dbconn();
        $date = translateQuarantineDate($dir, 'sql');
        $sql = "
SELECT
 id AS id2,
 DATE_FORMAT(timestamp, '" . DATE_FORMAT . ' ' . TIME_FORMAT . "') AS datetime,
 from_address,";
        if (defined('DISPLAY_IP') && DISPLAY_IP) {
            $sql .= 'clientip,';
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
 released,
 '' as status
FROM
 maillog
WHERE
 " . $_SESSION['global_filter'] . "
AND
 date = '$date'
AND
 quarantined = 1";

        // Hide high spam/mcp from regular users if enabled
        if (defined('HIDE_HIGH_SPAM') && HIDE_HIGH_SPAM === true && $_SESSION['user_type'] === 'U') {
            $sql .= '
    AND
     ishighspam=0
    AND
     COALESCE(ishighmcp,0)=0';
        }

        $sql .= '
ORDER BY
 date DESC, time DESC';
        db_colorised_table($sql, __('folder08') . ' ' . translateQuarantineDate($dir, DATE_FORMAT), true, true);
    } else {
        // SECURITY: trim off any potential nasties
        $dir = preg_replace('[\.|\.\.|\/]', '', $dir);
        $items = quarantine_list($dir);
        // Build list of message id's to be used in SQL statement
        if (count($items) > 0) {
            $msg_ids = implode($items, ',');
            $date = safe_value(translateQuarantineDate($dir, 'sql'));
            $sql = "
  SELECT
   id AS id2,
   DATE_FORMAT(timestamp, '" . DATE_FORMAT . ' ' . TIME_FORMAT . "') AS datetime,
   from_address,";
            if (defined('DISPLAY_IP') && DISPLAY_IP) {
                $sql .= 'clientip,';
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
   released,
   '' as status
  FROM
   maillog
  WHERE
   " . $_SESSION['global_filter'] . "
  AND
   date = '$date'
  AND
   BINARY id IN ($msg_ids)";

            // Hide high spam/mcp from regular users if enabled
            if (defined('HIDE_HIGH_SPAM') && HIDE_HIGH_SPAM === true && $_SESSION['user_type'] === 'U') {
                $sql .= '
    AND
     ishighspam=0
    AND
     COALESCE(ishighmcp,0)=0';
            }

            $sql .= '
  ORDER BY
   date DESC, time DESC
  ';
            db_colorised_table($sql, __('folder_0208') . __('colon99') . ' ' . translateQuarantineDate($dir), true, true);
        } else {
            echo __('dienodir08') . "\n";
        }
    }
}

html_end();
dbclose();
