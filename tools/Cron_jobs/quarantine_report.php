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
    if (version_compare(phpversion(), '5.3.0', '<')) {
        error_reporting(E_ALL ^ E_STRICT);
    } else {
        // E_DEPRECATED added in PHP 5.3
        error_reporting(E_ALL ^ E_STRICT ^ E_DEPRECATED);
    }

    /*
    ** HTML Template
    */

    $html = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
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
<body marginheight="5" marginwidth="5" topmargin="0" leftmargin="0">

<!-- Outer table -->
<table width=100%% border="0">
 <tr>
  <td><img src="mailwatch-logo.png"/></td>
  <td align="center" valign="middle">
   <h2>Quarantine Report for %s</h2>
   In the last %s day(s) you have received %s e-mails that have been quarantined and are listed below.  All messages in the quarantine are automatically deleted %s days after the date that they were received.
  </td>
 </tr>
 <tr>
  <td colspan=2>%s</td>
 </tr>
</table>
</body>
</html>';

    $html_table = '<table width="100%%" border="0">
 <tr>
  <td bgcolor="#F7CE4A"><b>Received</b></td>
  <td bgcolor="#F7CE4A"><b>From</b></td>
  <td bgcolor="#F7CE4A"><b>Subject</b></td>
  <td bgcolor="#F7CE4A"><b>Reason</b></td>
  <td bgcolor="#F7CE4A"><b>Action</b></td>
 </tr>
%s
</table>';

    $html_content = ' <tr>
  <td bgcolor="#EBEBEB">%s</td>
  <td bgcolor="#EBEBEB">%s</td>
  <td bgcolor="#EBEBEB">%s</td>
  <td bgcolor="#EBEBEB">%s</td>
  <td bgcolor="#EBEBEB">%s</td>
 </tr>
';

    /*
    ** Text Template
    */

    $text = 'Quarantine Report for %s

In the last %s day(s) you have received %s e-mails that have been quarantined and are listed below.  All messages in the quarantine are automatically deleted %s days after the date that they were received.

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
 a.date >= DATE_SUB(CURRENT_DATE(), INTERVAL " . QUARANTINE_REPORT_DAYS . " DAY)
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
                    break;
                case 'D':
                    // Type: domain admin - this must be overridden
                    $email = $user->quarantine_rcpt;
                    break;
                default:
                    // Shouldn't ever get here - but just in case...
                    $email = $user->quarantine_rcpt;
                    break;
            }
            // Make sure we have a destination address
            if (!empty($email)) {
                dbg(" ==== Recipient e-mail address is $email");
                // Get any additional reports required
                $filters = array_merge(array($user->username), return_user_filters($user->username));
                foreach ($filters as $filter) {
                    dbg(" ==== Building list for $filter");
                    $quarantined = return_quarantine_list_array($filter);
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

function return_quarantine_list_array($filter)
{
    global $sql;
    $result = dbquery(sprintf($sql, quote_smart($filter), quote_smart($filter)));
    $rows = mysql_num_rows($result);
    $array = array();
    if ($rows > 0) {
        while ($row = mysql_fetch_object($result)) {
            $array[] = array(
                'id' => trim($row->id),
                'datetime' => trim($row->datetime),
                'from' => trim_output($row->from_address, FROMTO_MAXLEN),
                'subject' => trim_output($row->subject, SUBJECT_MAXLEN),
                'reason' => trim($row->reason)
            );
        }
    }
    return $array;
}

function send_quarantine_email($email, $filter, $quarantined)
{
    global $html, $html_table, $html_content, $text, $text_content;
    // Setup variables to prevent warnings
    $h1 = "";
    $t1 = "";
    // Build the quarantine list for this recipient
    foreach ($quarantined as $qitem) {
        // HTML Version
        $h1 .= sprintf(
            $html_content,
            $qitem['datetime'],
            $qitem['from'],
            $qitem['subject'],
            $qitem['reason'],
            '<a href="' . QUARANTINE_REPORT_HOSTURL . '/viewmail.php?id=' . $qitem['id'] . '">View</a>'
        );
        // Text Version
        $t1 .= sprintf(
            $text_content,
            strip_tags($qitem['datetime']),
            $qitem['from'],
            $qitem['subject'],
            $qitem['reason'],
            '<a href="' . QUARANTINE_REPORT_HOSTURL . '/viewmail.php?id=' . $qitem['id'] . '">View</a>'
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
        echo "<PRE>$text_report</PRE>\n";
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
