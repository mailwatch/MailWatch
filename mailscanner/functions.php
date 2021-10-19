<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2021  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
if (PHP_VERSION_ID < 50300) {
    error_reporting(E_ALL);
} else {
    // E_DEPRECATED added in PHP 5.3
    error_reporting(E_ALL ^ E_DEPRECATED ^ E_STRICT);
}

// Read in MailWatch configuration file
if (!is_readable(__DIR__ . '/conf.php')) {
    die(__('cannot_read_conf'));
}
require_once __DIR__ . '/conf.php';


// more secure session cookies
ini_set('session.use_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

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

// Load Language File
// If the translation file indicated at conf.php doesn´t exists, the system will load the English version.
if (!defined('LANG')) {
    define('LANG', 'en');
}
$langCode = LANG;
// If the user is allowed to select the language for the gui check which language he has choosen or create the cookie with the default lang
if (defined('USER_SELECTABLE_LANG')) {
    if (isset($_COOKIE['MW_LANG']) && checkLangCode($_COOKIE['MW_LANG'])) {
        $langCode = $_COOKIE['MW_LANG'];
    } else {
        setcookie('MW_LANG', LANG, 0, $params['path'], $params['domain'], $session_cookie_secure, false);
    }
}

// Load the lang file or en if the spicified language is not available
if (!is_file(__DIR__ . '/languages/' . $langCode . '.php')) {
    $lang = require __DIR__ . '/languages/en.php';
} else {
    $lang = require __DIR__ . '/languages/' . $langCode . '.php';
}

// Load the lang file or en if the spicified language is not available
if (!is_file(__DIR__ . '/languages/' . LANG . '.php')) {
    $systemLang = require __DIR__ . '/languages/en.php';
} else {
    $systemLang = require __DIR__ . '/languages/' . LANG . '.php';
}

$missingConfigEntries = checkConfVariables();
if ($missingConfigEntries['needed']['count'] !== 0) {
    $br = '';
    if (PHP_SAPI !== 'cli') {
        $br = '<br>';
    }
    echo __('missing_conf_entries') . $br . PHP_EOL;
    foreach ($missingConfigEntries['needed']['list'] as $missingConfigEntry) {
        echo '- ' . $missingConfigEntry . $br . PHP_EOL;
    }
    die();
}

require_once __DIR__ . '/database.php';

// Set PHP path to use local PEAR modules only
set_include_path(
    '.' . PATH_SEPARATOR .
    MAILWATCH_HOME . '/lib/pear' . PATH_SEPARATOR .
    MAILWATCH_HOME . '/lib/xmlrpc'
);

//ForceUTF8
require_once __DIR__ . '/lib/ForceUTF8/Encoding.php';

//HTLMPurifier
require_once __DIR__ . '/lib/htmlpurifier/HTMLPurifier.standalone.php';

//Enforce SSL if SSL_ONLY=true
if (PHP_SAPI !== 'cli' && SSL_ONLY && !empty($_SERVER['PHP_SELF'])) {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        header('Location: https://' . sanitizeInput($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']));
        exit;
    }
}

//security headers
if (PHP_SAPI !== 'cli') {
    header('X-XSS-Protection: 1; mode=block');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    unset($session_cookie_secure);
    session_start();
}

// set default timezone
date_default_timezone_set(TIME_ZONE);

// XML-RPC
if (!function_exists('xml_parser_create') && (!ini_get('enable_dl') || @dl('xml.so') !== true)) {
    die(__('phpxmlnotloaded03'));
}
require_once __DIR__ . '/lib/xmlrpc/xmlrpc.inc';
require_once __DIR__ . '/lib/xmlrpc/xmlrpcs.inc';
require_once __DIR__ . '/lib/xmlrpc/xmlrpc_wrappers.inc';

include __DIR__ . '/postfix.inc.php';
include __DIR__ . '/msmail.inc.php';

function getVirusRegex($scanner = null)
{
    /*
     For reporting of Virus names and statistics a regular expression matching
     the output of your virus scanner is required.  As Virus names vary across
     the vendors and are therefore impossible to match - you can only define one
     scanner as your primary scanner - this should be the scanner you wish to
     report against.  It defaults to the first scanner found in MailScanner.conf.

     Please submit any new regular expressions to the MailWatch mailing-list or
     open an issue on GitHub.

     If you are running MailWatch in DISTRIBUTED_MODE or you wish to override the
     selection of the regular expression - you will need to add one of the following
     statements to conf.php and set the regular expression manually.
    */
    // define('VIRUS_REGEX', '<<your regexp here>>');
    // define('VIRUS_REGEX', '/(\S+) was infected by (\S+)/');
    if ($scanner === null) {
        $scanner = get_primary_scanner();
    }
    if (!defined('VIRUS_REGEX') && DISTRIBUTED_SETUP === true) {
        // Have to set manually as running in DISTRIBUTED_MODE
        die('<B>' . __('dieerror03') . "</B><BR>\n&nbsp;" . __('dievirus03') . "\n");
    }

    if (defined('VIRUS_REGEX')) {
        return VIRUS_REGEX;
    }

    $regex = null;
    switch ($scanner) {
        case 'none':
            $regex = '/^Dummy$/';
            break;
        case 'sophos':
            $regex = '/>>> Virus \'(?P<virus>\S+)\' found in (?P<file>.*)/';
            break;
        case 'sophossavi':
            $regex = '/(?P<file>\S+) was infected by (?P<virus>\S+)/';
            break;
        case 'clamav':
            $regex = '/(?P<file>.+) contains (?P<virus>\S+)/';
            break;
        case 'clamd':
        case 'clamavmodule':
            $regex = '/(?P<file>.+) was infected: (?P<virus>\S+)/';
            break;
        case 'f-prot':
        case 'f-prot-6':
        case 'f-protd-6':
            $regex = '/(?P<file>.+) Infection: (?P<virus>\S+)/';
            break;
        case 'mcafee':
        case 'mcafee6':
            $regex = '/(?P<file>.+) Found the (?P<virus>\S+) virus !!!/';
            break;
        case 'f-secure':
        case 'f-secure-12':
            $regex = '/(?P<file>.+) Infected: (?P<virus>\S+)/';
            break;
        case 'avg':
        case 'norman':
        case 'trend':
            $regex = '/Found virus (?P<virus>\S+) in file (?P<file>\S+)/';
            break;
        case 'bitdefender':
            $regex = '/(?P<file>\S+) Found virus (?P<virus>\S+)/';
            break;
        case 'kaspersky-4.5':
            $regex = '/(?P<file>.+) INFECTED (?P<virus>\S+)/';
            break;
        case 'etrust':
            $regex = '/(?P<file>\S+) is infected by virus: (?P<virus>\S+)/';
            break;
        case 'nod32-1.99':
            $regex = '/Found virus (?P<virus>\S+) in (?P<file>\S+)/';
            break;
        case 'antivir':
            $regex = '/ALERT: \[(?P<virus>\S+) \S+\]/';
            break;
        case 'avast':
            $regex = '/Avast: found (?P<virus>.+) in (?P<file>.*)/';
            break;
        case 'esets':
        case 'esetsefs':
            $regex = '/Esets: found (?P<virus>\S+) in (?P<file>\S+)/';
            break;
        //default:
        // die("<B>" . __('dieerror03') . "</B><BR>\n&nbsp;" . __('diescanner03' . "\n");
        // break;
    }

    return $regex;
}

///////////////////////////////////////////////////////////////////////////////
// Functions
///////////////////////////////////////////////////////////////////////////////
/**
 * @return string
 */
function mailwatch_version()
{
    return '1.2.18';
}

/**
 * @param $number
 * @return string
 */
function suppress_zeros($number)
{
    if (abs($number - 0.0) < 0.1) {
        return '.';
    }

    return $number;
}

function disableBrowserCache()
{
    header('Expires: Sat, 10 May 2003 00:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, M d Y H:i:s') . ' GMT');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Cache-Control: post-check=0, pre-check=0', false);
}

/**
 * @param $title
 * @param int $refresh
 * @param bool|true $cacheable
 * @param bool|false $report
 * @return Filter|int
 */
function html_start($title, $refresh = 0, $cacheable = true, $report = false)
{
    if (PHP_SAPI !== 'cli') {
        if (!$cacheable) {
            // Cache control (as per PHP website)
            disableBrowserCache();
        } else {
            // calc an offset of 24 hours
            $offset = 3600 * 48;
            // calc the string in GMT not localtime and add the offset
            $expire = 'Expires: ' . gmdate('D, d M Y H:i:s', time() + $offset) . ' GMT';
            //output the HTTP header
            header($expire);
            header('Cache-Control: store, cache, must-revalidate, post-check=0, pre-check=1');
            header('Pragma: cache');
        }
    }

    // Check for a privilege change
    if (checkPrivilegeChange($_SESSION['myusername']) === true) {
        header('Location: logout.php?error=timeout');
        die();
    }

    if (checkLoginExpiry($_SESSION['myusername']) === true) {
        header('Location: logout.php?error=timeout');
        die();
    } else {
        if ($refresh === 0) {
            // User is moving about on non-refreshing pages, keep session alive
            updateLoginExpiry($_SESSION['myusername']);
        }
    }

    echo page_creation_timer();
    echo '<!DOCTYPE HTML>' . "\n";
    echo '<html>' . "\n";
    echo '<head>' . "\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . "\n";
    echo '<link rel="shortcut icon" href="images/favicon.png" >' . "\n";
    echo '<script type="text/javascript">';
    echo '' . java_time() . '';
    //$current_url = "".MAILWATCH_HOME."/status.php";
    //if($_SERVER['SCRIPT_FILENAME'] === $active_url){
    echo '</script>';
    if ($report) {
        echo '<title>' . __('mwfilterreport03') . ' ' . $title . ' </title>' . "\n";
        if (!isset($_SESSION['filter'])) {
            require_once __DIR__ . '/filter.inc.php';
            $filter = new Filter();
            $_SESSION['filter'] = $filter;
        } else {
            // Use existing filters
            $filter = $_SESSION['filter'];
        }
        audit_log(__('auditlogreport03', true) . ' ' . $title);
    } else {
        echo '<title>' . __('mwforms03') . $title . '</title>' . "\n";
    }
    echo '<link rel="stylesheet" type="text/css" href="./style.css">' . "\n";
    if (is_file(__DIR__ . '/skin.css')) {
        echo '<link rel="stylesheet" href="./skin.css" type="text/css">';
    }

    if ($refresh > 0) {
        echo '<meta http-equiv="refresh" content="' . $refresh . '">' . "\n";
    }

    if (isset($_GET['id'])) {
        $message_id = trim(htmlentities(safe_value(sanitizeInput($_GET['id']))), ' ');
        if (!validateInput($message_id, 'msgid')) {
            $message_id = '';
        }
    } else {
        $message_id = '';
    }
    echo '</head>' . "\n";
    echo '<body onload="updateClock(); setInterval(\'updateClock()\', 1000 )">' . "\n";
    echo '<table border="0" cellpadding="5" width="100%">' . "\n";
    echo '<tr class="noprint">' . "\n";
    echo '<td>' . "\n";
    echo '<table border="0" cellpadding="0" cellspacing="0">' . "\n";
    echo '<tr>' . "\n";
    echo '<td align="left"><a href="index.php" class="logo"><img src=".' . IMAGES_DIR . MW_LOGO . '" alt="' . __('mailwatchtitle03') . '"></a></td>' . "\n";
    echo '</tr>' . "\n";
    echo '<tr>' . "\n";
    echo '<td valign="bottom" align="left" class="jump">' . "\n";
    echo '<form action="./detail.php">' . "\n";
    echo '<p>' . __('jumpmessage03') . '<input type="text" name="id" value="' . $message_id . '"></p>' . "\n";
    echo '<input type="hidden" name="token" value="' . $_SESSION['token'] . '">' . "\n";
    echo '</form>' . "\n";
    echo '</td>';
    echo '</tr>';
    echo '</table>' . "\n";
    echo '<table cellspacing="1" class="mail">' . "\n";
    echo '<tr><td class="heading" align="center">' . __('cuser03') . '</td><td class="heading" align="center">' . __('cst03') . '</td></tr>' . "\n";
    echo '<tr><td>' . $_SESSION['fullname'] . '</td><td><span id="clock">&nbsp;</span></td></tr>' . "\n";
    echo '</table>' . "\n";
    echo '</td>' . "\n";

    if ($_SESSION['user_type'] === 'A' || $_SESSION['user_type'] === 'D') {
        echo '  <td align="center" valign="top">' . "\n";

        // Status table
        echo '   <table border="0" cellpadding="1" cellspacing="1" class="mail">' . "\n";
        echo '    <tr><th colspan="3">' . __('status03') . '</th></tr>' . "\n";

        printServiceStatus();
        printAverageLoad();

        if ($_SESSION['user_type'] === 'A') {
            printMTAQueue();
            printFreeDiskSpace();
        }
        echo '  </table>' . "\n";
        echo '  </td>' . "\n";

        printTrafficGraph();
    }

    echo '<td align="center" valign="top">' . "\n";
    printTodayStatistics();
    echo '  </td>' . "\n";

    echo ' </tr>' . "\n";

    printNavBar();
    echo '
 <tr>
  <td colspan="' . ($_SESSION['user_type'] === 'A' ? '5' : '4') . '">';

    if ($report) {
        $return_items = $filter;
    } else {
        $return_items = $refresh;
    }

    return $return_items;
}

function printColorCodes()
{
    echo '   <table border="0" cellpadding="1" cellspacing="3"  align="center" class="mail colorcodes">' . "\n";
    echo '    <tr><td class="infected"></td> <td>' . __('badcontentinfected03') . '</td>' . "\n";
    echo '    <td class="spam"></td> <td>' . __('spam103'). ' </td>' . "\n";
    echo '    <td class="highspam"></td> <td>' . __('highspam03') . '</td>' . "\n";
    if (get_conf_truefalse('mcpchecks')) {
        echo '    <td class="mcp"></td> <td>' . __('mcp03') . '</td>' . "\n";
        echo '    <td class="highmcp"></td> <td>' . __('highmcp03') . '</td>' . "\n";
    }
    echo '    <td class="whitelisted"></td> <td>' . __('whitelisted03') . '</td>' . "\n";
    echo '    <td class="blacklisted"></td> <td>' . __('blacklisted03') . '</td>' . "\n";
    echo '    <td class="notscanned"></td> <td>' . __('notverified03') . '</td>' . "\n";
    echo '    <td class="clean"></td> <td>' . __('clean03') . '</td></tr>' . "\n";
    echo '   </table><br>' . "\n";
}

function printServiceStatus()
{
    // MailScanner running?
    if (!DISTRIBUTED_SETUP) {
        $no = '<span class="yes">&nbsp;' . __('no03') . '&nbsp;</span>' . "\n";
        $yes = '<span class="no">&nbsp;' . __('yes03') . '&nbsp;</span>' . "\n";
        exec('ps ax | grep MailScanner | grep -v grep', $output);
        if (count($output) > 0) {
            $running = $yes;
            $procs = count($output) - 1 . ' ' . __('children03');
        } else {
            $running = $no;
            $procs = count($output) . ' ' . __('procs03');
        }
        echo '     <tr><td>' . __('mailscanner03') . '</td><td align="center">' . $running . '</td><td align="right">' . $procs . '</td></tr>' . "\n";

        $output = 0;
        // is MTA running
        $mta = get_conf_var('mta');
        if ($mta === 'msmail') {
            exec('ps ax | grep postfix | grep -v grep | grep -v php', $output);
            if (count($output) > 0) {
                $running = $yes;
            } else {
                $running = $no;
            }
            $procs = count($output) . ' ' . __('procs03');
            echo '    <tr><td>' . ucwords('postfix') . __('colon99') . '</td>'
                . '<td align="center">' . $running . '</td><td align="right">' . $procs . '</td></tr>' . "\n";

            $output = 0;
            exec('ps ax | grep MSMilter | grep -v grep', $output);
            if (count($output) > 0) {
                $running = $yes;
                $procs = count($output) - 1 . ' ' . __('children03');
            } else {
                $running = $no;
                $procs = count($output) . ' ' . __('procs03');
            }
            echo '    <tr><td>' . 'MSMilter' . __('colon99') . '</td>'
                . '<td align="center">' . $running . '</td><td align="right">' . $procs . '</td></tr>' . "\n";
        } else {
            $psExecCommand = "ps ax | grep $mta | grep -v grep | grep -v php";
            if ($mta === 'postfix') {
                $psExecCommand = 'ps -U postfix -u postfix | grep -v MailScanner | grep -v "MailWatch SQL"';
            }
            exec($psExecCommand, $output);
            if (count($output) > 0) {
                $running = $yes;
            } else {
                $running = $no;
            }
            $procs = count($output) . ' ' . __('procs03');
            echo '    <tr><td>' . ucwords($mta) . __('colon99') . '</td>'
                . '<td align="center">' . $running . '</td><td align="right">' . $procs . '</td></tr>' . "\n";
        }
    }
}

function printAverageLoad()
{
    // Load average
    if (!DISTRIBUTED_SETUP && file_exists('/proc/loadavg')) {
        $loadavg = file('/proc/loadavg');
        $loadavg = explode(' ', $loadavg[0]);
        $la_1m = $loadavg[0];
        $la_5m = $loadavg[1];
        $la_15m = $loadavg[2];
        echo '
        <tr>
            <td align="left" rowspan="3">' . __('loadaverage03') . '&nbsp;</td>
            <td align="right">' . __('1minute03') . '&nbsp;</td>
            <td align="right">' . $la_1m . '</td>
        </tr>
        <tr>
            <td align="right" colspan="1">' . __('5minutes03') . '&nbsp;</td>
            <td align="right">' . $la_5m . '</td>
        </tr>
        <tr>
            <td align="right" colspan="1">' . __('15minutes03') . '&nbsp;</td>
            <td align="right">' . $la_15m . '</td>
        </tr>
        ' . "\n";
    } elseif (!DISTRIBUTED_SETUP && file_exists('/usr/bin/uptime')) {
        $loadavg = shell_exec('/usr/bin/uptime');
        $loadavg = explode(' ', $loadavg);
        $la_1m = rtrim($loadavg[count($loadavg) - 3], ',');
        $la_5m = rtrim($loadavg[count($loadavg) - 2], ',');
        $la_15m = rtrim($loadavg[count($loadavg) - 1]);
        echo '
        <tr>
            <td align="left" rowspan="3">' . __('loadaverage03') . '&nbsp;</td>
            <td align="right">' . __('1minute03') . '&nbsp;</td>
            <td align="right">' . $la_1m . '</td>
        </tr>
        </tr>
            <td align="right" colspan="1">' . __('5minutes03') . '&nbsp;</td>
            <td align="right">' . $la_5m . '</td>
        </tr>
            <td align="right" colspan="1">' . __('15minutes03') . '&nbsp;</td>
            <td align="right">' . $la_15m . '</td>
        </tr>
        ' . "\n";
    }
}

function printMTAQueue()
{
    // Display the MTA queue
    // Postfix if mta = postfix
    if (get_conf_var('MTA', true) === 'postfix') {
        // Mail Queues display
        $incomingdir = get_conf_var('incomingqueuedir', true);
        $outgoingdir = get_conf_var('outgoingqueuedir', true);
        $inq = null;
        $outq = null;
        if (is_readable($incomingdir) || is_readable($outgoingdir)) {
            $inq = postfixinq();
            $outq = postfixallq() - $inq;
        } elseif (!defined('RPC_REMOTE_SERVER')) {
            echo '    <tr><td colspan="3">' . __('verifyperm03') . ' ' . $incomingdir . ' ' . __('and03') . ' ' . $outgoingdir . '</td></tr>' . "\n";
        }

        if (defined('RPC_REMOTE_SERVER')) {
            $pqerror = '';
            $servers = explode(' ', RPC_REMOTE_SERVER);

            for ($i = 0, $count_servers = count($servers); $i < $count_servers; $i++) {
                if ($servers[$i] !== gethostbyname(gethostname())) {
                    $msg = new xmlrpcmsg('postfix_queues', []);
                    $rsp = xmlrpc_wrapper($servers[$i], $msg);
                    if ($rsp->faultCode() === 0) {
                        $response = php_xmlrpc_decode($rsp->value());
                        $inq += $response['inq'];
                        $outq += $response['outq'];
                    } else {
                        $pqerror .= 'XML-RPC Error: ' . $rsp->faultString();
                    }
                }
                if ($pqerror !== '') {
                    echo '    <tr><td colspan="3">' . __('errorWarning03') . ' ' . $pqerror . '</td></tr>' . "\n";
                }
            }
        }
        if ($inq !== null && $outq !== null) {
            echo '    <tr><td colspan="3" class="heading" align="center">' . __('mailqueue03') . '</td></tr>' . "\n";
            echo '    <tr><td colspan="2"><a href="postfixmailq.php">' . __('inbound03') . '</a></td><td align="right">' . $inq . '</td></tr>' . "\n";
            echo '    <tr><td colspan="2"><a href="postfixmailq.php">' . __('outbound03') . '</a></td><td align="right">' . $outq . '</td></tr>' . "\n";
        }
    } elseif (get_conf_var('MTA', true) === 'msmail') {
        $incomingdir = get_conf_var('incomingqueuedir', true);
        $outgoingdir = get_conf_var('outgoingqueuedir', true);
        $incomingdir2 = '/var/spool/postfix/incoming';
        $inq = null;
        $outq = null;
        $inq2 = null;
        $outq2 = null;
        if (is_readable($incomingdir) || is_readable($outgoingdir)) {
            $inq = genericqueue($incomingdir);
            $outq = genericqueue($outgoingdir);
            $inq2 = genericqueue($incomingdir2);
            $outq2 = postfixallq() - $inq2;
        } elseif (!defined('RPC_REMOTE_SERVER')) {
            echo '    <tr><td colspan="3">' . __('verifyperm03') . ' ' . $incomingdir . ' ' . __('and03') . ' ' . $outgoingdir . '</td></tr>' . "\n";
        }

        if (defined('RPC_REMOTE_SERVER')) {
            $pqerror = '';
            $servers = explode(' ', RPC_REMOTE_SERVER);

            for ($i = 0, $count_servers = count($servers); $i < $count_servers; $i++) {
                if ($servers[$i] !== gethostbyname(gethostname())) {
                    $msg = new xmlrpcmsg('postfix_queues', []);
                    $rsp = xmlrpc_wrapper($servers[$i], $msg);
                    if ($rsp->faultCode() === 0) {
                        $response = php_xmlrpc_decode($rsp->value());
                        $inq2 += $response['inq'];
                        $outq2 += $response['outq'];
                    } else {
                        $pqerror .= 'XML-RPC Error: ' . $rsp->faultString();
                    }
                }
                if ($pqerror !== '') {
                    echo '    <tr><td colspan="3">' . __('errorWarning03') . ' ' . $pqerror . '</td></tr>' . "\n";
                }
            }
        }
        if ($inq !== null && $outq !== null && $inq2 !== null && $outq2 !== null) {
            echo '    <tr><td colspan="3" class="heading" align="center">' . __('mailqueue03') . '</td></tr>' . "\n";
            echo '    <tr><td colspan="2"><a href="msmailq.php">Milter ' . __('inbound03') . '</a></td><td align="right">' . $inq . '</td></tr>' . "\n";
            echo '    <tr><td colspan="2"><a href="msmailq.php">Milter ' .  __('outbound03') . '</a></td><td align="right">' . $outq . '</td></tr>' . "\n";
            echo '    <tr><td colspan="2"><a href="postfixmailq.php">Postfix ' . __('inbound03') . '</a></td><td align="right">' . $inq2 . '</td></tr>' . "\n";
            echo '    <tr><td colspan="2"><a href="postfixmailq.php">Postfix ' . __('outbound03') . '</a></td><td align="right">' . $outq2 . '</td></tr>' . "\n";
        }
        // Else use MAILQ from conf.php which is for Sendmail or Exim
    } elseif (defined('MAILQ') && MAILQ === true && !DISTRIBUTED_SETUP) {
        if (get_conf_var('MTA') === 'exim') {
            $inq = exec('sudo ' . EXIM_QUEUE_IN . ' 2>&1');
            $outq = exec('sudo ' . EXIM_QUEUE_OUT . ' 2>&1');
        } else {
            $cmd = exec('sudo ' . SENDMAIL_QUEUE_IN . ' 2>&1');
            preg_match('/(Total requests: )(.*)/', $cmd, $output_array);
            $inq = $output_array[2];
            $cmd = exec('sudo ' . SENDMAIL_QUEUE_OUT . ' 2>&1');
            preg_match('/(Total requests: )(.*)/', $cmd, $output_array);
            $outq = $output_array[2];
        }
        echo '    <tr><td colspan="3" class="heading" align="center">' . __('mailqueue03') . '</td></tr>' . "\n";
        echo '    <tr><td colspan="2"><a href="mailq.php?token=' . $_SESSION['token'] . '&amp;queue=inq">' . __('inbound03') . '</a></td><td align="right">' . $inq . '</td></tr>' . "\n";
        echo '    <tr><td colspan="2"><a href="mailq.php?token=' . $_SESSION['token'] . '&amp;queue=outq">' . __('outbound03') . '</a></td><td align="right">' . $outq . '</td></tr>' . "\n";
    }
}

function printFreeDiskSpace()
{
    if (!DISTRIBUTED_SETUP) {
        // Drive display
        echo '    <tr><td colspan="3" class="heading" align="center">' . __('freedspace03') . '</td></tr>' . "\n";
        foreach (get_disks() as $disk) {
            $free_space = disk_free_space($disk['mountpoint']);
            $total_space = disk_total_space($disk['mountpoint']);
            $percent = '<span>';
            if (round($free_space / $total_space, 2) <= 0.1) {
                $percent = '<span class="error">';
            }
            $percent .= ' [';
            $percent .= round($free_space / $total_space, 2) * 100;
            $percent .= '%] ';
            $percent .= '</span>';
            echo '    <tr><td>' . $disk['mountpoint'] . '</td><td colspan="2" align="right">' . formatSize($free_space) . $percent . '</td></tr>' . "\n";
        }
    }
}

function printTodayStatistics()
{
    $sql = '
 SELECT
  COUNT(*) AS processed,
  SUM(
   CASE WHEN (
    (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    AND (isspam=0 OR isspam IS NULL)
    AND (ishighspam=0 OR ishighspam IS NULL)
    AND (ismcp=0 OR ismcp IS NULL)
    AND (ishighmcp=0 OR ishighmcp IS NULL)
   ) THEN 1 ELSE 0 END
  ) AS clean,
  ROUND((
   SUM(
    CASE WHEN (
     (virusinfected=0 OR virusinfected IS NULL)
     AND (nameinfected=0 OR nameinfected IS NULL)
     AND (otherinfected=0 OR otherinfected IS NULL)
     AND (isspam=0 OR isspam IS NULL)
     AND (ishighspam=0 OR ishighspam IS NULL)
     AND (ismcp=0 OR ismcp IS NULL)
     AND (ishighmcp=0 OR ishighmcp IS NULL)
    ) THEN 1 ELSE 0 END
   )/COUNT(*))*100,1
  ) AS cleanpercent,
  SUM(
   CASE WHEN
    virusinfected>0
   THEN 1 ELSE 0 END
  ) AS viruses,
  ROUND((
   SUM(
    CASE WHEN
     virusinfected>0
    THEN 1 ELSE 0 END
   )/COUNT(*))*100,1
  ) AS viruspercent,
  SUM(
   CASE WHEN
    nameinfected>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    -- AND (isspam=0 OR isspam IS NULL)
    -- AND (ishighspam=0 OR ishighspam IS NULL)
   THEN 1 ELSE 0 END
  ) AS blockedfiles,
  ROUND((
   SUM(
    CASE WHEN
     nameinfected>0
     AND (virusinfected=0 OR virusinfected IS NULL)
     AND (otherinfected=0 OR otherinfected IS NULL)
     -- AND (isspam=0 OR isspam IS NULL)
     -- AND (ishighspam=0 OR ishighspam IS NULL)
    THEN 1 ELSE 0 END
   )/COUNT(*))*100,1
  ) AS blockedfilespercent,
  SUM(
   CASE WHEN
    otherinfected>0
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (isspam=0 OR isspam IS NULL)
    AND (ishighspam=0 OR ishighspam IS NULL)
   THEN 1 ELSE 0 END
  ) AS otherinfected,
  ROUND((
   SUM(
    CASE WHEN
     otherinfected>0
     AND (nameinfected=0 OR nameinfected IS NULL)
     AND (virusinfected=0 OR virusinfected IS NULL)
     AND (isspam=0 OR isspam IS NULL)
     AND (ishighspam=0 OR ishighspam IS NULL)
    THEN 1 ELSE 0 END
   )/COUNT(*))*100,1
  ) AS otherinfectedpercent,
  SUM(
   CASE WHEN
    isspam>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    AND (ishighspam=0 OR ishighspam IS NULL)
   THEN 1 ELSE 0 END
  ) AS spam,
  ROUND((
   SUM(
    CASE WHEN
     isspam>0
     AND (virusinfected=0 OR virusinfected IS NULL)
     AND (nameinfected=0 OR nameinfected IS NULL)
     AND (otherinfected=0 OR otherinfected IS NULL)
     AND (ishighspam=0 OR ishighspam IS NULL)
    THEN 1 ELSE 0 END
   )/COUNT(*))*100,1
  ) AS spampercent,
  SUM(
   CASE WHEN
    ishighspam>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
   THEN 1 ELSE 0 END
  ) AS highspam,
  ROUND((
   SUM(
    CASE WHEN
     ishighspam>0
     AND (virusinfected=0 OR virusinfected IS NULL)
     AND (nameinfected=0 OR nameinfected IS NULL)
     AND (otherinfected=0 OR otherinfected IS NULL)
    THEN 1 ELSE 0 END
   )/COUNT(*))*100,1
  ) AS highspampercent,
  SUM(
   CASE WHEN
    ismcp>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    AND (isspam=0 OR isspam IS NULL)
    AND (ishighspam=0 OR ishighspam IS NULL)
    AND (ishighmcp=0 OR ishighmcp IS NULL)
   THEN 1 ELSE 0 END
  ) AS mcp,
  ROUND((
   SUM(
    CASE WHEN
     ismcp>0
     AND (virusinfected=0 OR virusinfected IS NULL)
     AND (nameinfected=0 OR nameinfected IS NULL)
     AND (otherinfected=0 OR otherinfected IS NULL)
     AND (isspam=0 OR isspam IS NULL)
     AND (ishighspam=0 OR ishighspam IS NULL)
     AND (ishighmcp=0 OR ishighmcp IS NULL)
    THEN 1 ELSE 0 END
   )/COUNT(*))*100,1
  ) AS mcppercent,
  SUM(
   CASE WHEN
    ishighmcp>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    AND (isspam=0 OR isspam IS NULL)
    AND (ishighspam=0 OR ishighspam IS NULL)
   THEN 1 ELSE 0 END
  ) AS highmcp,
  ROUND((
   SUM(
    CASE WHEN
     ishighmcp>0
     AND (virusinfected=0 OR virusinfected IS NULL)
     AND (nameinfected=0 OR nameinfected IS NULL)
     AND (otherinfected=0 OR otherinfected IS NULL)
     AND (isspam=0 OR isspam IS NULL)
     AND (ishighspam=0 OR ishighspam IS NULL)
    THEN 1 ELSE 0 END
   )/COUNT(*))*100,1
  ) AS highmcppercent,
  SUM(size) AS size
 FROM
  maillog
 WHERE
  date = CURRENT_DATE()
 AND
  ' . $_SESSION['global_filter'] . '
';

    $sth = dbquery($sql);
    while ($row = $sth->fetch_object()) {
        echo '<table border="0" cellpadding="1" cellspacing="1" class="mail todaystatistics" width="220">' . "\n";
        echo ' <tr><th align="center" colspan="3">' . __('todaystotals03') . '</th></tr>' . "\n";
        echo ' <tr><td>' . __('processed03') . '</td><td>' . number_format(
            $row->processed
        ) . '</td><td>' . formatSize(
            $row->size
        ) . '</td></tr>' . "\n";
        echo ' <tr><td>' . __('cleans03') . '</td><td>' . number_format(
            $row->clean
        ) . '</td><td>' . $row->cleanpercent . '%</td></tr>' . "\n";
        echo ' <tr><td>' . __('viruses03') . '</td><td>' . number_format(
            $row->viruses
        ) . '</td><td>' . $row->viruspercent . '%</tr>' . "\n";
        echo ' <tr><td>' . __('topvirus03') . '</td><td colspan="2">' . return_todays_top_virus() . '</td></tr>' . "\n";
        echo ' <tr><td>' . __('blockedfiles03') . '</td><td>' . number_format(
            $row->blockedfiles
        ) . '</td><td>' . $row->blockedfilespercent . '%</td></tr>' . "\n";
        echo ' <tr><td>' . __('others03') . '</td><td>' . number_format(
            $row->otherinfected
        ) . '</td><td>' . $row->otherinfectedpercent . '%</td></tr>' . "\n";
        echo ' <tr><td>' . __('spam03') . '</td><td>' . number_format(
            $row->spam
        ) . '</td><td>' . $row->spampercent . '%</td></tr>' . "\n";
        echo ' <tr><td>' . __('hscospam03') . '</td><td>' . number_format(
            $row->highspam
        ) . '</td><td>' . $row->highspampercent . '%</td></tr>' . "\n";
        if (get_conf_truefalse('mcpchecks')) {
            echo ' <tr><td>MCP:</td><td>' . number_format(
                $row->mcp
            ) . '</td><td>' . $row->mcppercent . '%</td></tr>' . "\n";
            echo ' <tr><td>' . __('hscomcp03') . '</td><td>' . number_format(
                $row->highmcp
            ) . '</td><td>' . $row->highmcppercent . '%</td></tr>' . "\n";
        }
        echo '</table>' . "\n";
    }
}

function printNavBar()
{
    // Navigation links - put them into an array to allow them to be switched
    // on or off as necessary and to allow for the table widths to be calculated.
    $nav = [];
    $nav['status.php'] = __('recentmessages03');
    if (LISTS) {
        $nav['lists.php'] = __('lists03');
    }
    if (!DISTRIBUTED_SETUP) {
        $nav['quarantine.php'] = __('quarantine03');
    }
    $nav['reports.php'] = __('reports03');
    $nav['other.php'] = __('toolslinks03');

    if (SHOW_SFVERSION === true && $_SESSION['user_type'] === 'A') {
        $nav['sf_version.php'] = __('softwareversions03');
    }

    if (SHOW_DOC === true) {
        $nav['docs.php'] = __('documentation03');
    }
    $nav['logout.php'] = __('logout03');
    //$table_width = round(100 / count($nav));

    //Navigation table
    echo '<tr class="noprint">' . "\n";
    echo '<td colspan="' . ($_SESSION['user_type'] === 'A' ? '5' : '4') . '">' . "\n";

    echo '<ul id="menu" class="yellow">' . "\n";

    // Display the different words
    foreach ($nav as $url => $desc) {
        $active_url = MAILWATCH_HOME . '/' . $url;
        if ($_SERVER['SCRIPT_FILENAME'] === $active_url) {
            echo "<li class=\"active\"><a href=\"$url\">$desc</a></li>\n";
        } else {
            echo "<li><a href=\"$url\">$desc</a></li>\n";
        }
    }

    if (defined('USER_SELECTABLE_LANG')) {
        $langCodes = explode(',', USER_SELECTABLE_LANG);
        $langCount = count($langCodes);
        if ($langCount > 1) {
            global $langCode;
            echo '<script>function changeLang() { document.cookie = "MW_LANG="+document.getElementById("langSelect").selectedOptions[0].value; location.reload();} </script>';
            echo '<li class="lang"><select id="langSelect" class="lang" onChange="changeLang()">' . "\n";
            for ($i=0; $i < $langCount; $i++) {
                echo '<option value="' . $langCodes[$i] . '"'
                . ($langCodes[$i] === $langCode ? ' selected' : '')
                . '>' . __($langCodes[$i]) . '</option>' ."\n";
            }
            echo '</select></li>'. "\n";
        }
    }

    echo '
 </ul>
 </td>
 </tr>';
}

function java_time()
{
    echo '
function updateClock() {
  var currentTime = new Date();

  var currentHours = currentTime.getHours();
  var currentMinutes = currentTime.getMinutes();
  var currentSeconds = currentTime.getSeconds();

  // Pad the minutes and seconds with leading zeros, if required
  currentMinutes = ( currentMinutes < 10 ? "0" : "" ) + currentMinutes;
  currentSeconds = ( currentSeconds < 10 ? "0" : "" ) + currentSeconds;

  // Choose either "AM" or "PM" as appropriate
  var timeOfDay = ( currentHours < 12 ) ? "AM" : "PM";
';

    if (TIME_FORMAT === '%h:%i:%s') {
        echo '
  // Convert the hours component to 12-hour format if needed
  currentHours = ( currentHours > 12 ) ? currentHours - 12 : currentHours;

  // Convert an hours component of "0" to "12"
  currentHours = ( currentHours === 0 ) ? 12 : currentHours;';
    }

    echo '

  // Compose the string for display
  var currentTimeString = currentHours + ":" + currentMinutes + ":" + currentSeconds + " " + timeOfDay;

  // Update the time display
  document.getElementById("clock").firstChild.nodeValue = currentTimeString;
}

// -->
';
}

/**
 * @param string $footer
 */
function html_end($footer = '')
{
    echo '</td>' . "\n";
    echo '</tr>' . "\n";
    echo '</table>' . "\n";
    echo $footer;
    if (DEBUG) {
        echo '<p class="center footer"><i>' . "\n";
        echo page_creation_timer();
        echo '</i></p>' . "\n";
    }
    echo '<p class="center footer noprint">' . "\n";
    echo __('footer03');
    echo mailwatch_version();
    echo ' - &copy; 2006-' . date('Y');
    echo '</p>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

/**
 * @return mysqli
 */
function dbconn()
{
    //$link = mysql_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, false, 128);
    if (!defined('DB_PORT')) {
        define('DB_PORT', 3306);
    }
    return database::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
}

/**
 * @return bool
 */
function dbclose()
{
    return database::close();
}

/**
 * @param string $sql
 * @param bool $printError
 * @return mysqli_result
 */
function dbquery($sql, $printError = true)
{
    $link = dbconn();
    if (DEBUG && headers_sent() && preg_match('/\bselect\b/i', $sql)) {
        dbquerydebug($link, $sql);
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $result = $link->query($sql);

    if (true === $printError && false === $result) {
        // stop on query error
        $message = '<strong>Invalid query</strong>: ' . database::$link->errno . ': ' . database::$link->error . "<br>\n";
        $message .= '<strong>Whole query</strong>: <pre>' . $sql . '</pre>';
        die($message);
    }

    return $result;
}

/**
 * @param mysqli $link
 * @param string $sql
 */
function dbquerydebug($link, $sql)
{
    echo "<!--\n\n";
    $dbg_sql = 'EXPLAIN ' . $sql;
    echo "SQL:\n\n$sql\n\n";
    /** @var mysqli_result $result */
    $result = $link->query($dbg_sql);
    if ($result) {
        while ($row = $result->fetch_row()) {
            for ($f = 0; $f < $link->field_count; $f++) {
                echo $result->fetch_field_direct($f)->name . ': ' . $row[$f] . "\n";
            }
        }

        echo "\n-->\n\n";
        $result->free_result();
    } else {
        die(__('diedbquery03') . '(' . $link->connect_errno . ' ' . $link->connect_error . ')');
    }
}

/**
 * @param $string
 * @return string
 */
function sanitizeInput($string)
{
    $config = HTMLPurifier_Config::createDefault();
    $purifier = new HTMLPurifier($config);

    return $purifier->purify($string);
}

/**
 * @param $value
 * @return string
 */
function quote_smart($value)
{
    return "'" . safe_value($value) . "'";
}

/**
 * @param $value
 * @return string
 */
function safe_value($value)
{
    $link = dbconn();
    if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
        $value = stripslashes($value);
    }

    return $link->real_escape_string($value);
}

/**
 * @param string $string
 * @param boolean $useSystemLang
 * @return string
 */
function __($string, $useSystemLang = false)
{
    if ($useSystemLang) {
        global $systemLang;
        $language = $systemLang;
    } else {
        global $lang;
        $language = $lang;
    }

    $debug_message = '';
    $pre_string = '';
    $post_string = '';
    if (defined('DEBUG') && DEBUG === true) {
        $debug_message = ' (' . $string . ')';
        $pre_string = '<span class="error">';
        $post_string = '</span>';
    }

    if (isset($language[$string])) {
        return $language[$string] . $debug_message;
    }

    $en_lang = require __DIR__ . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR . 'en.php';
    if (isset($en_lang[$string])) {
        return $pre_string . $en_lang[$string] . $debug_message . $post_string;
    }

    return $pre_string . $language['i18_missing'] . $debug_message . $post_string;
}

/**
 * Returns true if $string is valid UTF-8 and false otherwise.
 *
 * @param  string $string
 * @return integer
 */
function is_utf8($string)
{
    // From https://www.w3.org/International/questions/qa-forms-utf-8.en.html
    return preg_match('%^(?:
          [\x09\x0A\x0D\x20-\x7E]            # ASCII
        | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
        |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
        |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
        |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
    )*$%xs', $string);
}

/**
 * @param string $string
 * @return string
 */
function getUTF8String($string)
{
    if (function_exists('mb_check_encoding')) {
        if (!mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8');
        }
    } else {
        if (!is_utf8($string)) {
            $string = utf8_encode($string);
        }
    }

    return $string;
}

/**
 * @param string $header
 * @return string
 */
function getFROMheader($header)
{
    $sender = '';
    if (preg_match('/From:([ ]|\n)(.*(?=((\d{3}[A-Z]?[ ]+(\w|[-])+:.*)|(\s*\z))))/sUi', $header, $match) === 1) {
        if (isset($match[2])) {
            $sender = $match[2];
        }
        if (preg_match('/\S+@\S+/', $sender, $match_email) === 1 && isset($match_email[0])) {
            $sender = str_replace(['<', '>', '"'], '', $match_email[0]);
        }
    }
    return $sender;
}

/**
 * @param string $header
 * @return string
 */
function getSUBJECTheader($header)
{
    $subject = '';
    if (preg_match('/^\d{3}  Subject:([ ]|\n)(.*(?=((\d{3}[A-Z]?[ ]+(\w|[-])+:.*)|(\s*\z))))/iUsm', $header, $match) === 1) {
        $subLines = preg_split('/[\r\n]+/', $match[2]);
        for ($i = 0, $countSubLines = count($subLines); $i < $countSubLines; $i++) {
            $convLine = '';
            if (function_exists('imap_mime_header_decode')) {
                $linePartArr = imap_mime_header_decode($subLines[$i]);
                for ($j = 0, $countLinePartArr = count($linePartArr); $j < $countLinePartArr; $j++) {
                    if (strtolower($linePartArr[$j]->charset) === 'default') {
                        if ($linePartArr[$j]->text !== ' ') {
                            $convLine .= $linePartArr[$j]->text;
                        }
                    } else {
                        $textdecoded = @iconv(
                            strtoupper($linePartArr[$j]->charset),
                            'UTF-8//TRANSLIT//IGNORE',
                            $linePartArr[$j]->text
                        );
                        if (!$textdecoded) {
                            $convLine .= $linePartArr[$j]->text;
                        } else {
                            $convLine .= $textdecoded;
                        }
                    }
                }
            } else {
                $convLine .= str_replace('_', ' ', mb_decode_mimeheader($subLines[$i]));
            }
            $subject .= $convLine;
        }
    }

    return $subject;
}

/**
 * @param string $spamreport
 * @return string|false
 */
function sa_autolearn($spamreport)
{
    if (preg_match('/autolearn=spam/', $spamreport) === 1) {
        return __('saspam03');
    }

    if (preg_match('/autolearn=not spam/', $spamreport) === 1) {
        return __('sanotspam03');
    }

    return false;
}

/**
 * @param $spamreport
 * @return string
 */
function format_spam_report($spamreport)
{
    // Run regex against the MailScanner spamreport picking out the (score=xx, required x, RULES...)
    if (preg_match('/\s\((.+?)\)/i', $spamreport, $sa_rules)) {
        // Get rid of the first match from the array
        array_shift($sa_rules);
        // Split the array
        $sa_rules = explode(', ', $sa_rules[0]);
        // Check to make sure a check was actually run
        if ($sa_rules[0] === 'Message larger than max testing size' || $sa_rules[0] === 'timed out') {
            return $sa_rules[0];
        }

        // Get rid of the 'score=', 'required' and 'autolearn=' lines
        $notRulesLines = [
            //english
            'cached',
            'score=',
            'required',
            'autolearn=',
            //italian
            'punteggio=',
            'necessario',
            //german
            'benoetigt',
            'Wertung=',
            'gecached',
            //french
            'requis'
        ];
        array_walk($notRulesLines, function ($value) {
            return preg_quote($value, '/');
        });
        $notRulesLinesRegex = '(' . implode('|', $notRulesLines) . ')';

        $sa_rules = array_filter($sa_rules, function ($val) use ($notRulesLinesRegex) {
            return preg_match("/$notRulesLinesRegex/i", $val) === 0;
        });

        $output_array = [];
        foreach ($sa_rules as $sa_rule) {
            $output_array[] = get_sa_rule_desc($sa_rule);
        }

        // Return the result as an html formatted string
        if (count($output_array) > 0) {
            return '<table class="sa_rules_report" cellspacing="2" width="100%"><tr><th>' . __('score03') . '</th><th>' . __('matrule03') . '</th><th>' . __('description03') . '</th></tr>' . implode(
                "\n",
                $output_array
            ) . '</table>' . "\n";
        }

        return $spamreport;
    }

    // Regular expression did not match, return unmodified report instead
    return $spamreport;
}

/**
 * @param string $rule
 * @return string
 */
function get_sa_rule_desc($rule)
{
    // Check if SA scoring is enabled
    $rule_score = '';
    if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
        $rule = $regs[1];
        $rule_score = $regs[2];
    }
    $result = dbquery("SELECT rule, rule_desc FROM sa_rules WHERE rule='$rule'");
    $row = $result->fetch_object();
    if ($row && $row->rule && $row->rule_desc) {
        return ('<tr><td>' . $rule_score . '</td><td>' . $row->rule . '</td><td>' . $row->rule_desc . '</td></tr>' . "\n");
    }

    return "<tr><td>$rule_score</td><td>$rule</td><td>&nbsp;</td></tr>";
}

/**
 * @param string $rule
 * @return string|false
 */
function return_sa_rule_desc($rule)
{
    $result = dbquery("SELECT rule, rule_desc FROM sa_rules WHERE rule='$rule'");
    $row = $result->fetch_object();
    if ($row) {
        return htmlentities($row->rule_desc);
    }

    return false;
}

/**
 * @param string $mcpreport
 * @return mixed|string
 */
function format_mcp_report($mcpreport)
{
    // Clean-up input
    $mcpreport = preg_replace('/\n/', '', $mcpreport);
    $mcpreport = preg_replace('/\t/', ' ', $mcpreport);
    // Run regex against the MailScanner mcpreport picking out the (score=xx, required x, RULES...)
    if (preg_match('/ \((.+?)\)/i', $mcpreport, $sa_rules)) {
        // Get rid of the first match from the array
        array_shift($sa_rules);
        // Split the array
        $sa_rules = explode(', ', $sa_rules[0]);
        // Check to make sure a check was actually run
        if ($sa_rules[0] === 'Message larger than max testing size' || $sa_rules[0] === 'timed out') {
            return $sa_rules[0];
        }
        // Get rid of the 'score=', 'required' and 'autolearn=' lines
        foreach (['score=', 'required', 'autolearn='] as $val) {
            if (preg_match("/$val/", $sa_rules[0])) {
                array_shift($sa_rules);
            }
        }
        $output_array = [];
        foreach ($sa_rules as $val) {
            $output_array[] = get_mcp_rule_desc($val);
        }
        // Return the result as an html formatted string
        if (count($output_array) > 0) {
            return '<table class="sa_rules_report" cellspacing="2" width="100%">"."<tr><th>' . __('score03') . '</th><th>' . __('matrule03') . '</th><th>' . __('description03') . '</th></tr>' . implode(
                "\n",
                $output_array
            ) . '</table>' . "\n";
        }

        return $mcpreport;
    }

    // Regular expression did not match, return unmodified report instead
    return $mcpreport;
}

/**
 * @param $rule
 * @return string
 */
function get_mcp_rule_desc($rule)
{
    // Check if SA scoring is enabled
    $rule_score = '';
    if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
        list($rule, $rule_score) = $regs;
    }
    $result = dbquery("SELECT rule, rule_desc FROM mcp_rules WHERE rule='$rule'");
    $row = $result->fetch_object();
    if ($row && $row->rule && $row->rule_desc) {
        return ('<tr><td>' . $rule_score . '</td><td>' . $row->rule . '</td><td>' . $row->rule_desc . '</td></tr>' . "\n");
    }

    return '<tr><td>' . $rule_score . '<td>' . $rule . '</td><td>&nbsp;</td></tr>' . "\n";
}

/**
 * @param $rule
 * @return bool
 */
function return_mcp_rule_desc($rule)
{
    $result = dbquery("SELECT rule, rule_desc FROM mcp_rules WHERE rule='$rule'");
    $row = $result->fetch_object();
    if ($row) {
        return $row->rule_desc;
    }

    return false;
}

/**
 * @return string
 */
function return_todays_top_virus()
{
    if (getVirusRegex() === null) {
        return __('unknownvirusscanner03');
    }
    $sql = '
SELECT
 report
FROM
 maillog
WHERE
 virusinfected>0
AND
 date = CURRENT_DATE()
';
    $result = dbquery($sql);
    $virus_array = [];
    while ($row = $result->fetch_object()) {
        $virus = getVirus($row->report);
        if ($virus !== null) {
            $virus = return_virus_link($virus, true);
            if (!isset($virus_array[$virus])) {
                $virus_array[$virus] = 1;
            } else {
                $virus_array[$virus]++;
            }
        }
    }
    if (count($virus_array) === 0) {
        return __('none03');
    }
    arsort($virus_array);
    reset($virus_array);

    // Get the topmost entry from the array
    $top = null;
    $count = 0;
    foreach ($virus_array as $key => $val) {
        if ($top === null) {
            $top = $val;
        } elseif ($val !== $top) {
            break;
        }
        $count++;
    }
    $topvirus_arraykeys = array_keys($virus_array);
    $topvirus = $topvirus_arraykeys[0];
    if ($count > 1) {
        // and ... others
        $topvirus .= sprintf(' ' . __('moretopviruses03'), $count-1);
    }

    return $topvirus;
}

/**
 * @return array|mixed
 */
function get_disks()
{
    $disks = [];
    if (PHP_OS === 'Windows NT') {
        // windows
        $disks = shell_exec('fsutil fsinfo drives');
        $disks = str_word_count($disks, 1);
        //TODO: won't work on non english installation, we need to find an universal command
        if ($disks[0] !== 'Drives') {
            return [];
        }
        unset($disks[0]);
        foreach ($disks as $disk) {
            $disks[]['mountpoint'] = $disk . ':\\';
        }
    } else {
        // unix
        /*
         * Using /proc/mounts as it seem to be standard on unix
         *
         * http://unix.stackexchange.com/a/24230/33366
         * http://unix.stackexchange.com/a/12086/33366
         */
        $temp_drive = [];
        // TODO: list nfs mount (and other relevant fs type) in $disks[]
        // TODO: remove bind mount
        // TODO: list MailScanner tmpfs
        if (is_file('/proc/mounts')) {
            $mounted_fs = file('/proc/mounts');
            foreach ($mounted_fs as $fs_row) {
                $drive = preg_split("/[\s]+/", $fs_row);
                if ((strpos($drive[0], '/dev/') === 0) && (stripos($drive[1], '/chroot/') === false)) {
                    $temp_drive['device'] = $drive[0];
                    $temp_drive['mountpoint'] = $drive[1];
                    $disks[] = $temp_drive;
                    unset($temp_drive);
                }
            }
        } else {
            // fallback to mount command
            $data = shell_exec('mount');
            $data = explode("\n", $data);
            foreach ($data as $disk) {
                $drive = preg_split("/[\s]+/", $disk);
                if ((strpos($drive[0], '/dev/') === 0) && (stripos($drive[2], '/chroot/') === false)) {
                    $temp_drive['device'] = $drive[0];
                    $temp_drive['mountpoint'] = $drive[2];
                    $disks[] = $temp_drive;
                    unset($temp_drive);
                }
            }
        }
    }

    return $disks;
}

/**
 * @param double $size
 * @param int $precision
 * @return string
 */
function formatSize($size, $precision = 2)
{
    if ($size === null) {
        return 'n/a';
    }
    if ($size === 0 || $size === '0') {
        return '0';
    }
    $base = log($size) / log(1024);
    $suffixes = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];

    return round(pow(1024, $base - floor($base)), $precision) . $suffixes[(int)floor($base)];
}

