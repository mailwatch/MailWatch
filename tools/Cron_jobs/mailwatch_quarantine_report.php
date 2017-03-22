#!/usr/bin/php -q
<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2017  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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

// Edit if you changed webapp directory from default
$pathToFunctions = '/var/www/html/mailscanner/functions.php';
if (!@is_file($pathToFunctions)) {
    die('Error: Cannot find functions.php file in "' . $pathToFunctions . '": edit ' . __FILE__ . ' and set the right path on line ' . (__LINE__ - 3) . PHP_EOL);
}
require $pathToFunctions;

$required_constant = array(
    'QUARANTINE_REPORT_DAYS',
    'MAILWATCH_HOSTURL',
    'QUARANTINE_DAYS_TO_KEEP',
    'QUARANTINE_REPORT_FROM_NAME',
    'MAILWATCH_FROM_ADDR',
    'QUARANTINE_REPORT_SUBJECT',
    'MAILWATCH_HOME',
    'MAILWATCH_MAIL_HOST',
    'FROMTO_MAXLEN',
    'SUBJECT_MAXLEN',
    'TIME_ZONE',
    'DATE_FORMAT',
    'TIME_FORMAT',
    'QUARANTINE_FILTERS_COMBINED'
);
$required_constant_missing_count = 0;
foreach ($required_constant as $constant) {
    if (!defined($constant)) {
        echo sprintf(__('message61'), $constant) . "\n";
        $required_constant_missing_count++;
    }
}
if ($required_constant_missing_count === 0) {
    require_once MAILWATCH_HOME . '/lib/pear/Mail.php';
    require_once MAILWATCH_HOME . '/lib/pear/Mail/smtp.php';
    require_once MAILWATCH_HOME . '/lib/pear/Mail/mime.php';
    date_default_timezone_set(TIME_ZONE);

    ini_set('html_errors', 'off');
    ini_set('display_errors', 'on');
    ini_set('implicit_flush', 'false');
    ini_set('memory_limit', '256M');
    ini_set('error_reporting', E_ALL);
    ini_set('max_execution_time', 0);

    /*
    ** HTML Template
    */

    $html = '<!DOCTYPE html>
<html>
<head>
 <title>' . __('title61') . '</title>
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
  <td><img src="' . MW_LOGO . '"/></td>
  <td align="center" valign="middle">
   <h2>' . __('text611') . '</h2>
   ' . __('text612') . '
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
  <td style="background-color: #F7CE4A"><b>' . __('received61') . '</b></td>
  <td style="background-color: #F7CE4A"><b>' . __('to61') . '</b></td>
  <td style="background-color: #F7CE4A"><b>' . __('from61') . '</b></td>
  <td style="background-color: #F7CE4A"><b>' . __('subject61') . '</b></td>
  <td style="background-color: #F7CE4A"><b>' . __('reason61') . '</b></td>
  <td style="background-color: #F7CE4A"><b>' . __('action61') . '</b></td>
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

    $users_sql = '
SELECT
 username,
 quarantine_rcpt,
 type
FROM
 users
WHERE
 quarantine_report=1
';

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
DATE_FORMAT(timestamp,'" . str_replace('%', '%%', DATE_FORMAT) . ' <br/>' . str_replace('%', '%%', TIME_FORMAT) . "') AS datetime,
a.timestamp AS timestamp,
a.to_address AS to_address,
a.from_address AS from_address,
a.subject AS subject,
a.token AS token,
CASE
 WHEN a.virusinfected>0 THEN '" . __('virus61') . "'
 WHEN a.nameinfected>0 THEN '" . __('badcontent61') . "'
 WHEN a.otherinfected>0 THEN '" . __('infected61') . "'
 WHEN a.ishighspam>0 THEN '" . __('spam61') . "'
 WHEN a.issaspam>0 THEN '" . __('spam61') . "'
 WHEN a.isrblspam>0 THEN '" . __('spam61') . "'
 WHEN a.spamblacklisted>0 THEN '" . __('blacklisted61') . "'
 WHEN a.isspam THEN '" . __('spam61') . "'
 WHEN a.ismcp>0 THEN '" . __('policy61') . "'
 WHEN a.ishighmcp>0 THEN '" . __('policy61') . "'
 WHEN a.issamcp>0 THEN '" . __('policy61') . "'
 WHEN a.mcpblacklisted>0 THEN '" . __('policy61') . "'
 WHEN a.isspam>0 THEN '" . __('spam61') . "'
 ELSE '" . __('unknow61') . "'
END AS reason
FROM
 maillog a
WHERE 
 a.quarantined = 1
AND ((to_address =%s) OR (to_domain =%s)) 
AND 
 a.date >= DATE_SUB(CURRENT_DATE(), INTERVAL " . QUARANTINE_REPORT_DAYS . ' DAY)';

    // Hide high spam/mcp from users if enabled
    if (defined('HIDE_HIGH_SPAM') && HIDE_HIGH_SPAM === true) {
        $sql .= '
    AND
     ishighspam=0
    AND
     COALESCE(ishighmcp,0)=0';
    }

    if (defined('HIDE_NON_SPAM') && HIDE_NON_SPAM === true) {
        $sql .= '
    AND
    isspam>0';
    }

    if (defined('HIDE_UNKNOWN') && HIDE_UNKNOWN === true) {
        $sql .= '
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
    isrblspam>0
    OR
    spamblacklisted>0
    OR
    ismcp>0
    OR
    ishighmcp>0
    OR
    issamcp>0
    OR
    isspam>0
    )';
    }

    $sql .= '
ORDER BY a.date DESC, a.time DESC';

    $result = dbquery($users_sql);
    $rows = $result->num_rows;
    if ($rows > 0) {
        while ($user = $result->fetch_object()) {
            dbg("\n === Generating report for " . $user->username . ' type=' . $user->type);
            // Work out destination e-mail address
            switch ($user->type) {
                case 'D':
                    // Type: domain admin - this must be overridden
                    if (!empty($user->quarantine_rcpt)) {
                        $email = $user->quarantine_rcpt;
                    } else {
                        $email = filter_var($user->username, FILTER_VALIDATE_EMAIL);
                    }
                    $to_address = $user->username;
                    if (preg_match('/(\S+)@(\S+)/', $user->username, $split)) {
                        $to_domain = $split[2];
                    } else {
                        $to_domain = $user->username;
                    }
                    break;
                case 'A':
                case 'U':
                default:
                    // Type 'A'dministrator, 'U'ser and everything else just in case...
                    if (!empty($user->quarantine_rcpt)) {
                        $email = $user->quarantine_rcpt;
                    } else {
                        $email = filter_var($user->username, FILTER_VALIDATE_EMAIL);
                    }
                    $to_address = $user->username;
                    $to_domain = $user->username;
                    break;
            }
            // Make sure we have a destination address
            if (!empty($email) && false !== $email) {
                dbg(" ==== Recipient e-mail address is $email");
                // Get any additional reports required
                $filters = array_merge(array($to_address), return_user_filters($user->username));
                if (false === QUARANTINE_FILTERS_COMBINED) {
                    foreach ($filters as $filter) {
                        if ($user->type === 'D') {
                            if (preg_match('/(\S+)@(\S+)/', $filter, $split)) {
                                $filter_domain = $split[2];
                            } else {
                                $filter_domain = $filter;
                            }
                            dbg(" ==== Building list for $filter_domain");
                            $quarantined = return_quarantine_list_array($filter, $filter_domain);
                        } else {
                            dbg(" ==== Building list for $filter");
                            $quarantined = return_quarantine_list_array($filter, $to_domain);
                        }
                        dbg(' ==== Found ' . count($quarantined) . ' quarantined e-mails');
                        //print_r($quarantined);
                        if (count($quarantined) > 0) {
                            if ($user->type === 'D') {
                                send_quarantine_email($email, $filter_domain, $quarantined);
                            } else {
                                send_quarantine_email($email, $filter, $quarantined);
                            }
                        }
                        unset($quarantined);
                    }
                } else {
                    //combined
                    $quarantine_list = array();
                    foreach ($filters as $filter) {
                        if ($user->type === 'D') {
                            if (preg_match('/(\S+)@(\S+)/', $filter, $split)) {
                                $filter_domain = $split[2];
                            } else {
                                $filter_domain = $filter;
                            }
                            $quarantine_list[] = $filter_domain;
                            dbg(" ==== Building list for $filter_domain");
                            $tmp_quarantined = return_quarantine_list_array($filter, $filter_domain);
                        } else {
                            $quarantine_list[] = $filter;
                            dbg(" ==== Building list for $filter");
                            $tmp_quarantined = return_quarantine_list_array($filter, $to_domain);
                        }
                        dbg(' ==== Found ' . count($tmp_quarantined) . ' quarantined e-mails');
                        if (isset($quarantined) && is_array($quarantined)) {
                            $quarantined = array_merge($quarantined, $tmp_quarantined);
                        } else {
                            $quarantined = $tmp_quarantined;
                        }
                    }
                    if (count($quarantined) > 0) {
                        $list = implode(', ', $quarantine_list);
                        send_quarantine_email($email, $list, quarantine_sort($quarantined));
                    }
                    unset($quarantined);
                }
            } else {
                dbg(' ==== ' . $user->username . ' has empty e-mail recipient address, skipping...');
            }
        }
    }
}

