#!/usr/bin/php -q
<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2016  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
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

// Change the following to reflect the location of functions.php
require_once('/var/www/html/mailscanner/functions.php');

$required_constant = array(
    'QUARANTINE_REPORT_DAYS',
    'QUARANTINE_REPORT_HOSTURL',
    'QUARANTINE_DAYS_TO_KEEP',
    'QUARANTINE_REPORT_FROM_NAME',
    'QUARANTINE_FROM_ADDR',
    'QUARANTINE_REPORT_SUBJECT',
    'MAILWATCH_HOME',
    'QUARANTINE_MAIL_HOST',
    'FROMTO_MAXLEN',
    'SUBJECT_MAXLEN',
    'TIME_ZONE',
    'DATE_FORMAT',
    'TIME_FORMAT'
);
$required_constant_missing_count = 0;
foreach ($required_constant as $constant) {
    if (!defined($constant)) {
        echo "The variable $constant is empty, please set a value in conf.php.\n";
        $required_constant_missing_count++;
    }
}
if ($required_constant_missing_count == 0) {
    require_once('Mail.php');
    require_once('Mail/mime.php');
    date_default_timezone_set(TIME_ZONE);

    ini_set('html_errors', 'off');
    ini_set('display_errors', 'on');
    ini_set('implicit_flush', 'false');
    ini_set("memory_limit", '256M');
    ini_set("error_reporting", E_ALL);
    ini_set("max_execution_time", 0);

    /*
    ** HTML Template
    */

    $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
 <title>Message Quarantine Report</title>
 <style type="text/css">
 <!--
  body, td, tr {
  font-family: sans-serif;
  font-size: 8pt;
 }
 -->
 </style>
</head>
<body style="margin: 5px;">

<!-- Outer table -->
<table width="100%%" border="0">
 <tr>
  <td><img src="mailwatch-logo.png"/></td>
  <td align="center" valign="middle">
   <h2>Quarantine Report for %s</h2>
   In the last %s day(s) you have received %s e-mails that have been quarantined and are listed below. All messages in the quarantine are automatically deleted %s days after the date that they were received.
  </td>
 </tr>
 <tr>
  <td colspan="2">%s</td>
 </tr>
</table>
</body>
</html>';

    $html_table = '<table width="100%%" border="0">
 <tr>
  <td style="background-color: #F7CE4A"><b>Received</b></td>
  <td style="background-color: #F7CE4A"><b>To</b></td>
  <td style="background-color: #F7CE4A"><b>From</b></td>
  <td style="background-color: #F7CE4A"><b>Subject</b></td>
  <td style="background-color: #F7CE4A"><b>Reason</b></td>
  <td style="background-color: #F7CE4A"><b>Action</b></td>
 </tr>
%s
</table>';

    $html_content = ' <tr>
  <td style="background-color: #EBEBEB">%s</td>
  <td style="background-color: #EBEBEB">%s</td>
  <td style="background-color: #EBEBEB">%s</td>
  <td style="background-color: #EBEBEB">%s</td>
  <td style="background-color: #EBEBEB">%s</td>
  <td style="background-color: #EBEBEB">%s</td>
 </tr>
';

    /*
    ** Text Template
    */

    $text = 'Quarantine Report for %s

In the last %s day(s) you have received %s e-mails that have been quarantined and are listed below. All messages in the quarantine are automatically deleted %s days after the date that they were received.

%s';

    $text_content = 'Received: %s
From: %s
Subject: %s
Reason: %s
Action:
%s

';

    /*
    ** SQL Templates
    */

    $users_sql = "
SELECT
 username,
 quarantine_rcpt,
 type
FROM
 users
WHERE
 quarantine_report=1
";

    $filters_sql = "
SELECT
 filter
FROM
 user_filters
WHERE
 username=%s
AND
 active='Y'
";

    $sql = "
SELECT DISTINCT
a.id AS id,
DATE_FORMAT(timestamp,'" . str_replace('%', '%%', DATE_FORMAT) . " <br/>" . str_replace('%', '%%', TIME_FORMAT) . "') AS datetime,
a.to_address AS to_address,
a.from_address AS from_address,
a.subject AS subject,
CASE
 WHEN a.virusinfected>0 THEN 'Virus'
 WHEN a.nameinfected>0 THEN 'Bad Content'
 WHEN a.otherinfected>0 THEN 'Infected'
 WHEN a.ishighspam>0 THEN 'Spam'
 WHEN a.issaspam>0 THEN 'Spam'
 WHEN a.isrblspam>0 THEN 'Spam'
 WHEN a.spamblacklisted>0 THEN 'Blacklisted'
 WHEN a.isspam THEN 'Spam'
 WHEN a.ismcp>0 THEN 'Policy'
 WHEN a.ishighmcp>0 THEN 'Policy'
 WHEN a.issamcp>0 THEN 'Policy'
 WHEN a.mcpblacklisted>0 THEN 'Policy'
 WHEN a.isspam>0 THEN 'Spam'
 ELSE 'UNKNOWN'
END AS reason
FROM
 maillog a
WHERE
 a.quarantined = 1
AND
 ((to_address=%s) OR (to_domain=%s))
AND
 a.date >= DATE_SUB(CURRENT_DATE(), INTERVAL " . QUARANTINE_REPORT_DAYS . " DAY)";

    // Hide high spam/mcp from users if enabled
    if (defined('HIDE_HIGH_SPAM') && HIDE_HIGH_SPAM === true) {
        $sql .= "
    AND
     ishighspam=0
    AND
     COALESCE(ishighmcp,0)=0";
    }

    if (defined('HIDE_NON_SPAM') && HIDE_NON_SPAM === true) {
        $sql .= "
    AND
    isspam>0";
    }

    if (defined('HIDE_UNKNOWN') && HIDE_UNKNOWN === true) {
        $sql .= "
	AND
	(
	virusinfected>0
	OR
	nameinfected>0
	OR
	otherinfected>0
	OR
	ishighspam>0
	OR
	isaspam>0
	OR
	isrblspam>0
	OR
	spamblacklisted>0
	OR
	isspam>0
	OR
	ismcp>0
	OR
	ishighmcp>0
	OR
	issamcp>0
	OR
	ismcpblacklisted>0
	OR
	isspam>0
	)";
    }

    $sql .= "
ORDER BY a.date DESC, a.time DESC";

    $result = dbquery($users_sql);
    $rows = mysql_num_rows($result);
    if ($rows > 0) {
        while ($user = mysql_fetch_object($result)) {
            dbg("\n === Generating report for " . $user->username . " type=" . $user->type);
            // Work out destination e-mail address
            switch ($user->type) {
                case 'U':
                    // Type: user - see if to address needs to be overridden
                    if (!empty($user->quarantine_rcpt)) {
                        $email = $user->quarantine_rcpt;
                    } else {
                        $email = $user->username;
                    }
                    $to_address = $user->username;
                    $to_domain = $user->username;
                    break;
                case 'D':
                    // Type: domain admin - this must be overridden
                    $email = $user->quarantine_rcpt;
                    $to_address = $user->username;
                    if (preg_match('/(\S+)@(\S+)/', $user->username, $split)) {
                        $to_domain = $split[2];
                    } else {
                        $to_domain = $user->username;
                    }
                    break;
                default:
                    // Shouldn't ever get here - but just in case...
                    $email = $user->quarantine_rcpt;
                    $to_address = $user->username;
                    $to_domain = $user->username;
                    break;
            }
            // Make sure we have a destination address
            if (!empty($email)) {
                dbg(" ==== Recipient e-mail address is $email");
                // Get any additional reports required
                $filters = array_merge(array($email), return_user_filters($user->username));
                foreach ($filters as $filter) {
                    dbg(" ==== Building list for $filter");
                    $quarantined = return_quarantine_list_array($filter, $to_domain);
                    dbg(" ==== Found " . count($quarantined) . " quarantined e-mails");
                    //print_r($quarantined);
                    if (count($quarantined) > 0) {
                        send_quarantine_email($email, $filter, $quarantined);
                    }
                    unset($quarantined);
                }
            } else {
                dbg(" ==== " . $user->username . " has empty e-mail recipient address, skipping...");
            }
        }
    }
}