/**
 * @param array $data_in
 * @param array $info_out
 */
function format_report_volume(&$data_in, &$info_out)
{
    // Measures
    $kb = 1024;
    $mb = 1024 * $kb;
    $gb = 1024 * $mb;
    $tb = 1024 * $gb;

    // Copy the data to a temporary variable
    $temp = $data_in;

    // Work out the average size of values in the array
    $count = count($temp);
    $sum = array_sum($temp);
    $average = $sum / $count;

    // Work out the largest value in the array
    arsort($temp);
    array_pop($temp);

    // Calculate the correct display size for the average value
    if ($average < $kb) {
        $info_out['formula'] = 1;
        $info_out['shortdesc'] = 'b';
        $info_out['longdesc'] = 'Bytes';
    } elseif ($average < $mb) {
        $info_out['formula'] = $kb;
        $info_out['shortdesc'] = 'Kb';
        $info_out['longdesc'] = 'Kilobytes';
    } elseif ($average < $gb) {
        $info_out['formula'] = $mb;
        $info_out['shortdesc'] = 'Mb';
        $info_out['longdesc'] = 'Megabytes';
    } elseif ($average < $tb) {
        $info_out['formula'] = $gb;
        $info_out['shortdesc'] = 'Gb';
        $info_out['longdesc'] = 'Gigabytes';
    } else {
        $info_out['formula'] = $tb;
        $info_out['shortdesc'] = 'Tb';
        $info_out['longdesc'] = 'Terabytes';
    }

    // Modify the original data accordingly
    $num_data_in = count($data_in);
    for ($i = 0; $i < $num_data_in; $i++) {
        $data_in[$i] /= $info_out['formula'];
    }
}

