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

// Require the functions page
require_once __DIR__ . '/functions.php';

// Require the login function code
require __DIR__ . '/login.function.php';

// Set the Memory usage
ini_set('memory_limit', MEMORY_LIMIT);

if (isset($_POST['token'])) {
    if (false === checkToken($_POST['token'])) {
        header('Location: login.php?error=pagetimeout');
        die();
    }
} else {
    if (false === checkToken($_GET['token'])) {
        header('Location: login.php?error=pagetimeout');
        die();
    }
}

if (isset($_POST['id'])) {
    $url_id = trim(deepSanitizeInput($_POST['id'], 'url'), ' ');
} else {
    $url_id = trim(deepSanitizeInput($_GET['id'], 'url'), ' ');
}

if (!validateInput($url_id, 'msgid')) {
    die(__('dieid04') . " '" . $url_id . "' " . __('dienotfound04') . "\n");
}

// Start the header code and Title
html_start(__('messdetail04') . ' ' . $url_id, 0, false, false);

// Setting the yes and no variable
$yes = '<span class="yes">&nbsp;' . __('yes04') . '&nbsp;</span>';
$no = '<span class="no">&nbsp;' . __('no04') . '&nbsp;</span>';

// Setting what Mail Transfer Agent is being used
$mta = get_conf_var('mta');

// The sql command to pull the data
$sql = "
 SELECT
  DATE_FORMAT(timestamp, '" . DATE_FORMAT . ' ' . TIME_FORMAT . "') AS '" . __('receivedon04') . "',
  hostname AS '" . __('receivedby04') . "',
  clientip AS '" . __('receivedfrom04') . "',
  headers '" . __('receivedvia04') . "',
  id AS '" . __('id04') . "',
  headers AS '" . __('msgheaders04') . "',
  from_address AS '" . __('from04') . "',
  to_address AS '" . __('to04') . "',
  subject AS '" . __('subject04') . "',
  size AS '" . __('size04') . "',
  archive AS 'Archive',
  '" . __('hdrantivirus04') . "' AS 'HEADER',
  CASE WHEN virusinfected>0 THEN '$yes' ELSE '$no' END AS '" . __('virus04') . "',
  CASE WHEN nameinfected>0 THEN '$yes' ELSE '$no' END AS '" . __('blkfile04') . "',
  CASE WHEN otherinfected>0 THEN '$yes' ELSE '$no' END AS '" . __('otherinfec04') . "',
  report AS '" . __('report04') . "',
  '" . __('spamassassin04') . "' AS 'HEADER',
  CASE WHEN isspam>0 THEN '$yes' ELSE '$no' END AS '" . __('spam04') . "',
  CASE WHEN ishighspam>0 THEN '$yes' ELSE '$no' END AS '" . __('hscospam04') . "',
  CASE WHEN issaspam>0 THEN '$yes' ELSE '$no' END AS '" . __('spamassassinspam04') . "',
  CASE WHEN isrblspam>0 THEN '$yes' ELSE '$no' END AS '" . __('listedrbl04') . "',
  CASE WHEN spamwhitelisted>0 THEN '$yes' ELSE '$no' END AS '" . __('spamwl04') . "',
  CASE WHEN spamblacklisted>0 THEN '$yes' ELSE '$no' END AS '" . __('spambl04') . "',
  spamreport AS '" . __('saautolearn04') . "',
  sascore AS '" . __('sascore04') . "',
  spamreport AS '" . __('spamrep04') . "',
  '" . __('hdrmcp04') . "' AS 'HEADER',
  CASE WHEN ismcp>0 THEN '$yes' ELSE '$no' END AS 'MCP:',
  CASE WHEN ishighmcp>0 THEN '$yes' ELSE '$no' END AS '" . __('highscomcp04') . "',
  CASE WHEN issamcp>0 THEN '$yes' ELSE '$no' END AS '" . __('spamassassinmcp04') . "',
  CASE WHEN mcpwhitelisted>0 THEN '$yes' ELSE '$no' END AS '" . __('mcpwl04') . "',
  CASE WHEN mcpblacklisted>0 THEN '$yes' ELSE '$no' END AS '" . __('mcpbl04') . "',
  mcpsascore AS '" . __('mcpscore04') . "',
  mcpreport AS '" . __('mcprep04') . "',
  rblspamreport AS rblspamreport
 FROM
  maillog
 WHERE
  " . $_SESSION['global_filter'] . "
 AND
  id = '" . $url_id . "'