function dbg($text)
{
    echo $text . "\n";
}

function return_user_filters($user)
{
    global $filters_sql;
    $result = dbquery(sprintf($filters_sql, quote_smart($user)));
    $rows = mysql_num_rows($result);
    $array = array();
    if ($rows > 0) {
        while ($row = mysql_fetch_object($result)) {
            $array[] = $row->filter;
        }
    }
    return $array;
}

function return_quarantine_list_array($to_address, $to_domain)
{
    global $sql;
    $result = dbquery(sprintf($sql, quote_smart($to_address), quote_smart($to_domain)));
    $rows = mysql_num_rows($result);
    $array = array();
    if ($rows > 0) {
        while ($row = mysql_fetch_object($result)) {
            $array[] = array(
                'id' => trim($row->id),
                'datetime' => trim($row->datetime),
                'to' => trim_output($row->to_address, FROMTO_MAXLEN),
                'from' => trim_output($row->from_address, FROMTO_MAXLEN),
                'subject' => trim_output($row->subject, SUBJECT_MAXLEN),
                'reason' => trim($row->reason)
            );
        }
    }
    return $array;
}

function get_random_string($count)
{
    $bytes = openssl_random_pseudo_bytes($count);
    return bin2hex($bytes);
}

function store_auto_release($qitem)
{
    $id = $qitem['id'];
    $rand = $qitem['rand'];
    $result = dbquery("INSERT INTO autorelease (msg_id,uid) VALUES ('$id','$rand')");
    if (!$result) {
        dbg(" ==== Error generating auto_release....skipping...");
        return false;
    } else {
        return true;
    }
}