/**
 * @param string $input
 * @param int $maxlen
 * @return string
 */
function trim_output($input, $maxlen)
{
    if ($maxlen > 0 && strlen($input) >= $maxlen) {
        return substr($input, 0, $maxlen) . '...';
    }

    return $input;
}

/**
 * @param string $file
 * @return bool
 */
function get_default_ruleset_value($file)
{
    $fh = fopen($file, 'rb') or die(__('dieruleset03') . " $file");
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, filesize($file)));
        if (preg_match('/^([^#]\S+:)\s+(\S+)\s+([^#]\S+)/', $line, $regs)) {
            if ($regs[2] === 'default') {
                return $regs[3];
            }
        }
    }
    fclose($fh);

    return false;
}

/**
 * @param string $name
 * @param bool $force
 * @return bool
 */
function get_conf_var($name, $force = false)
{
    if (DISTRIBUTED_SETUP && !$force) {
        return false;
    }
    $conf_dir = get_conf_include_folder($force);
    $MailScanner_conf_file = MS_CONFIG_DIR . 'MailScanner.conf';

    $array_output1 = parse_conf_file($MailScanner_conf_file);
    $array_output2 = parse_conf_dir($conf_dir);

    $array_output = $array_output1;
    if (is_array($array_output2)) {
        $array_output = array_merge($array_output1, $array_output2);
    }

    foreach ($array_output as $parameter_name => $parameter_value) {
        $parameter_name = preg_replace('/ */', '', $parameter_name);

        if (strtolower($parameter_name) === strtolower($name)) {
            if (is_file($parameter_value)) {
                return read_ruleset_default($parameter_value);
            }

            return $parameter_value;
        }
    }

    die(__('dienoconfigval103') . " $name " . __('dienoconfigval203') . " $MailScanner_conf_file\n");
}

