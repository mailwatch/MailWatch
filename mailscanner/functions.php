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

// Set error level (some distro's have php.ini set to E_ALL)
use MailWatch\Db;

error_reporting(E_ALL ^ E_DEPRECATED ^ E_STRICT);

$autoloader = require __DIR__ . '/../src/bootstrap.php';

// Read in MailWatch configuration file
/*if (!is_readable(__DIR__ . '/conf.php')) {
    die(\MailWatch\Translation::__('cannot_read_conf'));
}
require_once __DIR__ . '/conf.php';

// more secure session cookies
ini_set('session.use_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
*/
$session_cookie_secure = false;
if (SSL_ONLY === true) {
    ini_set('session.cookie_secure', 1);
    $session_cookie_secure = true;
}

//enforce session cookie security
$params = session_get_cookie_params();
if (defined('SESSION_NAME')) {
    session_name(SESSION_NAME);
}
session_set_cookie_params(0, $params['path'], $params['domain'], $session_cookie_secure, true);

\MailWatch\Translation::configureLanguage();

$missingConfigEntries = checkConfVariables();
if (0 !== $missingConfigEntries['needed']['count']) {
    $br = '';
    if (\PHP_SAPI !== 'cli') {
        $br = '<br>';
    }
    echo \MailWatch\Translation::__('missing_conf_entries') . $br . PHP_EOL;
    foreach ($missingConfigEntries['needed']['list'] as $missingConfigEntry) {
        echo '- ' . $missingConfigEntry . $br . PHP_EOL;
    }
    die();
}

// Set PHP path to use local PEAR modules only
// This appears to be handled by composer now, and is causing a conflict with Pager/Common.php not being found.
//Code remains pending further testing / verification.  Haven't discovered any side-effects of removing this yet.
/*set_include_path(
    '.' . PATH_SEPARATOR .
    MAILWATCH_HOME . '/lib/xmlrpc'
);*/

