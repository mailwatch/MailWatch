<?php

/*
 * Mailwatch for Mailscanner Modification
 * Author: Alan Urquhart - ASU Web Services Ltd
 * Version: 1.1.1
 * Updated: 31-08-2016
 *
 * Requires: Mailwatch 1.2.0
 *
 * Provides the mechanism for one click release of quarantined emails as reported by the quarantine_report.php cron
 *
 * SETUP:
 *
 * Create the following table in the mailscanner database:
 * CREATE TABLE `autorelease` (
 *  `id` bigint(20) NOT NULL AUTO_INCREMENT,
 *  `msg_id` varchar(255) NOT_NULL,
 *  `uid` varchar(255) NOT_NULL,
 *  PRIMARY_KEY (`id`)
 * );
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
        echo __('dberror59');
    }
    if (mysql_num_rows($result) == 0) {
        echo "<p>". __('msgnotfound159')."</p>";
        echo "<p>". __('msgnotfound259').$mid." ". __('msgnotfound359')."</p>";
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
            echo "<p>". __('msgreleased59'). "</p>";
            //cleanup
            $releaseID = $row['id'];
            $query = "DELETE FROM autorelease WHERE id = '$releaseID'";
            $result = dbquery($query);
            if (!$result) {
                dbg("ERROR cleaning up database... " . mysql_error());
            }
        } else {
            echo __('tokenmismatch59');
        }
    }
} else {
    echo __('notallowed59');
}
