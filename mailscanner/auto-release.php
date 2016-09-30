<?php

/*
 * Mailwatch for Mailscanner Modification
 * Author: Alan Urquhart - ASU Web Services Ltd
 * Version: 1.2
 * Updated: 30-09-2016
 *
 * Requires: Mailwatch 1.2.0
 *
 * Provides the mechanism for one click release of quarantined emails as reported by the quarantine_report.php cron
 *
 * Changelog:
 *
 * V1.2 - 30-09-2016
 * Fixes table definition - GitHub Issue #291 (https://github.com/mailwatch/1.2.0/issues/291)
 *
 * SETUP:
 *
 * Create the following table in the mailscanner database:
 * CREATE TABLE IF NOT EXISTS `autorelease` (
 * `id` bigint(20) NOT NULL AUTO_INCREMENT,
 * `msg_id` varchar(255) COLLATE utf8_general_ci NOT NULL,
 * `uid` varchar(255) COLLATE utf8_general_ci NOT NULL,
 * PRIMARY KEY (`id`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;
 *
 * Update cron.daily/quarantine_report.php with the modified file
 * Update cron.daily/quarantine_maint.php with the modified file
 *
 */
require_once(__DIR__ . '/functions.php');
if (isset($_GET['mid']) && isset($_GET['r'])) {
    dbconn();
    $mid = mysql_real_escape_string($_GET['mid']);
    $token = mysql_real_escape_string($_GET['r']);
    $sql = "SELECT * FROM autorelease WHERE msg_id = '$mid'";
    $result = dbquery($sql);
    if (!$result) {
        dbg("Error fetching from database" . mysql_error());
        echo __('dberror99');
    }
    if (mysql_num_rows($result) == 0) {
        echo "<p>". __('msgnotfound1')."</p>";
        echo "<p>". __('msgnotfound2').$mid." ". __('msgnotfound3')."</p>";
    } else {
        $row = mysql_fetch_assoc($result);
        if ($row['uid'] == $token) {
            $list = quarantine_list_items($mid);
            $result = '';
            if (count($list) == 1) {
                $to = $list[0]['to'];
                $result = quarantine_release($list, array(0), $to);
            } else {
                for ($i = 0; $i < count($list); $i++) {
                    if (preg_match('/message\/rfc822/', $list[$i]['type'])) {
                        $result = quarantine_release($list, array($i), $list[$i]['to']);
                    }
                }
            }


            // Display success
            echo "<p>". __('msgreleased1'). "</p>";
            //cleanup
            $releaseID = $row['id'];
            $query = "DELETE FROM autorelease WHERE id = '$releaseID'";
            $result = dbquery($query);
            if (!$result) {
                dbg("ERROR cleaning up database... " . mysql_error());
            }
        } else {
            echo __('tokenmismatch1');
        }
    }
} else {
    echo __('notallowed99');
}
