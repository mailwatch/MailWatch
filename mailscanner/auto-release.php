<?php

/*
 * Mailwatch for Mailscanner Modification
 * Author: Alan Urquhart - ASU Web Services Ltd
 * Version: 1.0
 * Date: 12-04-2016
 *
 * Requires: Mailwatch 1.2.0
 *
 * Provides the mechanism for one click release of quarantined emails as reported by the quarantine_report.php cron
 *
 * SETUP:
 *
 * Create the following table in the mailscanner database:
 * CREATE TABLE `mod_release` (
 *  `id` int(11) NOT NULL AUTO_INCREMENT,
 *  `msg_id` varchar(255) NOT_NULL,
 *  `uid` varchar(255) NOT_NULL,
 *  PRIMARY_KEY (`id`)
 * );
 *
 * Update cron.daily/quarantine_report.php with the modified file
 * Update cron.daily/quarantine_maint.php with the modified file
 *
 * Create a new database user with limited privileges on the mod_release table - minimum is SELECT,DELETE
 * Enter database credentials below.
 */
if (isset($_GET['mid']) && isset($_GET['r'])) {
    // Change the following to reflect the location of functions.php
    require_once('/var/www/html/mailscanner/functions.php');
    //Database Credentials
    $host = 'localhost';//change if using a remote db
    $user = '';
    $pass = '';
    $database = 'mailscanner'; //change is your database is called something else
    $db = mysqli_connect($host, $user, $pass, $database) or die("CONNECT ERROR" . mysqli_connect_error());
    $mid = mysqli_real_escape_string($db, $_GET['mid']);
    $token = mysqli_real_escape_string($db, $_GET['r']);
    $query = "SELECT * FROM mod_release WHERE msg_id = '$mid'";
    $result = mysqli_query($db, $query);
    if (!$result) die("Error fetching from database");
    if (mysqli_num_rows($result) == 0) {
        echo "<p>Message not found.  You may have already released this message.</p>
<p>Please contact your email administrator and provide them with this message ID: ".$mid." if you need this message released</p> ";
    } else {
        $row = mysqli_fetch_assoc($result);
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
            echo "<p>Message released<br>It may take a few minutes to appear in your inbox.</p>";
            //cleanup
            $releaseID = $row['id'];
            $query = "DELETE FROM mod_release WHERE id = '$releaseID'";
            $result = mysqli_query($db, $query);
            if (!$result) die('ERROR cleaning up database... ' . mysqli_error($db));
        } else echo "Error releasing message - token missmatch";
    }
} else {
    echo "You are not allowed to be here!";
}
?>