function check_auto_release($qitem)
{
    //function checks if message already has an autorelease entry
    $id = $qitem['id'];
    $result = dbquery("SELECT * FROM autorelease WHERE msg_id = '$id'");
    if (!$result) {
        dbg(" === Error checking if msg_id already exists.....skipping....");
    } else {
        if (mysql_num_rows($result) == 0) {
            return false;//msg_id not found,
        } elseif (mysql_num_rows($result) == 1) {
            $row = mysql_fetch_array($result);
            $rand = $row['uid'];
            return $rand; //return the stored uid
        } else {
            dbg("=== Error, msg_id exists more than once....generating new one...");
            return false;
        }
    }
}

function send_quarantine_email($email, $filter, $quarantined)
{
    global $html, $html_table, $html_content, $text, $text_content;
    // Setup variables to prevent warnings
    $h1 = "";
    $t1 = "";
    // Build the quarantine list for this recipient
    foreach ($quarantined as $qitem) {
        //Check if auto-release is enabled
        if (defined('AUTO_RELEASE') && AUTO_RELEASE === true) {
            //Check if email already has an autorelease entry
            $exists = check_auto_release($qitem);
            if (!$exists) {
                $qitem['rand'] = get_random_string(10);
                $auto_release = store_auto_release($qitem);
            } else {
                $qitem['rand'] = $exists;
                $auto_release = true;
            }
            if ($auto_release) {
                $links = '<a href="' . QUARANTINE_REPORT_HOSTURL . '/viewmail.php?id=' . $qitem['id'] . '">'.__('arview01').'</a>  <a href="' . QUARANTINE_REPORT_HOSTURL . '/auto-release.php?mid=' . $qitem['id'] . '&r=' . $qitem['rand'] . '">'.__('arrelease01').'</a>';
            } else {
                $links = '<a href="' . QUARANTINE_REPORT_HOSTURL . '/viewmail.php?id=' . $qitem['id'] . '">'.__('arview01').'</a>';
            }
        } else {
            //auto-release disabled
            $links = '<a href="' . QUARANTINE_REPORT_HOSTURL . '/viewmail.php?id=' . $qitem['id'] . '">'.__('arview01').'</a>';
        }

        // HTML Version
        $h1 .= sprintf(
            $html_content,
            $qitem['datetime'],
            $qitem['to'],
            $qitem['from'],
            $qitem['subject'],
            $qitem['reason'],
            $links
        );
        // Text Version
        $t1 .= sprintf(
            $text_content,
            strip_tags($qitem['datetime']),
            $qitem['to'],
            $qitem['from'],
            $qitem['subject'],
            $qitem['reason'],
            $links
        );
    }

    // HTML
    $h2 = sprintf($html_table, $h1);
    $html_report = sprintf($html, $filter, QUARANTINE_REPORT_DAYS, count($quarantined), QUARANTINE_DAYS_TO_KEEP, $h2);
    if (DEBUG) {
        echo $html_report;
    }

    // Text
    $text_report = sprintf($text, $filter, QUARANTINE_REPORT_DAYS, count($quarantined), QUARANTINE_DAYS_TO_KEEP, $t1);
    if (DEBUG) {
        echo "<pre>$text_report</pre>\n";
    }

    // Send e-mail
    $mime = new Mail_mime("\n");
    $hdrs = array(
        'From' => QUARANTINE_REPORT_FROM_NAME . ' <' . QUARANTINE_FROM_ADDR . '>',
        'To' => $email,
        'Subject' => QUARANTINE_REPORT_SUBJECT,
        'Date' => date("r")
    );
    $mime->addHTMLImage(MAILWATCH_HOME . '/images/mailwatch-logo.png', 'image/png', 'mailwatch-logo.png', true);
    $mime->setTXTBody($text_report);
    $mime->setHTMLBody($html_report);
    $body = $mime->get();
    $hdrs = $mime->headers($hdrs);
    $mail_param = array('host' => QUARANTINE_MAIL_HOST);
    $mail =& Mail::factory('smtp', $mail_param);
    $mail->send($email, $hdrs, $body);
    dbg(" ==== Sent e-mail to $email");
}