/**
 * @param string $conf_dir
 * @return array
 */
function parse_conf_dir($conf_dir)
{
    $array_output1 = [];
    if ($dh = opendir($conf_dir)) {
        while (($file = readdir($dh)) !== false) {
            // ignore subfolders and hidden files so that it doesn't throw an error when parsing files
            if (strlen($file) > 0 &&  substr($file, 0, 1) !== '.' && is_file($conf_dir . $file)) {
                $file_name = $conf_dir . $file;
                if (!is_array($array_output1)) {
                    $array_output1 = parse_conf_file($file_name);
                } else {
                    $array_output2 = parse_conf_file($file_name);
                    $array_output1 = array_merge($array_output1, $array_output2);
                }
            }
        }
    }
    closedir($dh);

    return $array_output1;
}

/**
 * @param string $name
 * @param bool $force
 * @return bool
 */
function get_conf_truefalse($name, $force = false)
{
    if (DISTRIBUTED_SETUP && !$force) {
        return true;
    }

    $conf_dir = get_conf_include_folder($force);
    $MailScanner_conf_file = MS_CONFIG_DIR . 'MailScanner.conf';

    $array_output1 = parse_conf_file($MailScanner_conf_file);
    $array_output2 = parse_conf_dir($conf_dir);

    $array_output = $array_output1;
    if (is_array($array_output2)) {
        $array_output = array_merge($array_output1, $array_output2);
    }

    foreach ($array_output as $parameter_name => $parameter_value) {
        $parameter_name = preg_replace('/ */', '', $parameter_name);

        if (strtolower($parameter_name) === strtolower($name)) {
            // Is it a ruleset?
            if (is_readable($parameter_value)) {
                $parameter_value = get_default_ruleset_value($parameter_value);
            }
            $parameter_value = strtolower($parameter_value);
            switch ($parameter_value) {
                case 'yes':
                case '1':
                    return true;
                case 'no':
                case '0':
                    return false;
                default:
                    // if $parameter_value is a ruleset or a function call return true
                    $parameter_value = trim($parameter_value);

                    return strlen($parameter_value) > 0;
            }
        }
    }

    return false;
}

/**
 * @param bool $force
 * @return bool|mixed
 */
function get_conf_include_folder($force = false)
{
    if (DISTRIBUTED_SETUP && !$force) {
        return false;
    }

    static $conf_include_folder;
    if (null !== $conf_include_folder) {
        return $conf_include_folder;
    }

    $msconfig = MS_CONFIG_DIR . 'MailScanner.conf';
    if (!is_file($msconfig) || !is_readable($msconfig)) {
        return false;
    }

    if (preg_match('/^include\s+([^=]*)\*\S*$/im', file_get_contents($msconfig), $match) === 1) {
        $conf_include_folder = $match[1];

        return $conf_include_folder;
    }

    die(__('dienoconfigval103') . ' include ' . __('dienoconfigval203') . ' ' . $msconfig . "\n");
}

/**
 * Parse conf files
 *
 * @param string $name
 * @return array
 */