";

// Pull the data back and put it in the the $result variable
$result = dbquery($sql);

// Check to make sure something was returned
if ($result->num_rows === 0) {
    die(__('dieid04') . " '" . $url_id . "' " . __('dienotfound04') . "\n </TABLE>");
}

audit_log(__('auditlog04', true) . ' (id=' . $url_id . ')');

// Check if MCP is enabled
$is_MCP_enabled = get_conf_truefalse('mcpchecks');

echo '<table class="maildetail" border="0" cellspacing="1" cellpadding="1" width="100%">' . "\n";

$row = $result->fetch_array();
if ($result->num_rows > 1) {
    error_log('WARNING: multiple mails existed for id ' . $url_id . ' only first result displayed to user');
}
$listurl = 'lists.php?token=' . $_SESSION['token'] .'&amp;host=' . $row[__('receivedfrom04')] . '&amp;from=' . $row[__('from04')] . '&amp;to=' . $row[__('to04')];
for ($f = 0; $f < $result->field_count; $f++) {
    $fieldInfo = $result->fetch_field_direct($f);
    $fieldn = $fieldInfo->name;
    if ($fieldn === __('receivedfrom04')) {
        $output = '<table class="sa_rules_report" width="100%" cellspacing=0 cellpadding=0><tr><td>' . $row[$f] . '</td>';
        if (LISTS) {
            $output .= '<td class="noprint" align="right">[<a class="nowrap" href="' . $listurl . '&amp;type=h&amp;list=w">' . __('addwl04') . '</a>&nbsp;|&nbsp;<a class="nowrap" href="' . $listurl . '&amp;type=h&amp;list=b">' . __('addbl04') . '</a>]</td>';
        }
        $output .= "</tr></table>\n";
        $row[$f] = $output;
    }
    if ($fieldn === __('receivedvia04')) {
        // Start Table
        $output = '<table width="100%" class="sa_rules_report">' . "\n";
        $output .= ' <tr>' . "\n";
        $output .= ' <th>' . __('ipaddress04') . '</th>' . "\n";
        $output .= ' <th>' . __('hostname04') . '</th>' . "\n";
        $output .= ' <th>' . __('country04') . '</th>' . "\n";
        $output .= ' <th class="noprint">RBL</th>' . "\n";
        $output .= ' <th class="noprint">Spam</th>' . "\n";
        $output .= ' <th class="noprint" >Virus</th>' . "\n";
        $output .= ' <th class="noprint">' . __('all04') . '</th>' . "\n";
        $output .= ' </tr>' . "\n";
        if (is_array($relays = get_mail_relays($row[$f]))) {
            foreach ($relays as $relay) {
                $output .= ' <tr>' . "\n";
                $output .= ' <td>' . $relay . '</td>' . "\n";
                // check if ipv4 has a port specified (e.g. 10.0.0.10:1025), strip it if found
                $relay = stripPortFromIp($relay);
                //check if address is in private IP space
                $isPrivateNetwork = ip_in_range($relay, false, 'private');
                $isLocalNetwork = ip_in_range($relay, false, 'local');
                if ($isPrivateNetwork === true) {
                    $output .= ' <td>' . __('privatenetwork04') . "</td>\n";
                } elseif ($isLocalNetwork === true) {
                    $output .= ' <td>' . __('localhost04') . "</td>\n";
                }
                // Reverse lookup on address. Possibly need to remove it.
                elseif (($host = gethostbyaddr($relay)) !== $relay) {
                    $output .= " <td>$host</td>\n";
                } else {
                    $output .= ' <td>' . __('reversefailed04') . "</td>\n";
                }
                // Do GeoIP lookup on address
                if (true === $isPrivateNetwork) {
                    $output .= ' <td>' .  __('privatenetwork04') . "</td>\n";
                } elseif ($isLocalNetwork === true) {
                    $output .= ' <td>' . __('localhost04') . "</td>\n";
                } elseif (!version_compare(phpversion(), '5.4.0', '>=')) {
                    $output .= ' <td>' . __('geoipnotsupported04') . "</td>\n";
                } elseif ($geoip_country = return_geoip_country($relay)) {
                    $output .= ' <td>' . $geoip_country . '</td>' . "\n";
                } else {
                    $output .= ' <td>' . __('geoipfailed04') . '</td>' . "\n";
                }
                // Link to RBL Lookup
                $output .= ' <td class="noprint" align="center">[<a href="http://multirbl.valli.org/lookup/' . $relay . '.html">&nbsp;&nbsp;</a>]</td>' . "\n";
                // Link to Spam Report for this relay
                $output .= ' <td class="noprint" align="center">[<a href="rep_message_listing.php?token=' . $_SESSION['token'] .'&amp;relay=' . $relay . '&amp;isspam=1">&nbsp;&nbsp;</a>]</td>' . "\n";
                // Link to Virus Report for this relay
                $output .= ' <td class="noprint" align="center">[<a href="rep_message_listing.php?token=' . $_SESSION['token'] .'&amp;relay=' . $relay . '&amp;isvirus=1">&nbsp;&nbsp;</a>]</td>' . "\n";
                // Link to All Messages Report for this relay
                $output .= ' <td class="noprint" align="center">[<a href="rep_message_listing.php?token=' . $_SESSION['token'] .'&amp;relay=' . $relay . '">&nbsp;&nbsp;</a>]</td>' . "\n";
                // Close table
                $output .= ' </tr>' . "\n";
            }
            $output .= '</table>' . "\n";
            $row[$f] = $output;
        } else {
            $row[$f] = '127.0.0.1'; // Must be local mailer (Exim)
        }
    }
    if ($fieldn === __('report04')) {
        $row[$f] = nl2br(str_replace(',', '<br>', htmlentities($row[$f])));
        $row[$f] = preg_replace("/<br \/>/", '<br>', $row[$f]);
        $row[$f] = preg_replace('/ <br>/', '<br>', $row[$f]);
    }
    if ($fieldn === __('from04')) {
        $row[$f] = htmlentities($row[$f]);
        $output = '<table class="sa_rules_report" cellspacing="0"><tr><td>' . $row[$f] . '</td>' . "\n";
        if (LISTS) {
            $output .= '<td class="noprint" align="right">[<a class="nowrap" href="' . $listurl . '&amp;type=f&amp;list=w">' . __('addwl04') . '</a>&nbsp;|&nbsp;<a class="nowrap" href="' . $listurl . '&amp;type=f&amp;list=b">' . __('addbl04') . '</a>]</td>' . "\n";
        }
        $output .= '</tr></table>' . "\n";
        $row[$f] = $output;
    }
    if ($fieldn === __('to04')) {
        $row[$f] = htmlspecialchars($row[$f]);
        $row[$f] = str_replace(',', '<br>', $row[$f]);
    }
    if ($fieldn === __('subject04')) {
        $row[$f] = htmlspecialchars(getUTF8String(decode_header($row[$f])));
    }
    if ($fieldn === __('spamrep04')) {
        $row[$f] = format_spam_report($row[$f]);
    }
    if ($fieldn === __('size04')) {
        $row[$f] = formatSize($row[$f]);
    }
    if ($fieldn === __('msgheaders04')) {
        if (version_compare(PHP_VERSION, '5.4', '>=')) {
            $row[$f] = nl2br(
                str_replace(array("\n", "\t"), array('<br>', '&nbsp; &nbsp; &nbsp;'), htmlentities($row[$f], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE))
            );
        } else {
            $row[$f] = nl2br(
                str_replace(array("\n", "\t"), array('<br>', '&nbsp; &nbsp; &nbsp;'), htmlentities($row[$f]))
            );
        }
        if (function_exists('iconv_mime_decode')) {
            $row[$f] = iconv_mime_decode(utf8_decode($row[$f]), 2, 'UTF-8');
        }
        $row[$f] = preg_replace("/<br \/>/", '<br>', $row[$f]);
    }
    if ($fieldn === __('saautolearn04')) {
        if (($autolearn = sa_autolearn($row[$f])) !== false) {
            $row[$f] = $yes . " ($autolearn)";
        } else {
            $row[$f] = $no;
        }
    }
    if ($fieldn === __('spam04') && !DISTRIBUTED_SETUP) {
        // Display actions if spam/not-spam
        if ($row[$f] === $yes) {
            $row[$f] = $row[$f] . '&nbsp;&nbsp;' . __('actions04') . ' ' . str_replace(' ', ', ', get_conf_var('SpamActions'));
        } else {
            $row[$f] = $row[$f] . '&nbsp;&nbsp;' . __('actions04') . ' ' . str_replace(
                    ' ',
                    ', ',
                    get_conf_var('NonSpamActions')
                );
        }
    }
    if ($row[$f] === $yes && $fieldn === __('hscospam04')) {
        // Display actions if high-scoring
        $row[$f] = $row[$f] . '&nbsp;&nbsp;' . __('actions04') . ' ' . str_replace(
                ' ',
                ', ',
                get_conf_var('HighScoringSpamActions')
            );
    }

    if ($is_MCP_enabled === true) {
        if ($fieldn === __('mcprep04')) {
            $row[$f] = format_mcp_report($row[$f]);
        }
    } else {
        if ($fieldn === 'HEADER' && strpos($row[$f], 'MCP') !== false) {
            continue;
        }
        if (strpos($fieldn, 'MCP') !== false) {
            continue;
        }
    }

    if ($row[$f] === $yes && $fieldn === __('listedrbl04')) {
        $row[$f] = $row[$f] . ' (' . $row['rblspamreport'] . ')';
    }

    if ($fieldn === 'rblspamreport') {
        continue;
    }

    // Handle dummy header fields
    if ($fieldn === 'HEADER') {
        // Display header
        echo '<tr><td class="heading" align="center" valign="top" colspan="2">' . $row[$f] . '</td></tr>' . "\n";
    } else {
        // Actual data
        if (!empty($row[$f])) {
            // Skip empty rows (notably Spam Report when SpamAssassin didn't run)
            echo '<tr><td class="heading-w175">' . $fieldn . '</td><td class="detail">' . $row[$f] . '</td></tr>' . "\n";
        }
    }
}