/**
 * @param string $text
 */
function dbg($text)
{
    echo $text . "\n";
}

/**
 * @param string $user
 * @return array
 */
function return_user_filters($user)
{
    global $filters_sql;
    $result = dbquery(sprintf($filters_sql, quote_smart($user)));
    $rows = $result->num_rows;
    $array = array();
    if ($rows > 0) {
        while ($row = $result->fetch_object()) {
            $array[] = $row->filter;
        }
    }
    return $array;
}

/**
 * @param string $to_address
 * @param string $to_domain
 * @return array
 */
function return_quarantine_list_array($to_address, $to_domain)
{
    global $sql;
    $result = dbquery(sprintf($sql, quote_smart($to_address), quote_smart($to_domain)));
    $rows = $result->num_rows;
    $array = array();
    if ($rows > 0) {
        while ($row = $result->fetch_object()) {
            $array[] = array(
                'id' => trim($row->id),
                'datetime' => trim($row->datetime),
                'to' => trim_output($row->to_address, FROMTO_MAXLEN),
                'from' => trim_output($row->from_address, FROMTO_MAXLEN),
                'subject' => trim_output($row->subject, SUBJECT_MAXLEN),
                'reason' => trim($row->reason),
                'timestamp' => trim($row->timestamp),
                'token' => trim($row->token)
            );
        }
    }
    return $array;
}