function parse_conf_file($name)
{
    static $conf_file_cache;
    if (null !== $conf_file_cache && isset($conf_file_cache[$name])) {
        return $conf_file_cache[$name];
    }

    // check if file can be read
    if (!is_file($name) || !is_readable($name)) {
        $exitString = __('dienomsconf03');
        if (defined('DEBUG') && DEBUG === true) {
            $exitString .= ' (' . $name . ')';
        }
        die($exitString);
    }

    $array_output = [];
    $var = [];
    // open each file and read it
    $fileContent = array_filter(
        file($name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
        function ($value) {
            return !($value[0] === '#');
        }
    );

    foreach ($fileContent as $line) {
        //echo "line: ".$line."\n"; // only use for troubleshooting lines

        // find all lines that match
        if (preg_match("/^(?P<name>[^#].+[^\s*$])\s*=\s*(?P<value>[^#]*)/", $line, $regs)) {

            // Strip trailing comments
            $regs['value'] = preg_replace('/#.*$/', '', $regs['value']);

            // store %var% variables
            if (preg_match('/%.+%/', $regs['name'])) {
                $var[$regs['name']] = $regs['value'];
            }

            // expand %var% variables
            if (preg_match('/(%[^%]+%)/', $regs['value'], $matches)) {
                array_shift($matches);
                foreach ($matches as $varname) {
                    $regs['value'] = str_replace($varname, $var[$varname], $regs['value']);
                }
            }

            // Remove any html entities from the code
            $key = htmlentities($regs['name']);
            //$string = htmlentities($regs['value']);
            $string = $regs['value'];

            // Stuff all of the data to an array
            $array_output[$key] = $string;
        }
    }
    unset($fileContent);

    $conf_file_cache[$name] = $array_output;
    return $conf_file_cache[$name];
}

/**
 * @return mixed
 */
function get_primary_scanner()
{
    // Might be more than one scanner defined - pick the first as the primary
    $scanners = explode(' ', get_conf_var('VirusScanners'));

    return $scanners[0];
}

/**
 * @param $date
 * @param string $format
 * @return mixed|string
 */
function translateQuarantineDate($date, $format = 'dmy')
{
    $y = substr($date, 0, 4);
    $m = substr($date, 4, 2);
    $d = substr($date, 6, 2);

    $format = strtolower($format);

    switch ($format) {
        case 'dmy':
            return "$d/$m/$y";
        case 'sql':
            return "$y-$m-$d";
        default:
            $format = preg_replace('/%y/', $y, $format);
            $format = preg_replace('/%m/', $m, $format);
            $format = preg_replace('/%d/', $d, $format);

            return $format;
    }
}

/**
 * @param $preserve
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
 * @return int
 */
function generatePager($sql)
{
    require_once __DIR__ . '/lib/pear/Pager.php';
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
    $results = dbquery($sqlcount);
    $rows = database::mysqli_result($results, 0);

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
<th colspan="5">' . __('disppage03') . ' ' . $pager->getCurrentPageID() . ' ' . __('of03') . ' ' . $pager->numPages() . ' - ' . __('records03') . ' ' . $from . ' ' . __('to0203') . ' ' . $to . ' ' . __('of03') . ' ' . $pager->numItems() . '</th>
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
  <td colspan="' . ($_SESSION['user_type'] === 'A' ? '5' : '4') . '">';

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
    require_once __DIR__ . '/lib/pear/Mail/mimeDecode.php';

    // Ordering
    $orderby = null;
    $orderdir = '';
    if (isset($_GET['orderby'])) {
        $orderby = sanitizeInput($_GET['orderby']);
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
        if (($p = stristr($sql, 'ORDER BY')) !== false) {
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
        $sth = dbquery($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if ($operations !== false) {
            $fields++;
        }
    } else {
        $sth = dbquery($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if ($operations !== false) {
            $fields++;
        }
    }

    if ($rows > 0) {
        if ($operations !== false) {
            // Start form for operations
            echo '<form name="operations" action="./do_message_ops.php" method="POST">' . "\n";
            echo '<input type="hidden" name="token" value="' . $_SESSION['token'] . '">' . "\n";
            echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . generateFormToken('/do_message_ops.php form token') . '">' . "\n";
        }
        printColorCodes();
        echo '<table cellspacing="1" width="100%" class="mail rowhover">' . "\n";
        // Work out which columns to display
        $display = [];
        $orderable = [];
        $fieldname = [];
        $align = [];
        for ($f = 0; $f < $fields; $f++) {
            if ($f === 0 && $operations !== false) {
                // Set up display for operations form elements
                $display[$f] = true;
                $orderable[$f] = false;
                // Set it up not to wrap - tricky way to leach onto the align field
                $align[$f] = 'center" style="white-space:nowrap';
                $fieldname[$f] = __('ops03') . '<br><a href="javascript:SetRadios(\'S\')">' . __('radiospam203') . '</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'H\')">' . __('radioham03') . '</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'F\')">' . __('radioforget03') . '</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'R\')">' . __('radiorelease03') . '</a>';
                continue;
            }
            $display[$f] = true;
            $orderable[$f] = true;
            $align[$f] = false;
            // Set up the mysql column to account for operations
            $colnum = $f;
            if ($operations !== false) {
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
                case 'datetime':
                case 'timestamp':
                    $fieldname[$f] = __('datetime03');
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
                    $fieldname[$f] = __('size03');
                    $align[$f] = 'right';
                    break;
                case 'from_address':
                    $fieldname[$f] = __('from03');
                    break;
                case 'to_address':
                    $fieldname[$f] = __('to03');
                    break;
                case 'subject':
                    $fieldname[$f] = __('subject03');
                    break;
                case 'clientip':
                    if (defined('DISPLAY_IP') && DISPLAY_IP) {
                        $fieldname[$f] = __('clientip03');
                    }
                    $display[$f] = true;
                    break;
                case 'isspam':
                case 'ishighspam':
                case 'issaspam':
                case 'isrblspam':
                case 'spamwhitelisted':
                case 'spamblacklisted':
                case 'spamreport':
                case 'virusinfected':
                case 'nameinfected':
                case 'otherinfected':
                case 'report':
                case 'ismcp':
                case 'ishighmcp':
                case 'issamcp':
                case 'mcpwhitelisted':
                case 'mcpblacklisted':
                case 'mcpreport':
                case 'headers':
                case 'released':
                case 'salearn':
                case 'archive':
                    $display[$f] = false;
                    break;
                case 'hostname':
                    $fieldname[$f] = __('host03');
                    $display[$f] = true;
                    break;
                case 'date':
                    $fieldname[$f] = __('date03');
                    break;
                case 'time':
                    $fieldname[$f] = __('time03');
                    break;
                case 'sascore':
                    if (true === get_conf_truefalse('UseSpamAssassin')) {
                        $fieldname[$f] = __('sascore03');
                        $align[$f] = 'right';
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'mcpsascore':
                    if (get_conf_truefalse('MCPChecks')) {
                        $fieldname[$f] = __('mcpscore03');
                        $align[$f] = 'right';
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'status':
                    $fieldname[$f] = __('status03');
                    $orderable[$f] = false;
                    break;
                case 'message':
                    $fieldname[$f] = __('message03');
                    break;
                case 'attempts':
                    $fieldname[$f] = __('tries03');
                    $align[$f] = 'right';
                    break;
                case 'lastattempt':
                    $fieldname[$f] = __('last03');
                    $align[$f] = 'right';
                    break;
            }
        }
        // Table heading
        if (isset($table_heading) && $table_heading !== '') {
            // Work out how many columns are going to be displayed
            $column_headings = 0;
            for ($f = 0; $f < $fields; $f++) {
                if ($display[$f]) {
                    $column_headings++;
                }
            }
            echo ' <tr class="nohover"">' . "\n";
            echo '  <th colspan="' . $column_headings . '">' . $table_heading . '</th>' . "\n";
            echo ' </tr>' . "\n";
        }
        // Column headings
        echo '<tr class="nohover">' . "\n";
        for ($f = 0; $f < $fields; $f++) {
            if ($display[$f]) {
                if ($order && $orderable[$f]) {
                    // Set up the mysql column to account for operations
                    if ($operations !== false) {
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
        for ($r = 0; $r < $rows; $r++) {
            $row = $sth->fetch_row();
            $tooltips = [];
            if ($operations !== false) {
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
            for ($f = 0; $f < $fields; $f++) {
                if ($operations !== false) {
                    if ($f === 0) {
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
                            $tooltips[$f] = $row[$f];
                            $row[$f] = trim_output($row[$f], FROMTO_MAXLEN);
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
                            $tooltips[$f] = $row[$f];
                            // Trim each address to specified size
                            $to_temp = explode(',', $row[$f]);
                            $num_to_temp = count($to_temp);
                            for ($t = 0; $t < $num_to_temp; $t++) {
                                $to_temp[$t] = trim_output($to_temp[$t], FROMTO_MAXLEN);
                            }
                            // Return the data
                            $row[$f] = implode(',', $to_temp);
                        }
                        // Put each address on a new line
                        $row[$f] = str_replace(',', '<br>', $row[$f]);
                        break;
                    case 'subject':
                        $row[$f] = htmlspecialchars(getUTF8String(decode_header($row[$f])));
                        if (SUBJECT_MAXLEN > 0) {
                            $tooltips[$f] = $row[$f];
                            $row[$f] = trim_output($row[$f], SUBJECT_MAXLEN);
                        }
                        break;
                    case 'isspam':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $spam = true;
                            $status_array[] = __('spam103');
                        }
                        break;
                    case 'ishighspam':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $highspam = true;
                        }
                        break;
                    case 'ismcp':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $mcp = true;
                            $status_array[] = __('mcp03');
                        }
                        break;
                    case 'ishighmcp':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $highmcp = true;
                        }
                        break;
                    case 'virusinfected':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $infected = true;
                            $status_array[] = __('virus03');
                        }
                        break;
                    case 'report':
                        // IMPORTANT NOTE: for this to work correctly the 'report' field MUST
                        // appear after the 'virusinfected' field within the SQL statement.
                        $virus = getVirus($row[$f]);
                        if (defined('DISPLAY_VIRUS_REPORT') && DISPLAY_VIRUS_REPORT === true && $virus !== null) {
                            foreach ($status_array as $k => $v) {
                                if ($v = str_replace('Virus', 'Virus (' . return_virus_link($virus) . ')', $v)) {
                                    $status_array[$k] = $v;
                                }
                            }
                        }
                        break;
                    case 'nameinfected':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $infected = true;
                            $status_array[] = __('badcontent03');
                        }
                        break;
                    case 'otherinfected':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $infected = true;
                            $status_array[] = __('otherinfected03');
                        }
                        break;
                    case 'size':
                        $row[$f] = formatSize($row[$f]);
                        break;
                    case 'spamwhitelisted':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $whitelisted = true;
                            $status_array[] = __('whitelisted03');
                        }
                        break;
                    case 'spamblacklisted':
                        if ($row[$f] === 'Y' || $row[$f] > 0) {
                            $blacklisted = true;
                            $status_array[] = __('blacklisted03');
                        }
                        break;
                    case 'clienthost':
                        $hostname = gethostbyaddr($row[$f]);
                        if ($hostname === $row[$f]) {
                            $row[$f] = __('hostfailed03');
                        } else {
                            $row[$f] = $hostname;
                        }
                        break;
                    case 'released':
                        if ($row[$f] > 0) {
                            $released = true;
                            $status_array[] = __('released03');
                        }
                        break;
                    case 'salearn':
                        switch ($row[$f]) {
                            case 1:
                                $salearnham = true;
                                $status_array[] = __('learnham03');
                                break;
                            case 2:
                                $salearnspam = true;
                                $status_array[] = __('learnspam03');
                                break;
                        }
                        break;
                    case 'status':
                        // NOTE: this should always be the last row for it to be displayed correctly
                        // Work out status
                        if (count($status_array) === 0) {
                            $status = __('clean03');
                        } else {
                            $status = '';
                            foreach ($status_array as $item) {
                                if ($item === __('released03')) {
                                    $class = 'released';
                                } elseif ($item === __('learnham03')) {
                                    $class = 'salearn-1';
                                } elseif ($item === __('learnspam03')) {
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
            if ($operations !== false) {
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
                    if (isset($fieldname['mcpsascore']) && $fieldname['mcpsascore'] !== '') {
                        echo '<tr class="mcp">' . "\n";
                    } else {
                        echo '<tr >' . "\n";
                    }
                    break;
            }
            // Display the rows
            for ($f = 0; $f < $fields; $f++) {
                if ($display[$f]) {
                    $alignClassAddon = '';

                    if (isset($align[$f]) && $align[$f] !== false) {
                        $alignClassAddon = ' align="' . $align[$f] . '"';
                        if ($f === 0) {
                            $alignClassAddon .= ' class="link-transparent"';
                        }
                    }
                    $tooltipAddon = '';
                    if (isset($tooltips[$f]) && $tooltips[$f] !== false) {
                        $tooltipAddon = ' title="' . $tooltips[$f] . '"';
                    }

                    echo ' <td' . $tooltipAddon . $alignClassAddon . '>' . $row[$f] . '</td>' . "\n";
                }
            }
            echo ' </tr>' . "\n";
        }
        echo '</table>' . "\n";
        // Javascript function to clear radio buttons
        if ($operations !== false) {
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
   <p>&nbsp; <a href=\"javascript:SetRadios('S')\">" . __('radiospam203') . "</a>
   &nbsp; <a href=\"javascript:SetRadios('H')\">" . __('radioham03') . "</a>
   &nbsp; <a href=\"javascript:SetRadios('F')\">" . __('radioforget03') . "</a>
   &nbsp; <a href=\"javascript:SetRadios('R')\">" . __('radiorelease03') . '</a>
   &nbsp; ' . __('or03') . " <a href=\"javascript:SetRadios('C')\">" . __('clear03') . "</p>
   <p><input type='SUBMIT' name='SUBMIT' value='" . __('learn03') . "'></p>
   </form>
   <p><b>" . __('spam203') . ' &nbsp; <b>' . __('ham03') . ' &nbsp; <b>' . __('forget03') . ' &nbsp; <b>' . __('release03') . '' . "\n";
        }
        echo '<br>' . "\n";
        if ($pager) {
            generatePager($sql);
        }
    }
}

/**
 * Function to display data as a table
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
    $sth = dbquery($sql);

    // Count the number of rows in a table
    $rows = $sth->num_rows;

    // Count the nubmer of fields
    $fields = $sth->field_count;
    */

    // Turn on paging of for the database
    if ($pager) {
        require_once __DIR__ . '/lib/pear/Pager.php';
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

        $results = dbquery($sqlcount);
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
   <th colspan="5">' . __('disppage03') . ' ' . $pager->getCurrentPageID() . ' ' . __('of03') . ' ' . $pager->numPages() . ' - ' . __('records03') . ' ' . $from . ' ' . __('to0203') . ' ' . $to . ' ' . __('of03') . ' ' . $pager->numItems() . '</th>
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
  <td colspan="' . ($_SESSION['user_type'] === 'A' ? '5' : '4') . '">';

        // Re-run the original query and limit the rows
        $sql .= ' LIMIT ' . ($from - 1) . ',' . MAX_RESULTS;
        $sth = dbquery($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if ($operations !== false) {
            $fields++;
        }
    } else {
        $sth = dbquery($sql);
        $rows = $sth->num_rows;
        $fields = $sth->field_count;
        // Account for extra operations column
        if ($operations !== false) {
            $fields++;
        }
    }

    if ($rows > 0) {
        echo '<table cellspacing="1" width="100%" class="mail">' . "\n";
        if ($title !== null) {
            echo '<tr><th colspan=' . $fields . '>' . $title . '</TH></tr>' . "\n";
        }
        // Column headings
        echo ' <tr>' . "\n";
        if ($operations !== false) {
            echo '<td></td>';
        }

        foreach ($sth->fetch_fields() as $field) {
            echo '  <th>' . $field->name . '</th>' . "\n";
        }
        echo ' </tr>' . "\n";
        // Rows
        while ($row = $sth->fetch_row()) {
            echo ' <tr class="table-background">' . "\n";
            for ($f = 0; $f < $fields; $f++) {
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
        echo __('norowfound03') . "\n";
    }
    echo '<br>' . "\n";
    if ($pager) {
        require_once __DIR__ . '/lib/pear/Pager.php';
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
        $rows = database::mysqli_result(dbquery($sqlcount), 0);

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
   <th colspan="5">' . __('disppage03') . ' ' . $pager->getCurrentPageID() . ' ' . __('of03') . ' ' . $pager->numPages() . ' - ' . __('records03') . ' ' . $from . ' ' . __('to0203') . ' ' . $to . ' ' . __('of03') . ' ' . $pager->numItems() . '</th>
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
  <td colspan="' . ($_SESSION['user_type'] === 'A' ? '5' : '4') . '">';
    }
}

/**
 * @param $sql

function db_vertical_table($sql)
 * {
 * $sth = dbquery($sql);
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
 * @return double
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

        return sprintf(__('pggen03') . ' %f ' . __('seconds03') . "\n", $pc_total_time);
    }
}

/**
 * @param $text
 */
function debug($text)
{
    if (true === DEBUG && headers_sent()) {
        echo "<!-- DEBUG: $text -->\n";
    }
}

/**
 * @param $dir
 * @return bool|int
 *
 * @todo rewrite using SPL
 */
function count_files_in_dir($dir)
{
    $file_list_array = @scandir($dir);
    if ($file_list_array === false) {
        return false;
    }

    //there is always . and .. so reduce the count
    return count($file_list_array) - 2;
}

/**
 * @param string $message_headers
 * @return array|bool
 */
function get_mail_relays($message_headers)
{
    $headers = explode("\\n", $message_headers);
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
                    $sqladdr_arr[] = "to_address = '$address' OR to_address like '$address,%' OR to_address like '%,$address' OR to_address like '%,$address,%'";
                } else {
                    $sqladdr_arr[] = "to_address = '$address' OR to_address like '$address,%' OR to_address like '%,$address' OR to_address like '%,$address,%' OR from_address = '$address'";
                }
            }
            $sqladdr = implode(' OR ', $sqladdr_arr);
            break;
        case 'D': // Domain administrator
            foreach ($addresses as $address) {
                if (strpos($address, '@')) {
                    if (defined('FILTER_TO_ONLY') && FILTER_TO_ONLY) {
                        $sqladdr_arr[] = "to_address = '$address' OR to_address like '$address,%' OR to_address like '%,$address' OR to_address like '%,$address,%'";
                    } else {
                        $sqladdr_arr[] = "to_address = '$address' OR to_address like '$address,%' OR to_address like '%,$address' OR to_address like '%,$address,%' OR from_address = '$address'";
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
 * @param string $username
 * @param string $password
 * @return null|string
 */
function ldap_authenticate($username, $password)
{
    $username = ldap_escape(strtolower($username), '', LDAP_ESCAPE_DN);
    if ($username !== '' && $password !== '') {
        $ds = ldap_connect(LDAP_HOST, LDAP_PORT) or die(__('ldpaauth103') . ' ' . LDAP_HOST);

        $ldap_protocol_version = 3;
        if (defined('LDAP_PROTOCOL_VERSION')) {
            $ldap_protocol_version = LDAP_PROTOCOL_VERSION;
        }
        // Check if Microsoft Active Directory compatibility is enabled
        if (defined('LDAP_MS_AD_COMPATIBILITY') && LDAP_MS_AD_COMPATIBILITY === true) {
            ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
            $ldap_protocol_version = 3;
        }
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $ldap_protocol_version);

        $bindResult = @ldap_bind($ds, LDAP_USER, LDAP_PASS);
        if (false === $bindResult) {
            die(ldap_print_error($ds));
        }

        //search for $user in LDAP directory
        $ldap_search_results = ldap_search($ds, LDAP_DN, sprintf(LDAP_FILTER, $username)) or die(__('ldpaauth203'));

        if (false === $ldap_search_results) {
            @trigger_error(__('ldapnoresult03') . ' "' . $username . '"');

            return null;
        }
        if (1 > ldap_count_entries($ds, $ldap_search_results)) {
            //
            @trigger_error(__('ldapresultnodata03') . ' "' . $username . '"');

            return null;
        }
        if (ldap_count_entries($ds, $ldap_search_results) > 1) {
            @trigger_error(__('ldapresultset03') . ' "' . $username . '" ' . __('ldapisunique03'));

            return null;
        }

        if ($ldap_search_results) {
            $result = ldap_get_entries($ds, $ldap_search_results) or die(__('ldpaauth303'));
            ldap_free_result($ldap_search_results);
            if (isset($result[0])) {
                if (in_array('group', array_values($result[0]['objectclass']), true)) {
                    // do not login as group
                    return null;
                }

                if (!isset($result[0][LDAP_USERNAME_FIELD])) {
                    @trigger_error(__('ldapno03') . ' "' . LDAP_USERNAME_FIELD . '" ' . __('ldapresults03'));
                    return null;
                }
                if (!is_array($result[0][LDAP_USERNAME_FIELD])) {
                    $user = $result[0][LDAP_USERNAME_FIELD];
                } elseif (isset($result[0][LDAP_USERNAME_FIELD][0])) {
                    $user = $result[0][LDAP_USERNAME_FIELD][0];
                } else {
                    @trigger_error(__('ldapno03') . ' "' . LDAP_USERNAME_FIELD . '" ' . __('ldapresults03'));
                    return null;
                }

                if (defined('LDAP_BIND_PREFIX')) {
                    $user = LDAP_BIND_PREFIX . $user;
                }
                if (defined('LDAP_BIND_SUFFIX')) {
                    $user .= LDAP_BIND_SUFFIX;
                }

                if (!isset($result[0][LDAP_EMAIL_FIELD])) {
                    @trigger_error(__('ldapno03') . ' "' . LDAP_EMAIL_FIELD . '" ' . __('ldapresults03'));

                    return null;
                }

                $bindResult = @ldap_bind($ds, $user, $password);
                if (false !== $bindResult) {
                    foreach ($result[0][LDAP_EMAIL_FIELD] as $email) {
                        if (0 === strpos($email, 'SMTP')) {
                            $email = strtolower(substr($email, 5));
                            break;
                        }
                    }

                    if (!isset($email)) {
                        //user has no mail but it is required for mailwatch
                        return null;
                    }

                    $sql = sprintf('SELECT username FROM users WHERE username = %s', quote_smart($email));
                    $sth = dbquery($sql);
                    if ($sth->num_rows === 0) {
                        $sql = sprintf(
                            "REPLACE INTO users (username, fullname, type, password) VALUES (%s, %s,'U',NULL)",
                            quote_smart($email),
                            quote_smart($result[0]['cn'][0])
                        );
                        dbquery($sql);
                    }

                    return $email;
                }

                if (ldap_errno($ds) === 49) {
                    //LDAP_INVALID_CREDENTIALS
                    return null;
                }
                die(ldap_print_error($ds));
            }
        }
    }

    return null;
}

/**
 * @param Resource $ds
 * @return string
 */
function ldap_print_error($ds)
{
    return sprintf(
        __('ldapnobind03'),
        LDAP_HOST,
        ldap_errno($ds),
        ldap_error($ds)
    );
}

if (!function_exists('ldap_escape')) {
    define('LDAP_ESCAPE_FILTER', 0x01);
    define('LDAP_ESCAPE_DN', 0x02);

    /**
     * function ldap_escape
     *
     * @source http://stackoverflow.com/questions/8560874/php-ldap-add-function-to-escape-ldap-special-characters-in-dn-syntax#answer-8561604
     * @author Chris Wright
     * @param string $subject The subject string
     * @param string $ignore Set of characters to leave untouched
     * @param int $flags Any combination of LDAP_ESCAPE_* flags to indicate the
     *                   set(s) of characters to escape.
     * @return string The escaped string
     */
    function ldap_escape($subject, $ignore = '', $flags = 0)
    {
        $charMaps = [
            LDAP_ESCAPE_FILTER => ['\\', '*', '(', ')', "\x00"],
            LDAP_ESCAPE_DN => ['\\', ',', '=', '+', '<', '>', ';', '"', '#']
        ];

        // Pre-process the char maps on first call
        if (!isset($charMaps[0])) {
            $charMaps[0] = [];
            for ($i = 0; $i < 256; $i++) {
                $charMaps[0][chr($i)] = sprintf('\\%02x', $i);
            }

            for ($i = 0, $l = count($charMaps[LDAP_ESCAPE_FILTER]); $i < $l; $i++) {
                $chr = $charMaps[LDAP_ESCAPE_FILTER][$i];
                unset($charMaps[LDAP_ESCAPE_FILTER][$i]);
                $charMaps[LDAP_ESCAPE_FILTER][$chr] = $charMaps[0][$chr];
            }

            for ($i = 0, $l = count($charMaps[LDAP_ESCAPE_DN]); $i < $l; $i++) {
                $chr = $charMaps[LDAP_ESCAPE_DN][$i];
                unset($charMaps[LDAP_ESCAPE_DN][$i]);
                $charMaps[LDAP_ESCAPE_DN][$chr] = $charMaps[0][$chr];
            }
        }

        // Create the base char map to escape
        $flags = (int)$flags;
        $charMap = [];
        if ($flags & LDAP_ESCAPE_FILTER) {
            $charMap += $charMaps[LDAP_ESCAPE_FILTER];
        }
        if ($flags & LDAP_ESCAPE_DN) {
            $charMap += $charMaps[LDAP_ESCAPE_DN];
        }
        if (!$charMap) {
            $charMap = $charMaps[0];
        }

        // Remove any chars to ignore from the list
        $ignore = (string)$ignore;
        for ($i = 0, $l = strlen($ignore); $i < $l; $i++) {
            unset($charMap[$ignore[$i]]);
        }

        // Do the main replacement
        $result = strtr($subject, $charMap);

        // Encode leading/trailing spaces if LDAP_ESCAPE_DN is passed
        if ($flags & LDAP_ESCAPE_DN) {
            if ($result[0] === ' ') {
                $result = '\\20' . substr($result, 1);
            }
            if ($result[strlen($result) - 1] === ' ') {
                $result = substr($result, 0, -1) . '\\20';
            }
        }

        return $result;
    }
}

/**
 * @param $entry
 * @return string
 */
function ldap_get_conf_var($entry)
{
    // Translate MailScanner.conf vars to internal
    $entry = translate_etoi($entry);

    $lh = ldap_connect(LDAP_HOST, LDAP_PORT)
    or die(__('ldapgetconfvar103') . ' ' . LDAP_HOST . "\n");

    @ldap_bind($lh)
    or die(__('ldapgetconfvar203') . "\n");

    # As per MailScanner Config.pm
    $filter = '(objectClass=mailscannerconfmain)';
    $filter = "(&$filter(mailScannerConfBranch=main))";

    $sh = ldap_search($lh, LDAP_DN, $filter, [$entry]);

    $info = ldap_get_entries($lh, $sh);
    if ($info['count'] > 0 && $info[0]['count'] !== 0) {
        if ($info[0]['count'] === 0) {
            // Return single value
            return $info[0][$info[0][0]][0];
        }

        // Multi-value option, build array and return as space delimited
        $return = [];
        for ($n = 0; $n < $info[0][$info[0][0]]['count']; $n++) {
            $return[] = $info[0][$info[0][0]][$n];
        }

        return implode(' ', $return);
    }

    // No results
    die(__('ldapgetconfvar303') . " '$entry' " . __('ldapgetconfvar403') . "\n");
}

/**
 * @param $entry
 * @return bool
 */
function ldap_get_conf_truefalse($entry)
{
    // Translate MailScanner.conf vars to internal
    $entry = translate_etoi($entry);

    $lh = ldap_connect(LDAP_HOST, LDAP_PORT)
    or die(__('ldapgetconfvar103') . ' ' . LDAP_HOST . "\n");

    @ldap_bind($lh)
    or die(__('ldapgetconfvar203') . "\n");

    # As per MailScanner Config.pm
    $filter = '(objectClass=mailscannerconfmain)';
    $filter = "(&$filter(mailScannerConfBranch=main))";

    $sh = ldap_search($lh, LDAP_DN, $filter, [$entry]);

    $info = ldap_get_entries($lh, $sh);
    debug(debug_print_r($info));
    if ($info['count'] > 0) {
        debug('Entry: ' . debug_print_r($info[0][$info[0][0]][0]));
        switch ($info[0][$info[0][0]][0]) {
            case 'yes':
            case '1':
                return true;
            case 'no':
            case '0':
            default:
                return false;
        }
    } else {
        // No results
        //die(__('ldapgetconfvar303') . " '$entry' " . __('ldapgetconfvar403') . "\n");
        return false;
    }
}

/**
 * @param string $username
 * @param string $password
 * @return null|string
 */
function imap_authenticate($username, $password)
{
    $username = strtolower($username);

    if (
        (
            !defined('IMAP_USERNAME_FULL_EMAIL') &&
            !filter_var($username, FILTER_VALIDATE_EMAIL)
        )
        ||
        (
            defined('IMAP_USERNAME_FULL_EMAIL') &&
            IMAP_USERNAME_FULL_EMAIL === true &&
            !filter_var($username, FILTER_VALIDATE_EMAIL)
        )
    ) {
        //user has no mail but it is required for mailwatch
        return null;
    }

    if ($username !== '' && $password !== '') {
        $imapUsername = $username;
        if (
            defined('IMAP_USERNAME_FULL_EMAIL') &&
            IMAP_USERNAME_FULL_EMAIL === false
        ) {
            $imapUsername = substr($username, 0, strrpos($username, '@'));
        }
        $mbox = imap_open(IMAP_HOST, $imapUsername, $password, null, 0);

        if (false === $mbox) {
            //auth faild
            return null;
        }

        if (defined('IMAP_AUTOCREATE_VALID_USER') && IMAP_AUTOCREATE_VALID_USER === true) {
            $sql = sprintf('SELECT username FROM users WHERE username = %s', quote_smart($username));
            $sth = dbquery($sql);
            if ($sth->num_rows === 0) {
                $sql = sprintf(
                    "REPLACE INTO users (username, fullname, type, password) VALUES (%s, %s,'U',NULL)",
                    quote_smart($username),
                    quote_smart($username)
                );
                dbquery($sql);
            }
        }

        return $username;
    }

    return null;
}

/**
 * @param $name
 * @return string
 */
function translate_etoi($name)
{
    $name = strtolower($name);
    $file = MS_SHARE_DIR . 'perl/MailScanner/ConfigDefs.pl';
    $fh = fopen($file, 'rb')
    or die(__('dietranslateetoi03') . " $file\n");
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
    if (isset($etoi[(string)$name])) {
        return $etoi[(string)$name];
    }

    return $name;
}

/**
 * @param $input
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
 * @param $input
 * @return string
 */
function debug_print_r($input)
{
    ob_start();
    print_r($input);
    $return = ob_get_contents();
    ob_end_clean();

    return $return;
}

/**
 * @param string $ip
 * @return bool
 */
function return_geoip_country($ip)
{
    require_once __DIR__ . '/lib/maxmind-db/reader/autoload.php';

    $geoLite2File = __DIR__ . '/temp/GeoLite2-Country.mmdb';
    if (file_exists($geoLite2File) && filesize($geoLite2File) > 0) {
        try {
            //check if ipv4 has a port specified (e.g. 10.0.0.10:1025), strip it if found
            $ip = stripPortFromIp($ip);
            $reader = new \MaxMind\Db\Reader($geoLite2File);
            $countryData = $reader->get($ip);
            $reader->close();
            if (isset($countryData['country']['names'][LANG])) {
                return $countryData['country']['names'][LANG];
            }

            return $countryData['country']['names']['en'];
        } catch (Exception $e) {
            return false;
        }
    } else {
        return false;
    }
}

/**
 * @param string $ip
 * @return string
 */
function stripPortFromIp($ip)
{
    if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\:\d{1,5}/', $ip)) {
        $ip = current(array_slice(explode(':', $ip), 0, 1));
    }

    return $ip;
}

/**
 * @param string $input
 * @return array
 */
function quarantine_list($input = '/')
{
    $quarantinedir = get_conf_var('QuarantineDir') . '/';
    $item = [];
    if ($input === '/') {

        // Return top-level directory
        $d = @opendir($quarantinedir);

        while (false !== ($f = @readdir($d))) {
            if ($f !== '.' && $f !== '..') {
                $item[] = $f;
            }
        }
        @closedir($d);
    } else {
        $current_dir = $quarantinedir . $input;
        $dirs = [$current_dir, $current_dir . '/spam', $current_dir . '/nonspam', $current_dir . '/mcp'];
        foreach ($dirs as $dir) {
            if (is_dir($dir) && is_readable($dir)) {
                $d = @opendir($dir);
                while (false !== ($f = readdir($d))) {
                    if ($f !== '.' && $f !== '..') {
                        $item[] = "'$f'";
                    }
                }
                closedir($d);
            }
        }
    }

    if (count($item) > 0) {
        // Sort in reverse chronological order
        arsort($item);
    }

    return $item;
}

/**
 * @param $host
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
 * @param string $msgid
 * @param bool|false $rpc_only
 * @return array|mixed|string
 */
function quarantine_list_items($msgid, $rpc_only = false)
{
    $sql = "
SELECT
  hostname,
  DATE_FORMAT(date,'%Y%m%d') AS date,
  id,
  to_address,
  CASE WHEN isspam>0 THEN 'Y' ELSE 'N' END AS isspam,
  CASE WHEN nameinfected>0 THEN 'Y' ELSE 'N' END AS nameinfected,
  CASE WHEN virusinfected>0 THEN 'Y' ELSE 'N' END AS virusinfected,
  CASE WHEN otherinfected>0 THEN 'Y' ELSE 'N' END AS otherinfected
 FROM
  maillog
 WHERE
  id = '$msgid'";
    $sth = dbquery($sql);
    $rows = $sth->num_rows;
    if ($rows <= 0) {
        die(__('diequarantine103') . " $msgid " . __('diequarantine103') . "\n");
    }
    $row = $sth->fetch_object();
    if (!$rpc_only && is_local($row->hostname)) {
        $quarantinedir = get_conf_var('QuarantineDir');
        $quarantine = $quarantinedir . '/' . $row->date . '/' . $row->id;
        $spam = $quarantinedir . '/' . $row->date . '/spam/' . $row->id;
        $nonspam = $quarantinedir . '/' . $row->date . '/nonspam/' . $row->id;
        $mcp = $quarantinedir . '/' . $row->date . '/mcp/' . $row->id;
        $infected = 'N';
        if ($row->virusinfected === 'Y' || $row->nameinfected === 'Y' || $row->otherinfected === 'Y') {
            $infected = 'Y';
        }
        $quarantined = [];
        $count = 0;
        foreach ([$nonspam, $spam, $mcp] as $category) {
            if (file_exists($category) && is_readable($category)) {
                $quarantined[$count]['id'] = $count;
                $quarantined[$count]['host'] = $row->hostname;
                $quarantined[$count]['msgid'] = $row->id;
                $quarantined[$count]['to'] = $row->to_address;
                $quarantined[$count]['file'] = 'message';
                $quarantined[$count]['type'] = 'message/rfc822';
                $quarantined[$count]['path'] = $category;
                $quarantined[$count]['md5'] = md5($category);
                $quarantined[$count]['dangerous'] = $infected;
                $quarantined[$count]['isspam'] = $row->isspam;
                $count++;
            }
        }
        // Check the main quarantine
        if (is_dir($quarantine) && is_readable($quarantine)) {
            $d = opendir($quarantine) or die(__('diequarantine303') . " $quarantine\n");
            while (false !== ($f = readdir($d))) {
                if ($f !== '..' && $f !== '.') {
                    $quarantined[$count]['id'] = $count;
                    $quarantined[$count]['host'] = $row->hostname;
                    $quarantined[$count]['msgid'] = $row->id;
                    $quarantined[$count]['to'] = $row->to_address;
                    $quarantined[$count]['file'] = $f;
                    $file = escapeshellarg($quarantine . '/' . $f);
                    $quarantined[$count]['type'] = ltrim(rtrim(shell_exec('/usr/bin/file -bi ' . $file)));
                    $quarantined[$count]['path'] = $quarantine . '/' . $f;
                    $quarantined[$count]['md5'] = md5($quarantine . '/' . $f);
                    $quarantined[$count]['dangerous'] = $infected;
                    $quarantined[$count]['isspam'] = $row->isspam;
                    $count++;
                }
            }
            closedir($d);
        }

        return $quarantined;
    }

    // Host is remote call quarantine_list_items by RPC
    debug("Calling quarantine_list_items on $row->hostname by XML-RPC");
    //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$row->hostname,80);
    //if(DEBUG) { $client->setDebug(1); }
    //$parameters = array($input);
    //$msg = new xmlrpcmsg('quarantine_list_items',$parameters);
    $msg = new xmlrpcmsg('quarantine_list_items', [new xmlrpcval($msgid)]);
    $rsp = xmlrpc_wrapper($row->hostname, $msg); //$client->send($msg);
    if ($rsp->faultCode() === 0) {
        $response = php_xmlrpc_decode($rsp->value());
    } else {
        $response = 'XML-RPC Error: ' . $rsp->faultString();
    }

    return $response;
}

/**
 * @param array $list
 * @param array $num
 * @param string $to
 * @param bool|false $rpc_only
 * @return string
 */
function quarantine_release($list, $num, $to, $rpc_only = false)
{
    if (!is_array($list) || !isset($list[0]['msgid'])) {
        return 'Invalid argument';
    }

    $new = quarantine_list_items($list[0]['msgid']);
    $list =& $new;

    if (!$rpc_only && is_local($list[0]['host'])) {
        if (!QUARANTINE_USE_SENDMAIL) {
            // Load in the required PEAR modules
            require_once __DIR__ . '/lib/pear/PEAR.php';
            require_once __DIR__ . '/lib/pear/Mail.php';
            require_once __DIR__ . '/lib/pear/Mail/mime.php';
            require_once __DIR__ . '/lib/pear/Mail/smtp.php';

            $hdrs = ['From' => MAILWATCH_FROM_ADDR, 'Subject' => \ForceUTF8\Encoding::toUTF8(QUARANTINE_SUBJECT), 'Date' => date('r')];
            $mailMimeParams = [
                'eol' => "\r\n",
                'html_charset' => 'UTF-8',
                'text_charset' => 'UTF-8',
                'head_charset' => 'UTF-8'
            ];
            $mime = new Mail_mime($mailMimeParams);
            $mime->setTXTBody(\ForceUTF8\Encoding::toUTF8(QUARANTINE_MSG_BODY));
            // Loop through each selected file and attach them to the mail
            foreach ($num as $key => $val) {
                // If the message is of rfc822 type then set it as Quoted printable
                if (preg_match('/message\/rfc822/', $list[$val]['type'])) {
                    $mime->addAttachment($list[$val]['path'], 'message/rfc822', 'Original Message', true, '');
                } else {
                    // Default is base64 encoded
                    $mime->addAttachment($list[$val]['path'], $list[$val]['type'], $list[$val]['file'], true);
                }
            }
            $mail_param = ['host' => MAILWATCH_MAIL_HOST, 'port' => MAILWATCH_MAIL_PORT];
            if (defined('MAILWATCH_SMTP_HOSTNAME')) {
                $mail_param['localhost'] = MAILWATCH_SMTP_HOSTNAME;
            }
            $body = $mime->get();
            $hdrs = $mime->headers($hdrs);
            $mail = new Mail_smtp($mail_param);

            $m_result = $mail->send(stripslashes($to), $hdrs, $body);
            if (is_a($m_result, 'PEAR_Error')) {
                // Error
                $status = __('releaseerror03') . ' (' . $m_result->getMessage() . ')';
                global $error;
                $error = true;
            } else {
                $sql = "UPDATE maillog SET released = '1' WHERE id = '" . safe_value($list[0]['msgid']) . "'";
                dbquery($sql);
                $status = __('releasemessage03') . ' ' . str_replace(',', ', ', stripslashes($to));
                audit_log(sprintf(__('auditlogquareleased03', true), $list[0]['msgid']) . ' ' . $to);
            }

            return $status;
        }

        // Use sendmail to release message
        // We can only release message/rfc822 files in this way.
        $cmd = QUARANTINE_SENDMAIL_PATH . ' -i -f ' . MAILWATCH_FROM_ADDR . ' ' . escapeshellarg(stripslashes($to)) . ' < ';
        foreach ($num as $key => $val) {
            if (preg_match('/message\/rfc822/', $list[$val]['type'])) {
                debug($cmd . $list[$val]['path']);
                exec($cmd . $list[$val]['path'] . ' 2>&1', $output_array, $retval);
                if ($retval === 0) {
                    $sql = "UPDATE maillog SET released = '1' WHERE id = '" . safe_value($list[0]['msgid']) . "'";
                    dbquery($sql);
                    $status = __('releasemessage03') . ' ' . str_replace(',', ', ', stripslashes($to));
                    audit_log(sprintf(__('auditlogquareleased03', true), $list[$val]['msgid']) . ' ' . $to);
                } else {
                    $status = __('releaseerrorcode03') . ' ' . $retval . ' ' . __('returnedfrom03') . "\n" . implode(
                        "\n",
                        $output_array
                    );
                    global $error;
                    $error = true;
                }

                return $status;
            }
        }
    } else {
        // Host is remote - handle by RPC
        debug('Calling quarantine_release on ' . $list[0]['host'] . ' by XML-RPC');
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
        // Convert input parameters
        $list_output = [];
        foreach ($list as $list_array) {
            $list_struct = [];
            foreach ($list_array as $key => $val) {
                $list_struct[$key] = new xmlrpcval($val);
            }
            $list_output[] = new xmlrpcval($list_struct, 'struct');
        }
        $num_output = [];
        foreach ($num as $key => $val) {
            $num_output[$key] = new xmlrpcval($val);
        }
        // Build input parameters
        $param1 = new xmlrpcval($list_output, 'array');
        $param2 = new xmlrpcval($num_output, 'array');
        $param3 = new xmlrpcval($to, 'string');
        $parameters = [$param1, $param2, $param3];
        $msg = new xmlrpcmsg('quarantine_release', $parameters);
        $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
        if ($rsp->faultCode() === 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            $response = 'XML-RPC Error: ' . $rsp->faultString();
        }

        return $response . ' (RPC)';
    }
}

/**
 * @param $list
 * @param $num
 * @param $type
 * @param bool|false $rpc_only
 * @return string
 */
function quarantine_learn($list, $num, $type, $rpc_only = false)
{
    dbconn();
    if (!is_array($list) || !isset($list[0]['msgid'])) {
        return 'Invalid argument';
    }
    $new = quarantine_list_items($list[0]['msgid']);
    $list =& $new;
    $status = [];
    if (!$rpc_only && is_local($list[0]['host'])) {
        //prevent sa-learn process blocking complete apache server
        session_write_close();
        foreach ($num as $key => $val) {
            $use_spamassassin = false;
            $isfn = '0';
            $isfp = '0';
            switch ($type) {
                case 'ham':
                    $learn_type = 'ham';
                    // Learning SPAM as HAM - this is a false-positive
                    $isfp = ($list[$val]['isspam'] === 'Y' ? '1' : '0');
                    break;
                case 'spam':
                    $learn_type = 'spam';
                    // Learning HAM as SPAM - this is a false-negative
                    $isfn = ($list[$val]['isspam'] === 'N' ? '1' : '0');
                    break;
                case 'forget':
                    $learn_type = 'forget';
                    break;
                case 'report':
                    $use_spamassassin = true;
                    $learn_type = '-r';
                    $isfn = '1';
                    break;
                case 'revoke':
                    $use_spamassassin = true;
                    $learn_type = '-k';
                    $isfp = '1';
                    break;
                default:
                    //TODO handle this case
                    $isfp = null;
            }
            if ($isfp !== null) {
                $sql = 'UPDATE maillog SET isfp=' . $isfp . ', isfn=' . $isfn . " WHERE id='"
                    . safe_value($list[$val]['msgid']) . "'";
            }

            if (true === $use_spamassassin) {
                // Run SpamAssassin to report or revoke spam/ham
                exec(
                    SA_DIR . 'spamassassin -p ' . SA_PREFS . ' ' . $learn_type . ' < ' . $list[$val]['path'] . ' 2>&1',
                    $output_array,
                    $retval
                );
                if ($retval === 0) {
                    // Command succeeded - update the database accordingly
                    if (isset($sql)) {
                        debug("Learner - running SQL: $sql");
                        dbquery($sql);
                    }
                    $status[] = __('spamassassin03') . ' ' . implode(', ', $output_array);
                    switch ($learn_type) {
                        case '-r':
                            $learn_type = 'spam';
                            break;
                        case '-k':
                            $learn_type = 'ham';
                            break;
                    }
                    audit_log(
                        sprintf(__('auditlogquareleased03', true) . ' ', $list[$val]['msgid']) . ' ' . $learn_type
                    );
                } else {
                    $status[] = __('spamerrorcode0103') . ' ' . $retval . __('spamerrorcode0203') . "\n" . implode(
                        "\n",
                        $output_array
                    );
                    global $error;
                    $error = true;
                }
            } else {
                // Only sa-learn required
                $max_size_option = '';
                if (defined('SA_MAXSIZE') && is_int(SA_MAXSIZE) && SA_MAXSIZE >= 0) {
                    $max_size_option = ' --max-size ' . SA_MAXSIZE;
                }

                exec(
                    SA_DIR . 'sa-learn -p ' . SA_PREFS . ' --' . $learn_type . ' --file ' . $list[$val]['path'] . $max_size_option . ' 2>&1',
                    $output_array,
                    $retval
                );

                if ($retval === 0) {
                    // Command succeeded - update the database accordingly
                    if (isset($sql)) {
                        debug("Learner - running SQL: $sql");
                        dbquery($sql);
                    }
                    $status[] = __('salearn03') . ' ' . implode(', ', $output_array);
                    audit_log(sprintf(__('auditlogspamtrained03', true), $list[$val]['msgid']) . ' ' . $learn_type);
                } else {
                    $status[] = __('salearnerror03') . ' ' . $retval . ' ' . __('salearnreturn03') . "\n" . implode(
                        "\n",
                        $output_array
                    );
                    global $error;
                    $error = true;
                }
            }
            if (!isset($error)) {
                if ($learn_type === 'spam') {
                    $numeric_type = 2;
                }
                if ($learn_type === 'ham') {
                    $numeric_type = 1;
                }
                if (isset($numeric_type)) {
                    $sql = "UPDATE `maillog` SET salearn = '$numeric_type' WHERE id = '" . safe_value($list[$val]['msgid']) . "'";
                    dbquery($sql);
                }
            }
        }

        return implode("\n", $status);
    }

    // Call by RPC
    debug('Calling quarantine_learn on ' . $list[0]['host'] . ' by XML-RPC');
    //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
    // Convert input parameters
    $list_output = [];
    foreach ($list as $list_array) {
        $list_struct = [];
        foreach ($list_array as $key => $val) {
            $list_struct[$key] = new xmlrpcval($val);
        }
        $list_output[] = new xmlrpcval($list_struct, 'struct');
    }
    $num_output = [];
    foreach ($num as $key => $val) {
        $num_output[$key] = new xmlrpcval($val);
    }
    // Build input parameters
    $param1 = new xmlrpcval($list_output, 'array');
    $param2 = new xmlrpcval($num_output, 'array');
    $param3 = new xmlrpcval($type, 'string');
    $parameters = [$param1, $param2, $param3];
    $msg = new xmlrpcmsg('quarantine_learn', $parameters);
    $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
    if ($rsp->faultCode() === 0) {
        $response = php_xmlrpc_decode($rsp->value());
    } else {
        $response = 'XML-RPC Error: ' . $rsp->faultString();
    }

    return $response . ' (RPC)';
}

/**
 * @param $list
 * @param $num
 * @param bool|false $rpc_only
 * @return string
 */
function quarantine_delete($list, $num, $rpc_only = false)
{
    if (!is_array($list) || !isset($list[0]['msgid'])) {
        return 'Invalid argument';
    }

    $new = quarantine_list_items($list[0]['msgid']);
    $list =& $new;

    if (!$rpc_only && is_local($list[0]['host'])) {
        $status = [];
        foreach ($num as $key => $val) {
            if (@unlink($list[$val]['path'])) {
                $status[] = 'Delete: deleted file ' . $list[$val]['path'];
                dbquery("UPDATE maillog SET quarantined=NULL WHERE id='" . $list[$val]['msgid'] . "'");
                audit_log(__('auditlogdelqua03', true) . ' ' . $list[$val]['path']);
            } else {
                $status[] = __('auditlogdelerror03') . ' ' . $list[$val]['path'];
                global $error;
                $error = true;
            }
        }

        return implode("\n", $status);
    }

    // Call by RPC
    debug('Calling quarantine_delete on ' . $list[0]['host'] . ' by XML-RPC');
    //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
    // Convert input parameters
    $list_output = [];
    foreach ($list as $list_array) {
        $list_struct = [];
        foreach ($list_array as $key => $val) {
            $list_struct[$key] = new xmlrpcval($val);
        }
        $list_output[] = new xmlrpcval($list_struct, 'struct');
    }
    $num_output = [];
    foreach ($num as $key => $val) {
        $num_output[$key] = new xmlrpcval($val);
    }
    // Build input parameters
    $param1 = new xmlrpcval($list_output, 'array');
    $param2 = new xmlrpcval($num_output, 'array');
    $parameters = [$param1, $param2];
    $msg = new xmlrpcmsg('quarantine_delete', $parameters);
    $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
    if ($rsp->faultCode() === 0) {
        $response = php_xmlrpc_decode($rsp->value());
    } else {
        $response = 'XML-RPC Error: ' . $rsp->faultString();
    }

    return $response . ' (RPC)';
}

/**
 * @param $id
 * @return mixed
 */
function fixMessageId($id)
{
    $mta = get_conf_var('mta');
    if ($mta === 'postfix') {
        $id = str_replace('_', '.', $id);
    }

    return $id;
}

/**
 * @param string $action
 * @param string $user
 * @return bool
 */
function audit_log($action, $user = 'unknown')
{
    $link = dbconn();
    if (AUDIT) {
        if (isset($_SESSION['myusername'])) {
            $user = $link->real_escape_string(stripslashes($_SESSION['myusername']));
        }

        $action = safe_value(stripslashes($action));

        $ip = null;
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = safe_value($_SERVER['REMOTE_ADDR']);
        }

        $ret = dbquery("INSERT INTO audit_log (user, ip_address, action) VALUES ('$user', '$ip', '$action')");
        if ($ret) {
            return true;
        }
    }

    return false;
}

/**
 * @param $array
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
 * @return mixed
 */
function read_ruleset_default($file)
{
    $fh = fopen($file, 'rb') or die(__('diereadruleset03') . " ($file)");
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, filesize($file)));
        if (preg_match('/(\S+)\s+(\S+)\s+(\S+)/', $line, $regs)) {
            if (strtolower($regs[2]) === 'default') {
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
 * @param $scanner
 * @return string|false
 */
function get_virus_conf($scanner)
{
    $fh = fopen(MS_CONFIG_DIR . 'virus.scanners.conf', 'rb');
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, 1048576));
        if (preg_match("/(^[^#]\S+)\s+(\S+)\s+(\S+)/", $line, $regs)) {
            if ($regs[1] === $scanner) {
                fclose($fh);

                return $regs[2] . ' ' . $regs[3];
            }
        }
    }
    // Not found
    fclose($fh);

    return false;
}

/**
 * @return array
 */
function return_quarantine_dates()
{
    $array = [];
    for ($d = 0; $d < QUARANTINE_DAYS_TO_KEEP; $d++) {
        $array[] = date('Ymd', mktime(0, 0, 0, date('m'), date('d') - $d, date('Y')));
    }

    return $array;
}

/**
 * @param string $virus
 * @return string
 */
function return_virus_link($virus, $truncateOutput = false)
{
    $virus = htmlentities($virus);
    // Truncate excessively long output (more than 30 characters)
    if ($truncateOutput && strlen($virus) > 30) {
        $virusText = substr($virus, 0, 30) . '...';
    } else {
        $virusText = $virus;
    }

    if (defined('VIRUS_INFO') && VIRUS_INFO !== false) {
        $link = sprintf(VIRUS_INFO, $virus);

        return sprintf('<a href="%s">%s</a>', $link, $virusText);
    }

    return $virusText;
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
    if (defined('RPC_ALLOWED_CLIENTS') && (!RPC_ALLOWED_CLIENTS === false)) {
        // Read in space separated list
        $clients = explode(' ', constant('RPC_ALLOWED_CLIENTS'));
        // Validate each client type
        foreach ($clients as $client) {
            if ($client === 'allprivate' && ip_in_range($_SERVER['SERVER_ADDR'], false, 'private')) {
                return true;
            }
            if ($client === 'local24') {
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
 * @param $user
 * @param $hash
 */
function updateUserPasswordHash($user, $hash)
{
    $sqlCheckLenght = "SELECT CHARACTER_MAXIMUM_LENGTH AS passwordfieldlength FROM information_schema.columns WHERE column_name = 'password' AND table_name = 'users'";
    $passwordFiledLengthResult = dbquery($sqlCheckLenght);
    $passwordFiledLength = (int)database::mysqli_result($passwordFiledLengthResult, 0, 'passwordfieldlength');

    if ($passwordFiledLength < 255) {
        $sqlUpdateFieldLength = 'ALTER TABLE `users` CHANGE `password` `password` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL';
        dbquery($sqlUpdateFieldLength);
        audit_log(sprintf(__('auditlogquareleased03', true) . ' ', $passwordFiledLength));
    }

    $sqlUpdateHash = "UPDATE `users` SET `password` = '$hash' WHERE `users`.`username` = '$user'";
    dbquery($sqlUpdateHash);
    audit_log(__('auditlogupdateuser03', true) . ' ' . $user);
}

/**
 * @param string $username username that should be checked if it exists
 * @return boolean true if user exists, else false
 */
function checkForExistingUser($username)
{
    $sqlQuery = "SELECT COUNT(username) AS counter FROM users WHERE username = '" . safe_value(stripslashes($username)) . "'";
    $row = dbquery($sqlQuery)->fetch_object();

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
        'DB_PORT',
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
        'PROXY_TYPE',
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
        'MAIL_SENDER'  => ['description' => 'needed if you use Exim or Sendmail Queue'],
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
        'MAXMIND_LICENSE_KEY' => ['description' => 'needed to download MaxMind GeoLite2 data']
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
 * @param integer $lenght
 * @return string
 * @throws Exception
 */
function get_random_string($lenght)
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($lenght));
    }

    if (function_exists('mcrypt_create_iv')) {
        $random = mcrypt_create_iv($lenght, MCRYPT_DEV_URANDOM);
        if (false !== $random) {
            return bin2hex($random);
        }
    }

    if (DIRECTORY_SEPARATOR === '/' && @is_readable('/dev/urandom')) {
        // On unix system and if /dev/urandom is readable
        $handle = fopen('/dev/urandom', 'rb');
        $random = fread($handle, $lenght);
        fclose($handle);

        return bin2hex($random);
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $random = openssl_random_pseudo_bytes($lenght);
        if (false !== $random) {
            return bin2hex($random);
        }
    }

    // if none of the above three secure functions are enabled use a pseudorandom string generator
    // note to sysadmin: check your php installation if the following code is executed and make your system secure!
    $random = '';
    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $lenght; ++$i) {
        $random .= $keyspace[mt_rand(0, $max)];
    }

    return $random;
}

/**
 * @param string $email
 * @param string $html
 * @param string $text
 * @param string $subject
 * @param bool $pwdreset
 * @return mixed
 */
function send_email($email, $html, $text, $subject, $pwdreset = false)
{
    $mime = new Mail_mime("\n");
    if ($pwdreset === true && (defined('PWD_RESET_FROM_NAME') && defined('PWD_RESET_FROM_ADDRESS') && PWD_RESET_FROM_NAME !== '' && PWD_RESET_FROM_ADDRESS !== '')) {
        $sender = PWD_RESET_FROM_NAME . '<' . PWD_RESET_FROM_ADDRESS . '>';
    } else {
        $sender = QUARANTINE_REPORT_FROM_NAME . ' <' . MAILWATCH_FROM_ADDR . '>';
    }
    $hdrs = [
        'From' => $sender,
        'To' => $email,
        'Subject' => $subject,
        'Date' => date('r')
    ];
    $mime_params = [
        'text_encoding' => '7bit',
        'text_charset' => 'UTF-8',
        'html_charset' => 'UTF-8',
        'head_charset' => 'UTF-8'
    ];
    $mime->addHTMLImage(MAILWATCH_HOME . '/' . IMAGES_DIR . MW_LOGO, 'image/png', MW_LOGO, true);
    $mime->setTXTBody($text);
    $mime->setHTMLBody($html);
    $body = $mime->get($mime_params);
    $hdrs = $mime->headers($hdrs);
    $mail_param = ['host' => MAILWATCH_MAIL_HOST, 'port' => MAILWATCH_MAIL_PORT];
    if (defined('MAILWATCH_SMTP_HOSTNAME')) {
        $mail_param['localhost'] = MAILWATCH_SMTP_HOSTNAME;
    }
    $mail = new Mail_smtp($mail_param);

    return $mail->send($email, $hdrs, $body);
}

/**
 * @param $ip
 * @param bool|string $net
 * @param bool|string $privateLocal
 * @return bool
 */
function ip_in_range($ip, $net = false, $privateLocal = false)
{
    require_once __DIR__ . '/lib/IPSet.php';
    if ($privateLocal === 'private') {
        $privateIPSet = new \IPSet\IPSet([
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            'fc00::/7',
            'fe80::/10',
        ]);

        return $privateIPSet->match($ip);
    }

    if ($privateLocal === 'local') {
        $localIPSet = new \IPSet\IPSet([
            '127.0.0.0/8',
            '::1',
        ]);

        return $localIPSet->match($ip);
    }

    if ($privateLocal === false && $net !== false) {
        $network = new \IPSet\IPSet([
            $net
        ]);

        return $network->match($ip);
    }

    //return false to fail gracefully
    return false;
}

/**
 * @param string $input
 * @param string $type
 * @return string|false
 */
function deepSanitizeInput($input, $type)
{
    switch ($type) {
        case 'email':
            $string = filter_var($input, FILTER_SANITIZE_EMAIL);
            $string = sanitizeInput($string);
            $string = safe_value($string);

            return $string;
        case 'url':
            $string = filter_var($input, FILTER_SANITIZE_URL);
            $string = sanitizeInput($string);
            $string = htmlentities($string);
            $string = safe_value($string);

            return $string;
        case 'num':
            $string = filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            $string = sanitizeInput($string);
            $string = safe_value($string);

            return $string;
        case 'float':
            $string = filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $string = sanitizeInput($string);
            $string = safe_value($string);

            return $string;
        case 'string':
            $string = filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_BACKTICK);
            $string = sanitizeInput($string);
            $string = safe_value($string);

            return $string;
        default:
            return false;
    }
}

/**
 * @param string|bool $input
 * @param string $type
 * @return boolean
 */
function validateInput($input, $type)
{
    switch ($type) {
        case 'email':
            if (filter_var(stripslashes($input), FILTER_VALIDATE_EMAIL)) {
                return true;
            }
            break;
        case 'user':
            if (filter_var(stripslashes($input), FILTER_VALIDATE_EMAIL)) {
                return true;
            } elseif (preg_match('/^[\p{L}\p{M}\p{N}\&~!@$%^*=_:.\/+-\\\\\']{1,256}$/u', stripslashes($input))) {
                return true;
            }
            break;
        case 'general':
            if (preg_match('/^[\p{L}\p{M}\p{N}\p{Z}\p{P}\p{S}]{1,256}$/u', $input)) {
                return true;
            }
            break;
        case 'yn':
            if (preg_match('/^[YNyn]$/', $input)) {
                return true;
            }
            break;
        case 'quardir':
            if (preg_match('/^[0-9]{8}$/', $input)) {
                return true;
            }
            break;
        case 'num':
            if (preg_match('/^[0-9]{1,256}$/', $input)) {
                return true;
            }
            break;
        case 'float':
            if (is_float(filter_var($input, FILTER_VALIDATE_FLOAT))) {
                return true;
            }
            break;
        case 'orderby':
            if (preg_match('/^(datetime|from_address|to_address|subject|size|sascore|clientip)$/', $input)) {
                return true;
            }
            break;
        case 'orderdir':
            if (preg_match('/^[ad]$/', $input)) {
                return true;
            }
            break;
        case 'msgid':
            if (preg_match(
                '/^([A-F0-9]{7,20}\.[A-F0-9]{5}|[0-9B-DF-HJ-NP-TV-Zb-df-hj-np-tv-z]{8,20}|[0-9B-DF-HJ-NP-TV-Zb-df-hj-np-tv-z.]{8,20}\.[A-F0-9]{5}|[0-9A-Za-z]{6}-[A-Za-z0-9]{6}-[A-Za-z0-9]{2}|[0-9A-Za-z]{12,15})$/',
                $input
            )) {
                return true;
            }
            break;
        case 'urltype':
            if (preg_match('/^[hf]$/', $input)) {
                return true;
            }
            break;
        case 'host':
            if (preg_match('/^[\p{N}\p{L}\p{M}.:-]{2,256}$/u', $input)) {
                return true;
            }
            break;
        case 'list':
            if (preg_match('/^[wb]$/', $input)) {
                return true;
            }
            break;
        case 'listsubmit':
            if (preg_match('/^(add|delete)$/', $input)) {
                return true;
            }
            break;
        case 'releasetoken':
            if (preg_match('/^[0-9A-Fa-f]{20}$/', $input)) {
                return true;
            }
            break;
        case 'resetid':
            if (preg_match('/^[0-9A-Za-z]{32}$/', $input)) {
                return true;
            }
            break;
        case 'mailq':
            if (preg_match('/^(inq|outq)$/', $input)) {
                return true;
            }
            break;
        case 'salearnops':
            if (preg_match('/^(spam|ham|forget|report|revoke)$/', $input)) {
                return true;
            }
            break;
        case 'file':
            if (preg_match('/^[A-Za-z0-9._-]{2,256}$/', $input)) {
                return true;
            }
            break;
        case 'date':
            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $input)) {
                return true;
            }
            break;
        case 'alnum':
            if (preg_match('/^[0-9A-Za-z]{1,256}$/', $input)) {
                return true;
            }
            break;
        case 'ip':
            if (filter_var($input, FILTER_VALIDATE_IP)) {
                return true;
            }
            break;
        case 'action':
            if (preg_match('/^(new|edit|delete|filters|logout)$/', $input)) {
                return true;
            }
            break;
        case 'type':
            if (preg_match('/^[UDA]$/', $input)) {
                return true;
            }
            break;
        case 'mimepart':
            if (preg_match('/^[0-9.]{1,10}$/', $input)) {
                return true;
            }
            break;
        case 'loginerror':
            if (preg_match('/^(baduser|emptypassword|timeout|pagetimeout)$/', $input)) {
                return true;
            }
            break;
        case 'timeout':
            if (preg_match('/^[0-9]{1,5}$/', $input)) {
                return true;
            }
            break;
        case 'maxmind':
            if (preg_match('/^([0-9A-Za-z]{12}|[0-9A-Za-z]{16})$/', $input)) {
                return true;
            }
            break;
        default:
            return false;
    }
    return false;
}

/**
 * @return string
 * @throws Exception
 */
function generateToken()
{
    $tokenLenght = 32;

    return get_random_string($tokenLenght);
}

/**
 * @param string $token
 * @return boolean
 */
function checkToken($token)
{
    if (!isset($_SESSION['token'])) {
        return false;
    }

    return $_SESSION['token'] === deepSanitizeInput($token, 'url');
}

/**
 * @param string $formstring
 * @return string
 */
function generateFormToken($formstring)
{
    if (!isset($_SESSION['token'])) {
        header('Location: login.php?error=pagetimeout');
        die();
    }

    return hash_hmac('sha256', $formstring . $_SESSION['token'], $_SESSION['formtoken']);
}

/**
 * @param string $formstring
 * @param string $formtoken
 * @return bool
 */
function checkFormToken($formstring, $formtoken)
{
    if (!isset($_SESSION['token'], $_SESSION['formtoken'])) {
        return false;
    }
    $calc = hash_hmac('sha256', $formstring . $_SESSION['token'], $_SESSION['formtoken']);

    return $calc === deepSanitizeInput($formtoken, 'url');
}

/**
 * Checks if the passed language code is allowed to be used for the users
 * @param string $langCode
 * @return boolean
 */
function checkLangCode($langCode)
{
    $validLang = explode(',', USER_SELECTABLE_LANG);
    $found = array_search($langCode, $validLang);
    if ($found === false || $found === null) {
        audit_log(sprintf(__('auditundefinedlang12', true), $langCode));
        return false;
    }

    return true;
}

/**
 * Updates the user login expiry
 * @param string $myusername
 * @return bool|mysqli_result
 */
function updateLoginExpiry($myusername)
{
    $sql = "SELECT login_timeout from users where username='" . safe_value(stripslashes($myusername)) . "'";
    $result = dbquery($sql);

    if ($result->num_rows === 0) {
        // Something went wrong, or user no longer exists
        return false;
    }

    $login_timeout = database::mysqli_result($result, 0, 'login_timeout');

    // Use global if individual value is disabled (-1)
    if ($login_timeout === '-1') {
        if (defined('SESSION_TIMEOUT')) {
            if (SESSION_TIMEOUT > 0 && SESSION_TIMEOUT <= 99999) {
                $expiry_val = (time() + SESSION_TIMEOUT);
            } else {
                $expiry_val = 0;
            }
        } else {
            $expiry_val = (time() + 600);
        }
        // If set, use the individual timeout
    } elseif ($login_timeout === '0') {
        $expiry_val = 0;
    } else {
        $expiry_val = (time() + (int)$login_timeout);
    }
    $sql = "UPDATE users SET login_expiry='" . $expiry_val . "', last_login='" . time() . "' WHERE username='" . safe_value(stripslashes($myusername)) . "'";
    $result = dbquery($sql);

    return $result;
}

/**
 * Checks the user login expiry against the current time, if enabled
 * Returns true if expired
 * @param string $myusername
 * @return boolean
 */
function checkLoginExpiry($myusername)
{
    $sql = "SELECT login_expiry FROM users WHERE username='" . safe_value(stripslashes($myusername)) . "'";
    $result = dbquery($sql);

    if ($result->num_rows === 0) {
        // Something went wrong, or user no longer exists
        return true;
    }

    $login_expiry = database::mysqli_result($result, 0, 'login_expiry');

    if ($login_expiry === '-1') {
        // User administratively logged out
        return true;
    }

    if ($login_expiry === '0') {
        // Login never expires, so just return false
        return false;
    }

    if ((int)$login_expiry > time()) {
        // User is active
        return false;
    }

    // User has timed out
    return true;
}

/**
 * Checks for a privilege change, returns true if changed
 * @param string $myusername
 * @return boolean
 */
function checkPrivilegeChange($myusername)
{
    $sql = "SELECT type FROM users WHERE username='" . safe_value(stripslashes($myusername)) . "'";
    $result = dbquery($sql);

    if ($result->num_rows === 0) {
        // Something went wrong, or user does not exist
        return true;
    }

    $user_type = database::mysqli_result($result, 0, 'type');

    if ($_SESSION['user_type'] !== $user_type) {
        // Privilege change detected
        return true;
    }

    return false;
}

function printTrafficGraph()
{
    require_once __DIR__ . '/graphgenerator.inc.php';

    $graphInterval = (defined('STATUSGRAPH_INTERVAL') ? STATUSGRAPH_INTERVAL : 60);

    echo '<td align="center" valign="top">' . "\n";
    echo '   <table border="0" cellpadding="1" cellspacing="1" class="mail">' . "\n";
    if ($graphInterval <= 60) {
        echo '    <tr><th colspan="1">' . __('trafficgraph03') . '</th></tr>' . "\n";
    } else {
        echo '    <tr><th colspan="1">' . sprintf(__('trafficgraphmore03'), $graphInterval / 60) . '</th></tr>' . "\n";
    }
    echo '    <tr>' . "\n";
    echo '    <td>' . "\n";

    $graphgenerator = new GraphGenerator();
    $graphgenerator->sqlQuery = '
     SELECT
      timestamp AS xaxis,
      1 as total_mail,
      CASE
      WHEN virusinfected > 0 THEN 1
      WHEN nameinfected > 0 THEN 1
      WHEN otherinfected > 0 THEN 1
      ELSE 0 END AS total_virus,
      isspam AS total_spam
     FROM
      maillog
     WHERE
      1=1
     AND
      timestamp BETWEEN (NOW() - INTERVAL ' . $graphInterval . ' MINUTE) AND NOW()
     ORDER BY
      timestamp DESC
    ';

    $graphgenerator->sqlColumns = [
        'xaxis',
        'total_mail',
        'total_virus',
        'total_spam',
    ];
    $graphgenerator->valueConversion = [
        'xaxis' => 'generatetimescale',
        'total_mail' => 'timescale',
        'total_virus' => 'timescale',
        'total_spam' => 'timescale',
    ];
    $graphgenerator->graphColumns = [
        'labelColumn' => 'time',
        'dataLabels' => [
            [__('barvirus03'), __('barspam03'), __('barmail03')],
        ],
        'dataNumericColumns' => [
            ['total_virusconv', 'total_spamconv', 'total_mailconv'],
        ],
        'dataFormattedColumns' => [
            ['total_virusconv', 'total_spamconv', 'total_mailconv'],
        ],
        'xAxeDescription' => '',
        'yAxeDescriptions' => [
            '',
        ],
        'fillBelowLine' => ['true']
    ];
    $graphgenerator->types = [
        ['line', 'line', 'line'],
    ];
    $graphgenerator->graphTitle = '';
    $graphgenerator->settings['timeInterval'] = 'PT' . $graphInterval . 'M';
    $graphgenerator->settings['timeScale'] = 'PT1M';
    $graphgenerator->settings['timeGroupFormat'] = 'Y-m-dTH:i:00';
    $graphgenerator->settings['timeFormat'] = 'H:i';

    $graphgenerator->settings['maxTicks'] = 6;
    $graphgenerator->settings['plainGraph'] = true;
    $graphgenerator->settings['drawLines'] = true;
    $graphgenerator->settings['chartId'] = 'trafficgraph';
    $graphgenerator->settings['ignoreEmptyResult'] = true;
    $graphgenerator->settings['colors'] = [['virusColor', 'spamColor', 'mailColor']];
    $graphgenerator->printTable = false;
    $graphgenerator->printLineGraph();

    echo '    </td>' . "\n";
    echo '    </tr>' . "\n";
    echo '  </table>' . "\n";
    echo '  </td>' . "\n";
}

/**
 * @param string $report virus report message
 * @return string|null
 */
function getVirus($report)
{
    $match = null;
    if (defined('VIRUS_REGEX')) {
        preg_match(VIRUS_REGEX, $report, $match);
    } else {
        $scanners = explode(' ', get_conf_var('VirusScanners'));
        foreach ($scanners as $scanner) {
            $scannerRegex = getVirusRegex($scanner);
            if ($scannerRegex === null || $scannerRegex === '') {
                error_log('Could not find regex for virus scanner ' . $scanner);
                continue;
            }
            if (preg_match($scannerRegex, $report, $match) === 1) {
                break;
            }
        }
    }
    if (isset($match['virus'])) {
        return $match['virus'];
    }
    return $report;
}

/**
 * @param string $myusername contains username (or empty) used for login attempt
 */
function logFailedLogin($myusername = "")
{
    error_log("MailWatch failed login attempt from: [".getHTTPClientIP()."] for User: ".$myusername);
}

/**
 * @return string HTTP client IP Address
 */
function getHTTPClientIP()
{
    $remote_addr = $_SERVER['REMOTE_ADDR'];

    if (defined('TRUSTED_PROXIES') && !empty(TRUSTED_PROXIES)) {
        if (defined('PROXY_HEADER') && (! isset($_SERVER[PROXY_HEADER]) || empty($_SERVER[PROXY_HEADER]))) {
            return $remote_addr;
        }

        //check if remote_addr is a trusted proxy:
        if (! in_array($remote_addr, TRUSTED_PROXIES)) {
            return $remote_addr;
        }

        //remove all trusted proxies from header
        $ips = explode(',', $_SERVER[PROXY_HEADER]);
        $ips = array_map('trim', $ips);
        $ips = array_diff($ips, TRUSTED_PROXIES);

        if (empty($ips)) {
            return $remote_addr;
        }

        //the last entry should be the real client ip
        return array_pop($ips);
    } else {
        return $remote_addr;
    }
}