// Display the relay information only if there are matching
// rows in the relay table (maillog.id = relay.msg_id)...
$sqlcheck = "SHOW TABLES LIKE 'mtalog_ids'";
$tablecheck = dbquery($sqlcheck);
if (($mta === 'postfix' || $mta === 'msmail') && $tablecheck->num_rows > 0) { //version for postfix
    $sql1 = "
 SELECT
  DATE_FORMAT(m.timestamp,'" . DATE_FORMAT . ' ' . TIME_FORMAT . "') AS 'Date/Time',
  m.host AS 'Relayed by',
  m.relay AS 'Relayed to',
  m.delay AS 'Delay',
  m.status AS 'Status'
 FROM
  mtalog AS m
       	LEFT JOIN mtalog_ids AS i ON (i.smtp_id = m.msg_id)
 WHERE
  i.smtpd_id='" . $url_id . "'
 AND
  m.type='relay'
 ORDER BY
  m.timestamp DESC";
} else { //version for sendmail
    $sql1 = "
 SELECT
  DATE_FORMAT(timestamp,'" . DATE_FORMAT . ' ' . TIME_FORMAT . "') AS 'Date/Time',
  host AS 'Relayed by',
  relay AS 'Relayed to',
  delay AS 'Delay',
  status AS 'Status'
 FROM
  mtalog
 WHERE
  msg_id='" . $url_id . "'
 AND
  type='relay'
 ORDER BY
  timestamp DESC";
}