/**
 * @param array $qitem
 * @return bool
 */
function store_auto_release($qitem)
{
    $id = $qitem['id'];
    $rand = $qitem['rand'];
    $result = dbquery("INSERT INTO autorelease (msg_id,uid) VALUES ('$id','$rand')", false);
    if (!$result) {
        dbg(' ==== Error generating auto_release....skipping...');
        return false;
    } else {
        return true;
    }
}

/**
 * @param string $qitem
 * @return bool
 */
function check_auto_release($qitem)
{
    //function checks if message already has an autorelease entry
    $id = $qitem['id'];
    $result = dbquery("SELECT * FROM autorelease WHERE msg_id = '$id'", false);
    if (!$result) {
        dbg(' === Error checking if msg_id already exists.....skipping....');
    } else {
        if ($result->num_rows === 0) {
            return false;//msg_id not found,
        } elseif ($result->num_rows === 1) {
            $row = $result->fetch_array();

            return $row['uid']; //return the stored uid
        } else {
            dbg('=== Error, msg_id exists more than once....generating new one...');
            return false;
        }
    }

    return false;
}

/**
 * @param string $email
 * @param string $filter
 * @param array $quarantined
 */
function send_quarantine_email($email, $filter, $quarantined)
{
    global $html, $html_table, $html_content, $text, $text_content;
    // Setup variables to prevent warnings
    $h1 = '';
    $t1 = '';
    // Build the quarantine list for this recipient
    foreach ($quarantined as $qitem) {
        //Check if auto-release is enabled
        $links = '<a href="' . MAILWATCH_HOSTURL . '/viewmail.php?token=' . $qitem['token'] .'&amp;id=' . $qitem['id'] . '">'.__('view61').'</a>';
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
                // add auto release link if enabled
                $links .= '  <a href="' . MAILWATCH_HOSTURL . '/auto-release.php?mid=' . $qitem['id'] . '&r=' . $qitem['rand'] . '">' . __('release61') . '</a>';
            }
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
    $isSent = send_email($email, $html_report, $text_report, QUARANTINE_REPORT_SUBJECT);
    if ($isSent === true) {
        dbg(" ==== Sent e-mail to $email");
    } else {
        dbg(" ==== ERROR sending e-mail to $email ". $isSent);
    }
}

/**
 * @param $q
 * @return array
 */
function quarantine_sort($q)
{
    $key = 'timestamp';
    usort($q, function ($a, $b) use (&$key) {
        return strtotime($a[$key]) - strtotime($b[$key]);
    });

    return array_reverse($q);
}