//Enforce SSL if SSL_ONLY=true
if (\PHP_SAPI !== 'cli' && SSL_ONLY && !empty($_SERVER['PHP_SELF'])) {
    if (!isset($_SERVER['HTTPS']) || 'on' !== $_SERVER['HTTPS']) {
        header('Location: https://' . \MailWatch\Sanitize::sanitizeInput($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']));
        exit;
    }
}

//security headers
if (\PHP_SAPI !== 'cli') {
    header('X-XSS-Protection: 1; mode=block');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    unset($session_cookie_secure);
//    session_start();
}

// set default timezone
date_default_timezone_set(TIME_ZONE);

///////////////////////////////////////////////////////////////////////////////
// Functions
///////////////////////////////////////////////////////////////////////////////
/**
 * @return string
 */
function mailwatch_version()
{
    return '1.99.0-dev';
}

/**
 * @param $preserve
 *
 * @return string|false
 */
function subtract_get_vars($preserve)
{
    if (is_array($_GET)) {
        $output = [];
        foreach ($_GET as $k => $v) {
            if (strtolower($k) !== strtolower($preserve)) {
                $output[] = "$k=$v";
            }
        }
        if (count($output) > 0) {
            $output = implode('&amp;', $output);

            return '&amp;' . $output;
        }

        return false;
    }

    return false;
}

/**
 * @param string[] $preserve
 *
 * @return string|false
 */
function subtract_multi_get_vars($preserve)
{
    if (is_array($_GET)) {
        $output = [];
        foreach ($_GET as $k => $v) {
            if (!in_array($k, $preserve, true)) {
                $output[] = "$k=$v";
            }
        }
        if (count($output) > 0) {
            $output = implode('&amp;', $output);

            return '&amp;' . $output;
        }
    }

    return false;
}

/**
 * @param string $sql the sql query for which the page will be created
 *
 * @return int
 */
function generatePager($sql)
{
    if (isset($_GET['offset'])) {
        $from = (int)$_GET['offset'];
    } else {
        $from = 0;
    }

    // Remove any ORDER BY clauses as this will slow the count considerably
    if ($pos = strpos($sql, 'ORDER BY')) {
        $sqlcount = substr($sql, 0, $pos);
    } else {
        $sqlcount = $sql;
    }

    // Count the number of rows that would be returned by the query
    $sqlcount = 'SELECT COUNT(*) ' . strstr($sqlcount, 'FROM');
    $results = \MailWatch\Db::query($sqlcount);
    $rows = Db::mysqli_result($results, 0);

    // Build the pager data
    $pager_options = [
        'mode' => 'Sliding',
        'perPage' => MAX_RESULTS,
        'delta' => 2,
        'totalItems' => $rows,
    ];
    $pager = Pager::factory($pager_options);

    //then we fetch the relevant records for the current page
    list($from, $to) = $pager->getOffsetByPageId();

    echo '<table cellspacing="1" class="mail" >
<tr>
<th colspan="5">' . \MailWatch\Translation::__('disppage03') . ' ' . $pager->getCurrentPageID() . ' ' . \MailWatch\Translation::__('of03') . ' ' . $pager->numPages() . ' - ' . \MailWatch\Translation::__('records03') . ' ' . $from . ' ' . \MailWatch\Translation::__('to0203') . ' ' . $to . ' ' . \MailWatch\Translation::__('of03') . ' ' . $pager->numItems() . '</th>
</tr>
<tr>
<td align="center">' . "\n";
    //show the links
    echo $pager->links;
    echo '</td>
            </tr>
      </table>
</tr>
<tr>
  <td colspan="' . ('A' === $_SESSION['user_type'] ? '5' : '4') . '">';

    return $from;
}

/**
 * @param $sql
 * @param bool|string $table_heading
 * @param bool $pager
 * @param bool $order
 * @param bool $operations
 */
function db_colorised_table($sql, $table_heading = false, $pager = false, $order = false, $operations = false)
{
    // Ordering
    $orderby = null;
    $orderdir = '';
    if (isset($_GET['orderby'])) {
        $orderby = \MailWatch\Sanitize::sanitizeInput($_GET['orderby']);
        switch (strtoupper($_GET['orderdir'])) {
            case 'A':
                $orderdir = 'ASC';
                break;
            case 'D':
                $orderdir = 'DESC';
                break;
        }
    }
    if (!empty($orderby)) {
        if (false !== ($p = stristr($sql, 'ORDER BY'))) {
            // We already have an existing ORDER BY clause
            $p = "ORDER BY\n  " . $orderby . ' ' . $orderdir . ',' . substr($p, strlen('ORDER BY') + 2);
            $sql = substr($sql, 0, strpos($sql, 'ORDER BY')) . $p;
        } else {
            // No existing ORDER BY - disable feature
            $order = false;
        }
    }

    if ($pager) {
        $from = generatePager($sql);

        // Re-run the original query and limit the rows
        $limit = $from - 1;
        $sql .= " LIMIT $limit," . MAX_RESULTS;
        $sth = \MailWatch\Db::query($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if (false !== $operations) {
            ++$fields;
        }
    } else {
        $sth = \MailWatch\Db::query($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if (false !== $operations) {
            ++$fields;
        }
    }

    if ($rows > 0) {
        if (false !== $operations) {
            // Start form for operations
            echo '<form name="operations" action="./do_message_ops.php" method="POST">' . "\n";
            echo '<input type="hidden" name="token" value="' . $_SESSION['token'] . '">' . "\n";
            echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . \MailWatch\Security::generateFormToken('/do_message_ops.php form token') . '">' . "\n";
        }
        \MailWatch\Html::printColorCodes();
        echo '<table cellspacing="1" width="100%" class="mail rowhover">' . "\n";
        // Work out which columns to display
        $display = [];
        $orderable = [];
        $fieldname = [];
        $align = [];
        for ($f = 0; $f < $fields; ++$f) {
            if (0 === $f && false !== $operations) {
                // Set up display for operations form elements
                $display[$f] = true;
                $orderable[$f] = false;
                // Set it up not to wrap - tricky way to leach onto the align field
                $align[$f] = 'center" style="white-space:nowrap';
                $fieldname[$f] = \MailWatch\Translation::__('ops03') . '<br><a href="javascript:SetRadios(\'S\')">' . \MailWatch\Translation::__('radiospam203') . '</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'H\')">' . \MailWatch\Translation::__('radioham03') . '</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'F\')">' . \MailWatch\Translation::__('radioforget03') . '</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'R\')">' . \MailWatch\Translation::__('radiorelease03') . '</a>';
                continue;
            }
            $display[$f] = true;
            $orderable[$f] = true;
            $align[$f] = false;
            // Set up the mysql column to account for operations
            $colnum = $f;
            if (false !== $operations) {
                $colnum = $f - 1;
            }

            $fieldInfo = $sth->fetch_field_direct($colnum);
            switch ($fieldname[$f] = $fieldInfo->name) {
                case 'host':
                    $fieldname[$f] = 'Host';
                    if (DISTRIBUTED_SETUP) {
                        $display[$f] = true;
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'timestamp':
                    $fieldname[$f] = \MailWatch\Translation::__('datetime03');
                    $align[$f] = 'center';
                    break;
                case 'datetime':
                    $fieldname[$f] = \MailWatch\Translation::__('datetime03');
                    $align[$f] = 'center';
                    break;
                case 'id':
                    $fieldname[$f] = 'ID';
                    $orderable[$f] = false;
                    $align[$f] = 'center';
                    break;
                case 'id2':
                    $fieldname[$f] = '#';
                    $orderable[$f] = false;
                    $align[$f] = 'center';
                    break;
                case 'size':
                    $fieldname[$f] = \MailWatch\Translation::__('size03');
                    $align[$f] = 'right';
                    break;
                case 'from_address':
                    $fieldname[$f] = \MailWatch\Translation::__('from03');
                    break;
                case 'to_address':
                    $fieldname[$f] = \MailWatch\Translation::__('to03');
                    break;
                case 'subject':
                    $fieldname[$f] = \MailWatch\Translation::__('subject03');
                    break;
                case 'clientip':
                    if (defined('DISPLAY_IP') && DISPLAY_IP) {
                        $fieldname[$f] = \MailWatch\Translation::__('clientip03');
                    }
                    $display[$f] = true;
                    break;
                case 'archive':
                    $display[$f] = false;
                    break;
                case 'isspam':
                    $display[$f] = false;
                    break;
                case 'ishighspam':
                    $display[$f] = false;
                    break;
                case 'issaspam':
                    $display[$f] = false;
                    break;
                case 'isrblspam':
                    $display[$f] = false;
                    break;
                case 'spamwhitelisted':
                    $display[$f] = false;
                    break;
                case 'spamblacklisted':
                    $display[$f] = false;
                    break;
                case 'spamreport':
                    $display[$f] = false;
                    break;
                case 'virusinfected':
                    $display[$f] = false;
                    break;
                case 'nameinfected':
                    $display[$f] = false;
                    break;
                case 'otherinfected':
                    $display[$f] = false;
                    break;
                case 'report':
                    $display[$f] = false;
                    break;
                case 'ismcp':
                    $display[$f] = false;
                    break;
                case 'ishighmcp':
                    $display[$f] = false;
                    break;
                case 'issamcp':
                    $display[$f] = false;
                    break;
                case 'mcpwhitelisted':
                    $display[$f] = false;
                    break;
                case 'mcpblacklisted':
                    $display[$f] = false;
                    break;
                case 'mcpreport':
                    $display[$f] = false;
                    break;
                case 'hostname':
                    $fieldname[$f] = \MailWatch\Translation::__('host03');
                    $display[$f] = true;
                    break;
                case 'date':
                    $fieldname[$f] = \MailWatch\Translation::__('date03');
                    break;
                case 'time':
                    $fieldname[$f] = \MailWatch\Translation::__('time03');
                    break;
                case 'headers':
                    $display[$f] = false;
                    break;
                case 'sascore':
                    if (true === \MailWatch\MailScanner::getConfTrueFalse('UseSpamAssassin')) {
                        $fieldname[$f] = \MailWatch\Translation::__('sascore03');
                        $align[$f] = 'right';
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'mcpsascore':
                    if (\MailWatch\MailScanner::getConfTrueFalse('MCPChecks')) {
                        $fieldname[$f] = \MailWatch\Translation::__('mcpscore03');
                        $align[$f] = 'right';
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'status':
                    $fieldname[$f] = \MailWatch\Translation::__('status03');
                    $orderable[$f] = false;
                    break;
                case 'message':
                    $fieldname[$f] = \MailWatch\Translation::__('message03');
                    break;
                case 'attempts':
                    $fieldname[$f] = \MailWatch\Translation::__('tries03');
                    $align[$f] = 'right';
                    break;
                case 'lastattempt':
                    $fieldname[$f] = \MailWatch\Translation::__('last03');
                    $align[$f] = 'right';
                    break;
                case 'released':
                    $display[$f] = false;
                    break;
                case 'salearn':
                    $display[$f] = false;
                    break;
            }
        }
        // Table heading
        if (isset($table_heading) && '' !== $table_heading) {
            // Work out how many columns are going to be displayed
            $column_headings = 0;
            for ($f = 0; $f < $fields; ++$f) {
                if ($display[$f]) {
                    ++$column_headings;
                }
            }
            echo ' <tr class="nohover"">' . "\n";
            echo '  <th colspan="' . $column_headings . '">' . $table_heading . '</th>' . "\n";
            echo ' </tr>' . "\n";
        }
        // Column headings
        echo '<tr class="sonoqui nohover">' . "\n";
        for ($f = 0; $f < $fields; ++$f) {
            if ($display[$f]) {
                if ($order && $orderable[$f]) {
                    // Set up the mysql column to account for operations
                    if (false !== $operations) {
                        $colnum = $f - 1;
                    } else {
                        $colnum = $f;
                    }
                    $fieldInfo = $sth->fetch_field_direct($colnum);
                    echo "  <th>\n";
                    echo "  $fieldname[$f] (<a href=\"?orderby=" . $fieldInfo->name
                        . '&amp;orderdir=a' . subtract_multi_get_vars(
                            ['orderby', 'orderdir']
                        ) . '">A</a>/<a href="?orderby=' . $fieldInfo->name
                        . '&amp;orderdir=d' . subtract_multi_get_vars(['orderby', 'orderdir']) . "\">D</a>)\n";
                    echo "  </th>\n";
                } else {
                    echo '  <th>' . $fieldname[$f] . '</th>' . "\n";
                }
            }
        }
        echo ' </tr>' . "\n";
        // Rows
        $id = '';
        $jsRadioCheck = '';
        $jsReleaseCheck = '';
        for ($r = 0; $r < $rows; ++$r) {
            $row = $sth->fetch_row();
            if (false !== $operations) {
                // Prepend operations elements - later on, replace REPLACEME w/ message id
                array_unshift(
                    $row,
                    '<input name="OPT-REPLACEME" type="RADIO" value="S">&nbsp;<input name="OPT-REPLACEME" type="RADIO" value="H">&nbsp;<input name="OPT-REPLACEME" type="RADIO" value="F">&nbsp;<input name="OPTRELEASE-REPLACEME" type="checkbox" value="R">'
                );
            }
            // Work out field colourings and modify the incoming data as necessary
            // and populate the generate an overall 'status' for the mail.
            $status_array = [];
            $infected = false;
            $highspam = false;
            $spam = false;
            $whitelisted = false;
            $blacklisted = false;
            $mcp = false;
            $highmcp = false;
            $released = false;
            $salearnham = false;
            $salearnspam = false;
            for ($f = 0; $f < $fields; ++$f) {
                if (false !== $operations) {
                    if (0 === $f) {
                        // Skip the first field if it is operations
                        continue;
                    }
                    $fieldNumber = $f - 1;
                } else {
                    $fieldNumber = $f;
                }
                $field = $sth->fetch_field_direct($fieldNumber);
                switch ($field->name) {
                    case 'id':
                        // Store the id for later use
                        $id = $row[$f];
                        // Create a link to detail.php
                        $row[$f] = '<a href="detail.php?token=' . $_SESSION['token'] . '&amp;id=' . $row[$f] . '">' . $row[$f] . '</a>' . "\n";
                        break;
                    case 'id2':
                        // Store the id for later use
                        $id = $row[$f];
                        // Create a link to detail.php as [<link>]
                        $row[$f] = '<a href="detail.php?token=' . $_SESSION['token'] . "&amp;id=$row[$f]\" ><i class=\"mw-icon mw-info-circle\" aria-hidden=\"true\"></i></a>";
                        break;
                    case 'from_address':
                        $row[$f] = htmlentities($row[$f]);
                        if (FROMTO_MAXLEN > 0) {
                            $row[$f] = \MailWatch\Format::trim_output($row[$f], FROMTO_MAXLEN);
                        }
                        break;
                    case 'clientip':
                        $clientip = $row[$f];
                        if (defined('RESOLVE_IP_ON_DISPLAY') && RESOLVE_IP_ON_DISPLAY === true) {
                            if (ip_in_range($clientip)) {
                                $host = 'Internal Network';
                            } elseif (($host = gethostbyaddr($clientip)) === $clientip) {
                                $host = 'Unknown';
                            }
                            $row[$f] .= " ($host)";
                        }
                        break;
                    case 'to_address':
                        $row[$f] = htmlentities($row[$f]);
                        if (FROMTO_MAXLEN > 0) {
                            // Trim each address to specified size
                            $to_temp = explode(',', $row[$f]);
                            $num_to_temp = count($to_temp);
                            for ($t = 0; $t < $num_to_temp; ++$t) {
                                $to_temp[$t] = \MailWatch\Format::trim_output($to_temp[$t], FROMTO_MAXLEN);
                            }
                            // Return the data
                            $row[$f] = implode(',', $to_temp);
                        }
                        // Put each address on a new line
                        $row[$f] = str_replace(',', '<br>', $row[$f]);
                        break;
                    case 'subject':
                        $row[$f] = htmlspecialchars(\MailWatch\Format::getUTF8String(decode_header($row[$f])));
                        if (SUBJECT_MAXLEN > 0) {
                            $row[$f] = \MailWatch\Format::trim_output($row[$f], SUBJECT_MAXLEN);
                        }
                        break;
                    case 'isspam':
                        if ('Y' === $row[$f] || $row[$f] > 0) {
                            $spam = true;
                            $status_array[] = \MailWatch\Translation::__('spam103');
                        }
                        break;
                    case 'ishighspam':
                        if ('Y' === $row[$f] || $row[$f] > 0) {
                            $highspam = true;
                        }
                        break;
                    case 'ismcp':
                        if ('Y' === $row[$f] || $row[$f] > 0) {
                            $mcp = true;
                            $status_array[] = \MailWatch\Translation::__('mcp03');
                        }
                        break;
                    case 'ishighmcp':
                        if ('Y' === $row[$f] || $row[$f] > 0) {
                            $highmcp = true;
                        }
                        break;
                    case 'virusinfected':
                        if ('Y' === $row[$f] || $row[$f] > 0) {
                            $infected = true;
                            $status_array[] = \MailWatch\Translation::__('virus03');
                        }
                        break;
                    case 'report':
                        // IMPORTANT NOTE: for this to work correctly the 'report' field MUST
                        // appear after the 'virusinfected' field within the SQL statement.
                        $virus = \MailWatch\Antivirus::getVirus($row[$f]);
                        if (defined('DISPLAY_VIRUS_REPORT') && DISPLAY_VIRUS_REPORT === true && null !== $virus) {
                            foreach ($status_array as $k => $v) {
                                if ($v = str_replace('Virus', 'Virus (' . \MailWatch\Antivirus::getVirusLink($virus) . ')', $v)) {
                                    $status_array[$k] = $v;
                                }
                            }
                        }
                        break;
                    case 'nameinfected':
                        if ('Y' === $row[$f] || $row[$f] > 0) {
                            $infected = true;
                            $status_array[] = \MailWatch\Translation::__('badcontent03');
                        }
                        break;
                    case 'otherinfected':
                        if ('Y' === $row[$f] || $row[$f] > 0) {
                            $infected = true;
                            $status_array[] = \MailWatch\Translation::__('otherinfected03');
                        }
                        break;
                    case 'size':
                        $row[$f] = \MailWatch\Format::formatSize($row[$f]);
                        break;
                    case 'spamwhitelisted':
                        if ('Y' === $row[$f] || $row[$f] > 0) {
                            $whitelisted = true;
                            $status_array[] = \MailWatch\Translation::__('whitelisted03');
                        }
                        break;
                    case 'spamblacklisted':
                        if ('Y' === $row[$f] || $row[$f] > 0) {
                            $blacklisted = true;
                            $status_array[] = \MailWatch\Translation::__('blacklisted03');
                        }
                        break;
                    case 'clienthost':
                        $hostname = gethostbyaddr($row[$f]);
                        if ($hostname === $row[$f]) {
                            $row[$f] = \MailWatch\Translation::__('hostfailed03');
                        } else {
                            $row[$f] = $hostname;
                        }
                        break;
                    case 'released':
                        if ($row[$f] > 0) {
                            $released = true;
                            $status_array[] = \MailWatch\Translation::__('released03');
                        }
                        break;
                    case 'salearn':
                        switch ($row[$f]) {
                            case 1:
                                $salearnham = true;
                                $status_array[] = \MailWatch\Translation::__('learnham03');
                                break;
                            case 2:
                                $salearnspam = true;
                                $status_array[] = \MailWatch\Translation::__('learnspam03');
                                break;
                        }
                        break;
                    case 'status':
                        // NOTE: this should always be the last row for it to be displayed correctly
                        // Work out status
                        if (0 === count($status_array)) {
                            $status = \MailWatch\Translation::__('clean03');
                        } else {
                            $status = '';
                            foreach ($status_array as $item) {
                                if ($item === \MailWatch\Translation::__('released03')) {
                                    $class = 'released';
                                } elseif ($item === \MailWatch\Translation::__('learnham03')) {
                                    $class = 'salearn-1';
                                } elseif ($item === \MailWatch\Translation::__('learnspam03')) {
                                    $class = 'salearn-2';
                                } else {
                                    $class = '';
                                }
                                $status .= '<div class="' . $class . '">' . $item . '</div>';
                            }
                        }
                        $row[$f] = $status;
                        break;
                }
            }
            // Now add the id to the operations form elements
            if (false !== $operations) {
                $row[0] = str_replace('REPLACEME', $id, $row[0]);
                $jsRadioCheck .= "  document.operations.elements[\"OPT-$id\"][val].checked = true;\n";
                $jsReleaseCheck .= "  document.operations.elements[\"OPTRELEASE-$id\"].checked = true;\n";
            }
            // Colorise the row
            switch (true) {
                case $infected:
                    echo '<tr class="infected">' . "\n";
                    break;
                case $whitelisted:
                    echo '<tr class="whitelisted">' . "\n";
                    break;
                case $blacklisted:
                    echo '<tr class="blacklisted">' . "\n";
                    break;
                case $highspam:
                    echo '<tr class="highspam">' . "\n";
                    break;
                case $spam:
                    echo '<tr class="spam">' . "\n";
                    break;
                case $highmcp:
                    echo '<tr class="highmcp">' . "\n";
                    break;
                case $mcp:
                    echo '<tr class="mcp">' . "\n";
                    break;
                default:
                    if (isset($fieldname['mcpsascore']) && '' !== $fieldname['mcpsascore']) {
                        echo '<tr class="mcp">' . "\n";
                    } else {
                        echo '<tr >' . "\n";
                    }
                    break;
            }
            // Display the rows
            for ($f = 0; $f < $fields; ++$f) {
                if ($display[$f]) {
                    if ($align[$f]) {
                        if (0 === $f) {
                            echo ' <td align="' . $align[$f] . '" class="link-transparent">' . $row[$f] . '</td>' . "\n";
                        } else {
                            echo ' <td align="' . $align[$f] . '">' . $row[$f] . '</td>' . "\n";
                        }
                    } else {
                        echo ' <td>' . $row[$f] . '</td>' . "\n";
                    }
                }
            }
            echo ' </tr>' . "\n";
        }
        echo '</table>' . "\n";
        // Javascript function to clear radio buttons
        if (false !== $operations) {
            echo "
<script type='text/javascript'>
    function ClearRadios() {
        var e=document.operations.elements;
        for(i=0; i<e.length; i++) {
            if (e[i].type=='radio' || e[i].type=='checkbox') {
                e[i].checked=false;
            }
        }
    }

    function SetRadios(p) {
        var val;
        var values = {
            'S'  : 0,
            'H'  : 1,
            'F'  : 2,
            'R'  : 3
        };
        switch (p) {
            case 'S':
            case 'H':
            case 'F':
                val = values[p];
                $jsRadioCheck
                break;
            case 'R':
                $jsReleaseCheck
                break;
            case 'C':
                ClearRadios();
                break;
            default:
                return;
        }
    }
</script>
   <p>&nbsp; <a href=\"javascript:SetRadios('S')\">" . \MailWatch\Translation::__('radiospam203') . "</a>
   &nbsp; <a href=\"javascript:SetRadios('H')\">" . \MailWatch\Translation::__('radioham03') . "</a>
   &nbsp; <a href=\"javascript:SetRadios('F')\">" . \MailWatch\Translation::__('radioforget03') . "</a>
   &nbsp; <a href=\"javascript:SetRadios('R')\">" . \MailWatch\Translation::__('radiorelease03') . '</a>
   &nbsp; ' . \MailWatch\Translation::__('or03') . " <a href=\"javascript:SetRadios('C')\">" . \MailWatch\Translation::__('clear03') . "</p>
   <p><input type='SUBMIT' name='SUBMIT' value='" . \MailWatch\Translation::__('learn03') . "'></p>
   </form>
   <p><b>" . \MailWatch\Translation::__('spam203') . ' &nbsp; <b>' . \MailWatch\Translation::__('ham03') . ' &nbsp; <b>' . \MailWatch\Translation::__('forget03') . ' &nbsp; <b>' . \MailWatch\Translation::__('release03') . '' . "\n";
        }
        echo '<br>' . "\n";
        if ($pager) {
            generatePager($sql);
        }
    }
}

/**
 * Function to display data as a table.
 *
 * @param $sql
 * @param string|null $title
 * @param bool|false $pager
 * @param bool|false $operations
 */
function dbtable($sql, $title = null, $pager = false, $operations = false)
{
    /*
    // Query the data
    $sth = \MailWatch\Db::query($sql);

    // Count the number of rows in a table
    $rows = $sth->num_rows;

    // Count the nubmer of fields
    $fields = $sth->field_count;
    */

    // Turn on paging of for the database
    if ($pager) {
        $from = 0;
        if (isset($_GET['offset'])) {
            $from = (int)$_GET['offset'];
        }

        // Remove any ORDER BY clauses as this will slow the count considerably
        if ($pos = strpos($sql, 'ORDER BY')) {
            $sqlcount = substr($sql, 0, $pos);
        } else {
            $sqlcount = $sql;
        }

        // Count the number of rows that would be returned by the query
        $sqlcount = 'SELECT COUNT(*) AS numrows ' . strstr($sqlcount, 'FROM');

        $results = \MailWatch\Db::query($sqlcount);
        $resultsFirstRow = $results->fetch_array();
        $rows = (int)$resultsFirstRow['numrows'];

        // Build the pager data
        $pager_options = [
            'mode' => 'Sliding',
            'perPage' => MAX_RESULTS,
            'delta' => 2,
            'totalItems' => $rows,
        ];
        $pager = Pager::factory($pager_options);

        //then we fetch the relevant records for the current page
        list($from, $to) = $pager->getOffsetByPageId();

        echo '<table cellspacing="1" class="mail" >
    <tr>
   <th colspan="5">' . \MailWatch\Translation::__('disppage03') . ' ' . $pager->getCurrentPageID() . ' ' . \MailWatch\Translation::__('of03') . ' ' . $pager->numPages() . ' - ' . \MailWatch\Translation::__('records03') . ' ' . $from . ' ' . \MailWatch\Translation::__('to0203') . ' ' . $to . ' ' . \MailWatch\Translation::__('of03') . ' ' . $pager->numItems() . '</th>
  </tr>
  <tr>
  <td align="center">' . "\n";
        //show the links
        echo $pager->links;
        echo '</td>
                </tr>
          </table>
</tr>
<tr>
  <td colspan="' . ('A' === $_SESSION['user_type'] ? '5' : '4') . '">';

        // Re-run the original query and limit the rows
        $sql .= ' LIMIT ' . ($from - 1) . ',' . MAX_RESULTS;
        $sth = \MailWatch\Db::query($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if (false !== $operations) {
            ++$fields;
        }
    } else {
        $sth = \MailWatch\Db::query($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if (false !== $operations) {
            ++$fields;
        }
    }

    if ($rows > 0) {
        echo '<table cellspacing="1" width="100%" class="mail">' . "\n";
        if (null !== $title) {
            echo '<tr><th colspan=' . $fields . '>' . $title . '</TH></tr>' . "\n";
        }
        // Column headings
        echo ' <tr>' . "\n";
        if (false !== $operations) {
            echo '<td></td>';
        }

        foreach ($sth->fetch_fields() as $field) {
            echo '  <th>' . $field->name . '</th>' . "\n";
        }
        echo ' </tr>' . "\n";
        // Rows
        while ($row = $sth->fetch_row()) {
            echo ' <tr class="table-background">' . "\n";
            for ($f = 0; $f < $fields; ++$f) {
                echo '  <td>' . preg_replace(
                        "/,([^\s])/",
                        ', $1',
                        $row[$f]
                    ) . '</td>' . "\n";
            }
            echo ' </tr>' . "\n";
        }
        echo '</table>' . "\n";
    } else {
        echo \MailWatch\Translation::__('norowfound03') . "\n";
    }
    echo '<br>' . "\n";
    if ($pager) {
        $from = 0;
        if (isset($_GET['offset'])) {
            $from = (int)$_GET['offset'];
        }

        // Remove any ORDER BY clauses as this will slow the count considerably
        $sqlcount = '';
        if ($pos = strpos($sql, 'ORDER BY')) {
            $sqlcount = substr($sql, 0, $pos);
        }

        // Count the number of rows that would be returned by the query
        $sqlcount = 'SELECT COUNT(*) ' . strstr($sqlcount, 'FROM');
        $rows = Db::mysqli_result(\MailWatch\Db::query($sqlcount), 0);

        // Build the pager data
        $pager_options = [
            'mode' => 'Sliding',
            'perPage' => MAX_RESULTS,
            'delta' => 2,
            'totalItems' => $rows,
        ];
        $pager = Pager::factory($pager_options);

        //then we fetch the relevant records for the current page
        list($from, $to) = $pager->getOffsetByPageId();

        echo '<table cellspacing="1" class="mail" >
    <tr>
   <th colspan="5">' . \MailWatch\Translation::__('disppage03') . ' ' . $pager->getCurrentPageID() . ' ' . \MailWatch\Translation::__('of03') . ' ' . $pager->numPages() . ' - ' . \MailWatch\Translation::__('records03') . ' ' . $from . ' ' . \MailWatch\Translation::__('to0203') . ' ' . $to . ' ' . \MailWatch\Translation::__('of03') . ' ' . $pager->numItems() . '</th>
  </tr>
  <tr>
  <td align="center">' . "\n";
        //show the links
        echo $pager->links;
        echo '</td>
                </tr>
          </table>
</tr>
<tr>
  <td colspan="' . ('A' === $_SESSION['user_type'] ? '5' : '4') . '">';
    }
}

/**
 * @param $sql

function db_vertical_table($sql)
 * {
 * $sth = \MailWatch\Db::query($sql);
 * $rows = $sth->num_rows;
 * $fields = $sth->field_count;
 *
 * if ($rows > 0) {
 * echo '<table border="1" class="mail">' . "\n";
 * while ($row = $sth->fetch_row()) {
 * for ($f = 0; $f < $fields; $f++) {
 * $fieldInfo = $sth->fetch_field_direct($f);
 * echo " <tr>\n";
 * echo "  <td>" . $fieldInfo->name . "</td>\n";
 * echo "  <td>" . $row[$f] . "</td>\n";
 * echo " </tr>\n";
 * }
 * }
 * echo "</table>\n";
 * } else {
 * echo "No rows retrieved\n";
 * }
 * }
 */

/**
 * @return float
 */
function get_microtime()
{
    return microtime(true);
}

/**
 * @return string
 */
function page_creation_timer()
{
    if (!isset($GLOBALS['pc_start_time'])) {
        $GLOBALS['pc_start_time'] = get_microtime();
    } else {
        $pc_end_time = get_microtime();
        $pc_total_time = $pc_end_time - $GLOBALS['pc_start_time'];

        return sprintf(\MailWatch\Translation::__('pggen03') . ' %f ' . \MailWatch\Translation::__('seconds03') . "\n", $pc_total_time);
    }
}

/**
 * @param $dir
 *
 * @return bool|int
 *
 * @todo rewrite using SPL
 */
function count_files_in_dir($dir)
{
    $file_list_array = @scandir($dir);
    if (false === $file_list_array) {
        return false;
    }

    //there is always . and .. so reduce the count
    return count($file_list_array) - 2;
}

/**
 * @param string $message_headers
 *
 * @return array|bool
 */
function get_mail_relays($message_headers)
{
    $headers = explode('\\n', $message_headers);
    $relays = null;
    foreach ($headers as $header) {
        $header = preg_replace('/IPv6\:/', '', $header);
        if (preg_match_all('/Received.+\[(?P<ip>[\dabcdef.:]+)\]/', $header, $regs)) {
            foreach ($regs['ip'] as $relay) {
                if (false !== filter_var($relay, FILTER_VALIDATE_IP)) {
                    $relays[] = $relay;
                }
            }
        }
    }
    if (is_array($relays)) {
        return array_unique($relays);
    }

    return false;
}

/**
 * @param array $addresses
 * @param string $type
 *
 * @return string
 */
function address_filter_sql($addresses, $type)
{
    $sqladdr = '';
    $sqladdr_arr = [];
    switch ($type) {
        case 'A': // Administrator - show everything
            $sqladdr = '1=1';
            break;
        case 'U': // User - show only specific addresses
            foreach ($addresses as $address) {
                if (defined('FILTER_TO_ONLY') && FILTER_TO_ONLY) {
                    $sqladdr_arr[] = "to_address like '$address%'";
                } else {
                    $sqladdr_arr[] = "to_address like '$address%' OR from_address = '$address'";
                }
            }
            $sqladdr = implode(' OR ', $sqladdr_arr);
            break;
        case 'D': // Domain administrator
            foreach ($addresses as $address) {
                if (strpos($address, '@')) {
                    if (defined('FILTER_TO_ONLY') && FILTER_TO_ONLY) {
                        $sqladdr_arr[] = "to_address like '%$address%'";
                    } else {
                        $sqladdr_arr[] = "to_address like '%$address%' OR from_address = '$address'";
                    }
                } else {
                    if (defined('FILTER_TO_ONLY') && FILTER_TO_ONLY) {
                        $sqladdr_arr[] = "to_domain='$address'";
                    } else {
                        $sqladdr_arr[] = "to_domain='$address' OR from_domain='$address'";
                    }
                }
            }
            // Join together to form a suitable SQL WHERE clause
            $sqladdr = implode(' OR ', $sqladdr_arr);
            break;
        case 'H': // Host
            foreach ($addresses as $hostname) {
                $sqladdr_arr[] = "hostname='$hostname'";
            }
            $sqladdr = implode(' OR ', $sqladdr_arr);
            break;
    }

    return $sqladdr;
}

/**
 * @param $entry
 * @param mixed $username
 * @param mixed $password
 *
 * @return bool
 */

/**
 * @param string $username
 * @param string $password
 *
 * @return null|string
 */
function imap_authenticate($username, $password)
{
    $username = strtolower($username);

    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        //user has no mail but it is required for mailwatch
        return null;
    }

    if ('' !== $username && '' !== $password) {
        $mbox = imap_open(IMAP_HOST, $username, $password, null, 0);

        if (false === $mbox) {
            //auth faild
            return null;
        }

        if (defined('IMAP_AUTOCREATE_VALID_USER') && IMAP_AUTOCREATE_VALID_USER === true) {
            $sql = sprintf('SELECT username FROM users WHERE username = %s', \MailWatch\Sanitize::quote_smart($username));
            $sth = \MailWatch\Db::query($sql);
            if (0 === $sth->num_rows) {
                $sql = sprintf(
                    "REPLACE INTO users (username, fullname, type, password) VALUES (%s, %s,'U',NULL)",
                     \MailWatch\Sanitize::quote_smart($username),
                     \MailWatch\Sanitize::quote_smart($password)
                );
                \MailWatch\Db::query($sql);
            }
        }

        return $username;
    }

    return null;
}

/**
 * @param $name
 *
 * @return string
 */
function translate_etoi($name)
{
    $name = strtolower($name);
    $file = MS_SHARE_DIR . 'perl/MailScanner/ConfigDefs.pl';
    $fh = fopen($file, 'rb')
    or die(\MailWatch\Translation::__('dietranslateetoi03') . " $file\n");
    $etoi = [];
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, filesize($file)));
        if (preg_match('/^([^#].+)\s=\s([^#].+)/i', $line, $regs)) {
            // Lowercase all values
            $regs[1] = strtolower($regs[1]);
            $regs[2] = strtolower($regs[2]);
            $etoi[rtrim($regs[2])] = rtrim($regs[1]);
        }
    }
    fclose($fh) or die($php_errormsg);
    if (isset($etoi["$name"])) {
        return $etoi["$name"];
    }

    return $name;
}

/**
 * @param $input
 *
 * @return string
 */
function decode_header($input)
{
    // Remove white space between encoded-words
    $input = preg_replace('/(=\?[^?]+\?(q|b)\?[^?]*\?=)(\s)+=\?/i', '\1=?', $input);
    // For each encoded-word...
    while (preg_match('/(=\?([^?]+)\?(q|b)\?([^?]*)\?=)/i', $input, $matches)) {
        $encoded = $matches[1];
        //$charset = $matches[2];
        $encoding = $matches[3];
        $text = $matches[4];
        switch (strtolower($encoding)) {
            case 'b':
                $text = base64_decode($text);
                break;
            case 'q':
                $text = str_replace('_', ' ', $text);
                preg_match_all('/=([a-f0-9]{2})/i', $text, $matches);
                foreach ($matches[1] as $value) {
                    $text = str_replace('=' . $value, chr(hexdec($value)), $text);
                }
                break;
        }
        $input = str_replace($encoded, $text, $input);
    }

    return $input;
}

/**
 * @param $host
 *
 * @return bool
 */
function is_local($host)
{
    $host = strtolower($host);
    // Is RPC required to look-up??
    $sys_hostname = strtolower(rtrim(gethostname()));
    switch ($host) {
        case $sys_hostname:
        case gethostbyaddr('127.0.0.1'):
            return true;
        default:
            // Remote - RPC needed
            return false;
    }
}

/**
 * @param $id
 *
 * @return mixed
 */
function fixMessageId($id)
{
    $mta = \MailWatch\MailScanner::getConfVar('mta');
    if ('postfix' === $mta) {
        $id = str_replace('_', '.', $id);
    }

    return $id;
}

/**
 * @param $array
 *
 * @return array|number
 */
function mailwatch_array_sum($array)
{
    if (!is_array($array)) {
        // Not an array
        return [];
    }

    return array_sum($array);
}

/**
 * @param $file
 *
 * @return mixed
 */
function read_ruleset_default($file)
{
    $fh = fopen($file, 'rb') or die(\MailWatch\Translation::__('diereadruleset03') . " ($file)");
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, filesize($file)));
        if (preg_match('/(\S+)\s+(\S+)\s+(\S+)/', $line, $regs)) {
            if ('default' === strtolower($regs[2])) {
                // Check that it isn't another ruleset
                if (is_file($regs[3])) {
                    return read_ruleset_default($regs[3]);
                }

                return $regs[3];
            }
        }
    }

    return '';
}

/**
 * @return bool
 */
function is_rpc_client_allowed()
{
    // If no server address supplied
    if (!isset($_SERVER['SERVER_ADDR']) || empty($_SERVER['SERVER_ADDR'])) {
        return true;
    }
    // Get list of allowed clients
    if (defined('RPC_ALLOWED_CLIENTS') && (false === !RPC_ALLOWED_CLIENTS)) {
        // Read in space separated list
        $clients = explode(' ', constant('RPC_ALLOWED_CLIENTS'));
        // Validate each client type
        foreach ($clients as $client) {
            if ('allprivate' === $client && ip_in_range($_SERVER['SERVER_ADDR'], false, 'private')) {
                return true;
            }
            if ('local24' === $client) {
                // Get machine IP address from the hostname
                $ip = gethostbyname(rtrim(gethostname()));
                // Change IP address to a /24 network
                $ipsplit = explode('.', $ip);
                $ipsplit[3] = '0';
                $ip = implode('.', $ipsplit);
                if (ip_in_range($_SERVER['SERVER_ADDR'], "{$ip}/24")) {
                    return true;
                }
            }
            // All any others
            if (ip_in_range($_SERVER['SERVER_ADDR'], $client)) {
                return true;
            }
            // Try hostname
            $iplookup = gethostbyname($client);
            if ($client !== $iplookup && ip_in_range($_SERVER['SERVER_ADDR'], $iplookup)) {
                return true;
            }
        }

        // If all else fails
        return false;
    }

    return false;
}

/**
 * @param $host
 * @param $msg
 *
 * @return xmlrpcresp
 */
function xmlrpc_wrapper($host, $msg)
{
    $method = 'http';
    // Work out port
    if (defined('SSL_ONLY') && SSL_ONLY) {
        $port = 443;
        $method = 'https';
    } elseif (defined('RPC_PORT')) {
        $port = RPC_PORT;
        if (defined('RPC_SSL') && RPC_SSL) {
            $method = 'https';
            if (!defined('RPC_PORT')) {
                $port = 443;
            }
        }
    } else {
        $port = 80;
    }
    $client = new xmlrpc_client(constant('RPC_RELATIVE_PATH') . '/rpcserver.php', $host, $port);
    if (DEBUG) {
        $client->setDebug(1);
    }
    $client->setSSLVerifyPeer(0);
    $client->setSSLVerifyHost(0);

    return $client->send($msg, 0, $method);
}

/**
 * @param string $username username that should be checked if it exists
 *
 * @return bool true if user exists, else false
 */
function checkForExistingUser($username)
{
    $sqlQuery = "SELECT COUNT(username) AS counter FROM users WHERE username = '" . \MailWatch\Sanitize::safe_value($username) . "'";
    $row = \MailWatch\Db::query($sqlQuery)->fetch_object();

    return $row->counter > 0;
}

/**
 * @return array
 */
function checkConfVariables()
{
    $needed = [
        'ALLOWED_TAGS',
        'AUDIT',
        'AUDIT_DAYS_TO_KEEP',
        'AUTO_RELEASE',
        'DATE_FORMAT',
        'DB_DSN',
        'DB_HOST',
        'DB_NAME',
        'DB_PASS',
        'DB_TYPE',
        'DB_USER',
        'DEBUG',
        'DISPLAY_IP',
        'DISTRIBUTED_SETUP',
        'DOMAINADMIN_CAN_RELEASE_DANGEROUS_CONTENTS',
        'DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS',
        'FILTER_TO_ONLY',
        'FROMTO_MAXLEN',
        'HIDE_HIGH_SPAM',
        'HIDE_NON_SPAM',
        'HIDE_UNKNOWN',
        'IMAGES_DIR',
        'LANG',
        'LDAP_DN',
        'LDAP_EMAIL_FIELD',
        'LDAP_FILTER',
        'LDAP_HOST',
        'LDAP_MS_AD_COMPATIBILITY',
        'LDAP_PASS',
        'LDAP_PORT',
        'LDAP_PROTOCOL_VERSION',
        'LDAP_USER',
        'LDAP_USERNAME_FIELD',
        'LISTS',
        'MAIL_LOG',
        'MAILWATCH_HOME',
        'MAILWATCH_MAIL_HOST',
        'MAILWATCH_MAIL_PORT',
        'MAILWATCH_FROM_ADDR',
        'MAILWATCH_HOSTURL',
        'MAX_RESULTS',
        'MEMORY_LIMIT',
        'MS_CONFIG_DIR',
        'MS_EXECUTABLE_PATH',
        'MS_LIB_DIR',
        'MS_LOG',
        'MS_SHARE_DIR',
        'MSRE',
        'MSRE_RELOAD_INTERVAL',
        'MSRE_RULESET_DIR',
        'MW_LOGO',
        'PROXY_PASS',
        'PROXY_PORT',
        'PROXY_SERVER',
        'PROXY_USER',
        'QUARANTINE_DAYS_TO_KEEP',
        'QUARANTINE_FILTERS_COMBINED',
        'QUARANTINE_MSG_BODY',
        'QUARANTINE_REPORT_DAYS',
        'QUARANTINE_REPORT_FROM_NAME',
        'QUARANTINE_REPORT_SUBJECT',
        'QUARANTINE_SENDMAIL_PATH',
        'QUARANTINE_SUBJECT',
        'QUARANTINE_USE_FLAG',
        'QUARANTINE_USE_SENDMAIL',
        'RECORD_DAYS_TO_KEEP',
        'RESOLVE_IP_ON_DISPLAY',
        'RPC_ALLOWED_CLIENTS',
        'RPC_ONLY',
        'RPC_RELATIVE_PATH',
        'SA_DIR',
        'SA_MAXSIZE',
        'SA_PREFS',
        'SA_RULES_DIR',
        'SHOW_DOC',
        'SHOW_MORE_INFO_ON_REPORT_GRAPH',
        'SHOW_SFVERSION',
        'SSL_ONLY',
        'STATUS_REFRESH',
        'STRIP_HTML',
        'SUBJECT_MAXLEN',
        'TEMP_DIR',
        'TIME_FORMAT',
        'TIME_ZONE',
        'USE_LDAP',
        'USE_PROXY',
        'VIRUS_INFO',
        'DISPLAY_VIRUS_REPORT',
    ];

    $obsolete = [
        'MS_LOGO',
        'QUARANTINE_MAIL_HOST',
        'QUARANTINE_MAIL_PORT',
        'QUARANTINE_FROM_ADDR',
        'QUARANTINE_REPORT_HOSTURL',
        'CACHE_DIR',
        'LDAP_SSL',
        'TTF_DIR',
        'PROXY_TYPE',
    ];

    $optional = [
        'RPC_PORT' => ['description' => 'needed if RPC_ONLY mode is enabled'],
        'RPC_SSL' => ['description' => 'needed if RPC_ONLY mode is enabled'],
        'RPC_REMOTE_SERVER' => ['description' => 'needed to show number of mails in postfix queues on remote server (RPC)'],
        'VIRUS_REGEX' => ['description' => 'needed in distributed setup'],
        'LDAP_BIND_PREFIX' => ['description' => 'needed when using LDAP authentication'],
        'LDAP_BIND_SUFFIX' => ['description' => 'needed when using LDAP authentication'],
        'EXIM_QUEUE_IN' => ['description' => 'needed only if using Exim as MTA'],
        'EXIM_QUEUE_OUT' => ['description' => 'needed only if using Exim as MTA'],
        'PWD_RESET_FROM_NAME' => ['description' => 'needed if Password Reset feature is enabled'],
        'PWD_RESET_FROM_ADDRESS' => ['description' => 'needed if Password Reset feature is enabled'],
        'MAILQ' => ['description' => 'needed when using Exim or Sendmail to display the inbound/outbound mail queue lengths'],
        'MAIL_SENDER' => ['description' => 'needed if you use Exim or Sendmail Queue'],
        'SESSION_NAME' => ['description' => 'needed if experiencing session conflicts'],
        'SENDMAIL_QUEUE_IN' => ['description' => 'needed only if using Sendmail as MTA'],
        'SENDMAIL_QUEUE_OUT' => ['description' => 'needed only if using Sendmail as MTA'],
        'USER_SELECTABLE_LANG' => ['description' => 'comma separated list of codes for languages the users can use eg. "de,en,fr,it,ja,nl,pt_br"'],
        'MAILWATCH_SMTP_HOSTNAME' => ['description' => 'needed only if you use a remote SMTP server to send MailWatch emails'],
        'SESSION_TIMEOUT' => ['description' => 'needed if you want to override the default session timeout'],
        'STATUSGRAPH_INTERVAL' => ['description' => 'to change the interval of the status chart (default 60 minutes)'],
        'ALLOW_NO_USER_DOMAIN' => ['description' => 'allow usernames not in mail format for domain admins and regular users'],
        'ENABLE_SUPER_DOMAIN_ADMINS' => ['description' => 'allows domain admins to change domain admins from the same domain'],
        'USE_IMAP' => ['description' => 'use IMAP for user authentication'],
        'IMAP_HOST' => ['description' => 'IMAP host to be used for user authentication'],
        'IMAP_AUTOCREATE_VALID_USER' => ['description' => 'enable to autorcreate user from valid imap login'],
    ];

    $results = [];
    $neededMissing = [];
    foreach ($needed as $item) {
        if (!defined($item)) {
            $neededMissing[] = $item;
        }
    }
    $results['needed']['count'] = count($neededMissing);
    $results['needed']['list'] = $neededMissing;

    $obsoleteStillPresent = [];
    foreach ($obsolete as $item) {
        if (defined($item)) {
            $obsoleteStillPresent[] = $item;
        }
    }
    $results['obsolete']['count'] = count($obsoleteStillPresent);
    $results['obsolete']['list'] = $obsoleteStillPresent;

    $optionalMissing = [];
    foreach ($optional as $key => $item) {
        if (!defined($key)) {
            $optionalMissing[$key] = $item;
        }
    }
    $results['optional']['count'] = count($optionalMissing);
    $results['optional']['list'] = $optionalMissing;

    return $results;
}

/**
 * @param $ip
 * @param bool|string $net
 * @param bool|string $privateLocal
 *
 * @return bool
 */
function ip_in_range($ip, $net = false, $privateLocal = false)
{
    if ('private' === $privateLocal) {
        $privateIPSet = new \IPSet\IPSet([
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            'fc00::/7',
            'fe80::/10',
        ]);

        return $privateIPSet->match($ip);
    }

    if ('local' === $privateLocal) {
        $localIPSet = new \IPSet\IPSet([
            '127.0.0.0/8',
            '::1',
        ]);

        return $localIPSet->match($ip);
    }

    if (false === $privateLocal && false !== $net) {
        $network = new \IPSet\IPSet([
            $net,
        ]);

        return $network->match($ip);
    }

    //return false to fail gracefully
    return false;
}