$sth1 = dbquery($sql1);
if (false !== $sth1 && $sth1->num_rows > 0) {
    // Display the relay table entries
    echo ' <tr><td class="heading-w175">' .  __('relayinfo04') . '</td><td class="detail">' . "\n";
    echo '  <table class="sa_rules_report" width="100%">' . "\n";
    echo '   <tr>' . "\n";
    for ($f = 0; $f < $sth1->field_count; $f++) {
        $fieldInfo1 = $sth1->fetch_field_direct($f);
        echo '   <th>' . $fieldInfo1->name . '</th>' . "\n";
    }
    echo "   </tr>\n";
    while ($row = $sth1->fetch_row()) {
        echo '    <tr>' . "\n";
        echo '     <td class="detail" align="left">' . $row[0] . '</td>' . "\n"; // Date/Time
        echo '     <td class="detail" align="left">' . $row[1] . '</td>' . "\n"; // Relayed by
        if (($lhost = @gethostbyaddr($row[2])) !== $row[2]) {
            echo '     <td class="detail" align="left">' . $lhost . '</td>' . "\n"; // Relayed to
        } else {
            echo '     <td class="detail" align="left">' . $row[2], '</td>' . "\n";
        }
        echo '     <td class="detail">' . $row[3] . '</td>' . "\n"; // Delay
        echo '     <td class="detail">' . $row[4] . '</td>' . "\n"; // Status
        echo '    </tr>' . "\n";
    }
    echo "  </table>\n";
    echo " </td></tr>\n";
}
echo "</table>\n";

flush();

$quarantinedir = get_conf_var('QuarantineDir');
$quarantined = quarantine_list_items($url_id, RPC_ONLY);
if (is_array($quarantined) && (count($quarantined) > 0)) {
    echo "<br>\n";

    if (isset($_POST['submit']) && deepSanitizeInput($_POST['submit'], 'url') === 'submit') {
        if (false === checkFormToken('/detail.php ops token', $_POST['formtoken'])) {
            die(__('error04'));
        }
        debug('submit branch taken');
        // Reset error status
        $error = 0;
        $status = array();
        // Release
        if (isset($_POST['release'])) {
            // Send to the original recipient(s) or to an alternate address
            if (isset($_POST['alt_recpt_yn']) && deepSanitizeInput($_POST['alt_recpt_yn'], 'url') === 'y') {
                $to = deepSanitizeInput($_POST['alt_recpt'], 'string');
                if (!validateInput($to, 'user')) {
                    die(__('error04') . ' ' . $to);
                }
            } else {
                $to = $quarantined[0]['to'];
            }

            $arrid = $_POST['release'];
            if (!is_array($arrid)) {
                die();
            }
            $arrid2 = array();
            foreach ($arrid as $id) {
                $id2 = deepSanitizeInput($id, 'num');
                if (!validateInput($id2, 'num')) {
                    die();
                }
                $arrid2[] = $id2;
            }
            $status[] = quarantine_release($quarantined, $arrid2, $to, RPC_ONLY);
        }
        // sa-learn
        if (isset($_POST['learn'])) {
            $arrid = $_POST['learn'];
            if (!is_array($arrid)) {
                die();
            }
            $arrid2 = array();
            foreach ($arrid as $id) {
                $id2 = deepSanitizeInput($id, 'num');
                if (!validateInput($id2, 'num')) {
                    die(__('dievalidate99'));
                }
                $arrid2[] = $id2;
            }
            $type = deepSanitizeInput($_POST['learn_type'], 'url');
            if (!validateInput($type, 'salearnops')) {
                die(__('dievalidate99'));
            }
            $status[] = quarantine_learn($quarantined, $arrid2, $type, RPC_ONLY);
        }
        // Delete
        if (isset($_POST['delete'])) {
            $arrid = $_POST['delete'];
            if (!is_array($arrid)) {
                die();
            }
            $arrid2 = array();
            foreach ($arrid as $id) {
                $id2 = deepSanitizeInput($id, 'num');
                if (!validateInput($id2, 'num')) {
                    die(__('dievalidate99'));
                }
                $arrid2[] = $id2;
            }
            $status[] = quarantine_delete($quarantined, $arrid2, RPC_ONLY);
        }
        echo '<table border="0" cellpadding="1" cellspacing="1" width="100%" class="maildetail">' . "\n";
        echo ' <tr>' . "\n";
        echo '  <th colspan="2">' . __('quarcmdres04') . '</th>' . "\n";
        echo ' </tr>' . "\n";
        if (!empty($status)) {
            echo '  <tr>' . "\n";
            echo '  <td class="heading" width="150" align="right" valign="top">' . __('resultmsg04') . ':</td>' . "\n";
            echo '  <td class="detail">' . "\n";
            foreach ($status as $key => $val) {
                echo "  $val<br>\n";
            }
            echo "  </td>\n";
            echo " </tr>\n";
        }
        if (isset($errors)) {
            echo " <tr>\n";
            echo '  <td class="heading" width="150" align="right" valign="top">' . __('errormess04') . '</td>' . "\n";
            echo '  <td class="detail">' . "\n";
            foreach ($errors as $key => $val) {
                echo "  $val<br>\n";
            }
            echo "  </td>\n";
            echo " <tr>\n";
        }
        echo " <tr>\n";
        echo '  <td class="heading" width="150" align="right" valign="top">' . __('errormess04') . '</td>' . "\n";
        echo '  <td class="detail">' . ($error ? $yes : $no) . '</td>' . "\n";
        echo ' </tr>' . "\n";
        echo '</table>' . "\n";
    } else {
        // get perfromed actions
        $sql = "SELECT released,salearn FROM `maillog` WHERE `id` = '$url_id'";
        $result = dbquery($sql);
        $row = $result->fetch_array();
        echo '<form action="detail.php" method="post" name="quarantine">' . "\n";
        echo '<table cellspacing="1" width="100%" class="mail">' . "\n";
        echo ' <tr>' . "\n";
        echo '  <th colspan="7">' . __('quarantine04') . '</th>' . "\n";
        echo ' </tr>' . "\n";
        echo ' <tr>' . "\n";
        echo '  <th>' . __('release04') . '</th>' . "\n";
        echo '  <th class="noprint">' . __('delete04') . '</th>' . "\n";
        echo '  <th>' . __('salearn04') . '</th>' . "\n";
        echo '  <th>' . __('file04') . '</th>' . "\n";
        echo '  <th>' . __('type04') . '</th>' . "\n";
        echo '  <th>' . __('path04') . '</th>' . "\n";
        echo '  <th>' . __('dang04') . '</th>' . "\n";
        echo ' </tr>' . "\n";
        $is_dangerous = 0;
        foreach ($quarantined as $item) {
            $tdclass='';
            if ($row['released'] > 0 && $item['file'] === 'message') {
                $tdclass='released';
            }
            echo " <tr>\n";
            // Don't allow message to be released if it is marked as 'dangerous'
            // Currently this only applies to messages that contain viruses.
            // visible only to Administrators and Domain Admin only if DOMAINADMIN_CAN_RELEASE_DANGEROUS_CONTENTS is enabled
            if (
                $_SESSION['user_type'] === 'A' ||
                (defined('DOMAINADMIN_CAN_RELEASE_DANGEROUS_CONTENTS') && true === DOMAINADMIN_CAN_RELEASE_DANGEROUS_CONTENTS && $_SESSION['user_type'] === 'D') ||
                $item['dangerous'] !== 'Y'
            ) {
                echo '  <td align="center" class="' . $tdclass . '"><input class="noprint" type="checkbox" name="release[]" value="' . $item['id'] . '"></td>' . "\n";
            } else {
                echo '<td class="' . $tdclass . '">&nbsp;&nbsp;</td>' . "\n";
            }
            echo '  <td class="noprint" align="center"><input type="checkbox" name="delete[]" value="' . $item['id'] . '"></td>' . "\n";
            // If the file is an rfc822 message then allow the file to be learnt
            // by SpamAssassin Bayesian learner as either spam or ham (sa-learn).
            if (
                (preg_match('/message\/rfc822/', $item['type']) || $item['file'] === 'message') &&
                (strtoupper(get_conf_var('UseSpamAssassin')) !== 'NO')
            ) {
                echo '   <td align="center" class="salearn-' . $row['salearn'] . '"><input class="noprint" type="checkbox" name="learn[]" value="' . $item['id'] . '"><select class="noprint" name="learn_type"><option value="ham">' . __('asham04') . '</option><option value="spam">' . __('aspam04') . '</option><option value="forget">' . __('forget04') . '</option><option value="report">' . __('spamreport04') . '</option><option value="revoke">' . __('spamrevoke04') . '</option></select></td>' . "\n";
            } else {
                echo '   <td>&nbsp;&nbsp;</td>' . "\n";
            }
            echo '  <td>' . $item['file'] . '</td>' . "\n";
            echo '  <td>' . $item['type'] . '</td>' . "\n";
            // If the file is in message/rfc822 format and isn't dangerous - create a link to allow it to be viewed
            // Domain admins can view the file only if enabled
            if (
                (
                    $item['dangerous'] === 'N' ||
                    $_SESSION['user_type'] === 'A' ||
                    (defined('DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS') && true === DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS && $_SESSION['user_type'] === 'D' && $item['dangerous'] === 'Y')
                ) && preg_match('!message/rfc822!', $item['type'])
            ) {
                echo '  <td><a href="viewmail.php?token=' . $_SESSION['token'] .'&amp;id=' . $item['msgid'] . '">' .
                    substr($item['path'], strlen($quarantinedir) + 1) .
                    '</a></td>' . "\n";
            } else {
                echo '  <td>' . substr($item['path'], strlen($quarantinedir) + 1) . "</td>\n";
            }
            if ($item['dangerous'] === 'Y') {
                $dangerous = $yes;
                $is_dangerous++;
            } else {
                $dangerous = $no;
            }
            echo '  <td align="center">' . $dangerous . '</td>' . "\n";
            echo ' </tr>' . "\n";
        }
        echo ' <tr class="noprint">' . "\n";
        if ($_SESSION['user_type'] === 'A' ||
            ($_SESSION['user_type'] === 'D' &&
                ($is_dangerous === 0 ||
                ($is_dangerous > 0 && defined('DOMAINADMIN_CAN_RELEASE_DANGEROUS_CONTENTS') && true === DOMAINADMIN_CAN_RELEASE_DANGEROUS_CONTENTS)
                )
            )
        ) {
            echo '  <td colspan="6"><input type="checkbox" name="alt_recpt_yn" value="y">&nbsp;' . __('altrecip04') . '&nbsp;<input type="TEXT" name="alt_recpt" size="100"></td>' . "\n";
        } else {
            echo '  <td colspan="6">&nbsp;</td>' . "\n";
        }
        echo '  <td align="right">' . "\n";
        echo '<input type="HIDDEN" name="id" value="' . $quarantined[0]['msgid'] . '">' . "\n";
        echo '<INPUT TYPE="HIDDEN" NAME="token" VALUE="' . $_SESSION['token'] . '">' . "\n";
        echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . generateFormToken('/detail.php ops token') . '">' . "\n";
        echo '<button type="SUBMIT" name="submit" value="submit">' . __('submit04') . '</button>' . "\n";
        echo '  </td></tr>' . "\n";
        echo '</table>' . "\n";
        echo '</form>' . "\n";
    }
} else {
    // Error??
    if (!is_array($quarantined)) {
        echo '<br>' . $quarantined . '';
    }
}

// Add footer
html_end();
// Close any open db connections
dbclose();
