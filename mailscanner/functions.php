<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Background colours
$bg_colors = array("#EBEBEB", "#D8D8D8");

// Set error level (some distro's have php.ini set to E_ALL)
if (version_compare(phpversion(), '5.3.0', '<')) {
    error_reporting(E_ALL);
} else {
    // E_DEPRECATED added in PHP 5.3
    error_reporting(E_ALL ^ E_DEPRECATED);
}

// Read in MailWatch configuration file
if (!(@include_once('conf.php')) == true) {
    die("Cannot read conf.php - please create it by copying conf.php.example and modifying the parameters to suit.\n");
}

if (SSL_ONLY && (!empty($_SERVER['PHP_SELF']))) {
    if (!$_SERVER['HTTPS'] == 'on') {
        header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Set PHP path to use local PEAR modules only
ini_set('include_path', '.:' . MAILWATCH_HOME . '/lib/pear:' . MAILWATCH_HOME . '/lib/xmlrpc');

// XML-RPC
@include_once('lib/xmlrpc/xmlrpc.inc');
@include_once('lib/xmlrpc/xmlrpcs.inc');
@include_once('lib/xmlrpc/xmlrpc_wrappers.inc');

include "postfix.inc";

/*
 For reporting of Virus names and statistics a regular expression matching
 the output of your virus scanner is required.  As Virus names vary across
 the vendors and are therefore impossible to match - you can only define one
 scanner as your primary scanner - this should be the scanner you wish to
 report against.  It defaults to the first scanner found in MailScanner.conf.

 Please submit any new regular expressions to the MailWatch mailing-list or
 to me - smf@f2s.com.

 If you are running MailWatch in DISTRIBUTED_MODE or you wish to override the
 selection of the regular expression - you will need to add on of the following
 statements to conf.php and set the regular expression manually.
*/
// define('VIRUS_REGEX', '<<your regexp here>>');
// define('VIRUS_REGEX', '/(\S+) was infected by (\S+)/');

if (!defined('VIRUS_REGEX')) {
    switch ($scanner = get_primary_scanner()) {
        case 'none':
            define('VIRUS_REGEX', '/^Dummy$/');
            break;
        case 'sophos':
            define('VIRUS_REGEX', '/(>>>) Virus \'(\S+)\' found/');
            break;
        case 'sophossavi':
            define('VIRUS_REGEX', '/(\S+) was infected by (\S+)/');
            break;
        case 'clamav':
            define('VIRUS_REGEX', '/(.+) contains (\S+)/');
            break;
        case 'clamd':
            define('VIRUS_REGEX', '/(.+) was infected: (\S+)/');
            break;
        case 'clamavmodule':
            define('VIRUS_REGEX', '/(.+) was infected: (\S+)/');
            break;
        case 'f-prot':
            define('VIRUS_REGEX', '/(.+) Infection: (\S+)/');
            break;
        case 'f-protd-6':
            define('VIRUS_REGEX', '/(.+) Infection: (\S+)/');
            break;
        case 'mcafee':
            define('VIRUS_REGEX', '/(.+) Found the (\S+) virus !!!/');
            break;
        case 'mcafee6':
            define('VIRUS_REGEX', '/(.+) Found the (\S+) virus !!!/');
            break;
        case 'f-secure':
            define('VIRUS_REGEX', '/(.+) Infected: (\S+)/');
            break;
        case 'trend':
            define('VIRUS_REGEX', '/(Found virus) (\S+) in file (\S+)/');
            break;
        case 'bitdefender':
            define('VIRUS_REGEX', '/(\S+) Found virus (\S+)/');
            break;
        case 'kaspersky-4.5':
            define('VIRUS_REGEX', '/(.+) INFECTED (\S+)/');
            break;
        case 'etrust':
            define('VIRUS_REGEX', '/(\S+) is infected by virus: (\S+)/');
            break;
        case 'avg':
            define('VIRUS_REGEX', '/(Found virus) (\S+) in file (\S+)/');
            break;
        case 'norman':
            define('VIRUS_REGEX', '/(Found virus) (\S+) in file (\S+)/');
            break;
        case 'nod32-1.99':
            define('VIRUS_REGEX', '/(Found virus) (\S+) in (\S+)/');
            break;
        case 'antivir':
            define('VIRUS_REGEX', '/(ALERT:) \[(\S+) \S+\]/');
            break;
        //default:
        // die("<B>Error:</B><BR>\n&nbsp;Unable to select a regular expression for your primary virus scanner ($scanner) - please see the examples in functions.php to create one.\n");
        // break;
    }
} else {
    // Have to set manually as running in DISTRIBUTED_MODE
    die("<B>Error:</B><BR>\n&nbsp;You are running MailWatch in distributed mode therefore MailWatch cannot read your MailScanner configuration files to acertain your primary virus scanner - please edit functions.php and manually set the VIRUS_REGEX constant for your primary scanner.\n");
}


///////////////////////////////////////////////////////////////////////////////
// Functions
///////////////////////////////////////////////////////////////////////////////

function html_start($title, $refresh = 0, $cacheable = true, $report = false)
{
    if (!$cacheable) {
        // Cache control (as per PHP website)
        header("Expires: Sat, 10 May 2003 00:00:00 GMT");
        header("Last-Modified: " . gmdate("D, M d Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
    } else {
        // calc an offset of 24 hours
        $offset = 3600 * 48;
        // calc the string in GMT not localtime and add the offset
        $expire = "Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
        //output the HTTP header
        Header($expire);
        header("Cache-Control: store, cache, must-revalidate, post-check=0, pre-check=1");
        header("Pragma: cache");
    }
    page_creation_timer();
    echo '<!doctype html public "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">' . "\n";
    echo '<html>' . "\n";
    echo '<head>' . "\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . "\n";
    echo '<link rel="shortcut icon" href="images/favicon.png" >' . "\n";
    echo '<script type="text/javascript">';
    echo '' . java_time() . '';
    //$current_url = "".MAILWATCH_HOME."/status.php";
    //if($_SERVER['SCRIPT_FILENAME'] == $active_url){
    echo '' . row_highandclick() . '';
    echo '</script>';
    if ($report) {
        echo '<title>MailWatch Filter Report: ' . $title . ' </title>' . "\n";
        echo '<link rel="StyleSheet" type="text/css" href="./style.css">' . "\n";
        if (!isset($_SESSION["filter"])) {
            require_once('./filter.inc');
            $filter = new Filter;
            $_SESSION["filter"] = $filter;
        } else {
            // Use existing filters
            $filter = $_SESSION["filter"];
        }
        audit_log('Ran report ' . $title);

    } else {
        echo '<title>Mailwatch for Mailscanner - ' . $title . '</title>' . "\n";
        echo '<link rel="StyleSheet" type="text/css" href="style.css">' . "\n";
    }

    if ($refresh > 0) {
        echo '<meta http-equiv="refresh" content="' . $refresh . '">' . "\n";
    }

    if (isset($_GET['id'])) {
        $message_id = $_GET['id'];
    } else {
        $message_id = " ";
    }
    $message_id = safe_value($message_id);
    $message_id = htmlentities($message_id);
    $message_id = trim($message_id, " ");
    echo '</head>' . "\n";
    echo '<body onload="updateClock(); setInterval(\'updateClock()\', 1000 )">' . "\n";
    echo '<table border="0" cellpadding="5" width="100%">' . "\n";
    echo '<tr>' . "\n";
    echo '<td>' . "\n";
    echo '<table border="0" cellpadding="0" cellspacing="0">' . "\n";
    echo '<tr>' . "\n";
    echo '<td align="left"><a href="./index.php"><img src="./images/mailwatch-logo.png" alt="MailWatch for MailScanner"></a></td>' . "\n";
    echo '</tr>' . "\n";
    echo '<tr>' . "\n";
    echo '<td valign="bottom" align="left" class="jump">' . "\n";
    echo '<form action="./detail.php">' . "\n";
    echo '<p>Jump to message:<input type="text" name="id" value="' . $message_id . '"></p>' . "\n";
    echo '</form>' . "\n";
    echo '</table>' . "\n";
    echo '<table cellspacing="1" class="mail">' . "\n";
    echo '<tr><td class="heading" align="center">Current User</td><td class="heading" align="center">Current Sytem Time</td></tr>' . "\n";
    echo '<tr><td>' . $_SESSION['fullname'] . '</td><td><span id="clock">&nbsp;</span></td></tr>' . "\n";
    echo '</table>' . "\n";
    echo '</td>' . "\n";

    echo '<td align="left" valign="top">' . "\n";
    echo '   <table border="0" cellpadding="1" cellspacing="1" class="mail">' . "\n";
    echo '    <tr> <th colspan="2">Color Codes</th> </tr>' . "\n";
    echo '    <tr> <td>Bad Content/Infected</TD> <td class="infected"></TD> </TR>' . "\n";
    echo '    <tr> <td>Spam</td> <td class="spam"></td> </tr>' . "\n";
    echo '    <tr> <td>High Spam</td> <td class="highspam"></td> </tr>' . "\n";
    echo '    <tr> <td>MCP</td> <td class="mcp"></td> </tr>' . "\n";
    echo '    <tr> <td>High MCP</td><td class="highmcp"></td></tr>' . "\n";
    echo '    <tr> <td>Whitelisted</td> <td class="whitelisted"></td> </tr>' . "\n";
    echo '    <tr> <td>Blacklisted</td> <td class="blacklisted"></td> </tr>' . "\n";
    echo '	  <tr> <td>Not Scanned</td> <td class="notscanned"></td> </tr>' . "\n";
    echo '    <tr> <td>Clean</td> <td></td> </tr>' . "\n";
    echo '   </table>' . "\n";
    echo '  </td>' . "\n";

    if (!DISTRIBUTED_SETUP && ($_SESSION['user_type'] == 'A' || $_SESSION['user_type'] == 'D')) {
        echo '  <td align="center" valign="top">' . "\n";

        // Status table
        echo '   <table border="0" cellpadding="1" cellspacing="1" class="mail" width="200">' . "\n";
        echo '    <tr><th colspan="3">Status</th></tr>' . "\n";

        // MailScanner running?
        if (!DISTRIBUTED_SETUP) {
            $no = '<span class="yes">&nbsp;NO&nbsp;</span>' . "\n";
            $yes = '<span class="no">&nbsp;YES&nbsp;</span>' . "\n";
            exec("ps ax | grep MailScanner | grep -v grep", $output);
            if (count($output) > 0) {
                $running = $yes;
                $procs = count($output) - 1 . " children";
            } else {
                $running = $no;
                $procs = count($output) . " proc(s)";
            }
            echo '     <tr><td>MailScanner:</td><td align="center">' . $running . '</td><td align="right">' . $procs . '</td></tr>' . "\n";

            // is MTA running
            $mta = get_conf_var('mta');
            exec("ps ax | grep $mta | grep -v grep | grep -v php", $output);
            if (count($output) > 0) {
                $running = $yes;
            } else {
                $running = $no;
            }
            $procs = count($output) . " proc(s)";
            echo '    <tr><td>' . ucwords(
                    $mta
                ) . ':</td><td align="center">' . $running . '</td><td align="right">' . $procs . '</td></tr>' . "\n";
        }

        // Load average
        if (file_exists("/proc/loadavg") && !DISTRIBUTED_SETUP) {
            $loadavg = file("/proc/loadavg");
            $loadavg = explode(" ", $loadavg[0]);
            $la_1m = $loadavg[0];
            $la_5m = $loadavg[1];
            $la_15m = $loadavg[2];
            echo '    <tr><td>Load Average:</td><td align="right" colspan="2"><table width="100%" class="mail" cellpadding="0" cellspacing="0"><tr><td align="center">' . $la_1m . '</td><td align="center">' . $la_5m . '</td><td align="center">' . $la_15m . '</td></tr></table></td>' . "\n";
        }

        // Mail Queues display
        $incomingdir = get_conf_var('incomingqueuedir');
        $outgoingdir = get_conf_var('outgoingqueuedir');

        // Display the MTA queue
        // Postfix if mta = postfix
        if ($mta == 'postfix' && ($_SESSION['user_type'] == 'A')) {
            if (is_readable($incomingdir) && is_readable($outgoingdir)) {
                $inq = postfixinq();
                $outq = postfixallq() - $inq;
                echo '    <tr><td colspan="3" class="heading" align="center">Mail Queues</td></tr>' . "\n";
                echo '    <tr><td colspan="2"><a href="postfixmailq.php">Inbound:</a></td><td align="right">' . $inq . '</td>' . "\n";
                echo '    <tr><td colspan="2"><a href="postfixmailq.php">Outbound:</a></td><td align="right">' . $outq . '</td>' . "\n";
            } else {
                echo '    <tr><td colspan="3">Please verify read permissions on ' . $incomingdir . ' and ' . $outgoingdir . '</td></tr>' . "\n";
            }
            // else use mailq which is for sendmail and exim
        } elseif (MAILQ && ($_SESSION['user_type'] == 'A')) {
            $inq = mysql_result(dbquery("SELECT COUNT(*) FROM inq WHERE " . $_SESSION['global_filter']), 0);
            $outq = mysql_result(dbquery("SELECT COUNT(*) FROM outq WHERE " . $_SESSION['global_filter']), 0);
            echo '    <tr><td colspan="3" class="heading" align="center">Mail Queues</td></tr>' . "\n";
            echo '    <tr><td colspan="2"><a href="mailq.php?queue=inq">Inbound:</a></td><td align="right">' . $inq . '</td>' . "\n";
            echo '    <tr><td colspan="2"><a href="mailq.php?queue=outq">Outbound:</a></td><td align="right">' . $outq . '</td>' . "\n";
        }

        // drive display
        if ($_SESSION['user_type'] == 'A') {
            echo '    <tr><td colspan="3" class="heading" align="center">Free Drive Space</td></tr>' . "\n";
            function formatSize($size, $precision = 2)
            {
                $base = log($size) / log(1024);
                $suffixes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

                return round(pow(1024, $base - floor($base)), $precision) . $suffixes[(int)floor($base)];
            }

            function get_disks()
            {
                $disks = array();
                if (php_uname('s') == 'Windows NT') {
                    // windows
                    $disks = `fsutil fsinfo drives`;
                    $disks = str_word_count($disks, 1);
                    //TODO: won't work on non english installation, we need to find an universal command
                    if ($disks[0] != 'Drives') {
                        return '';
                    }
                    unset($disks[0]);
                    foreach ($disks as $key => $disk) {
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
                    $temp_drive = array();
                    if (is_file('/proc/mounts')) {
                        $mounted_fs = file("/proc/mounts");
                        foreach ($mounted_fs as $fs_row) {
                            $drive = preg_split("/[\s]+/", $fs_row);
                            if ((substr($drive[0], 0, 5) == '/dev/') && (stripos($drive[1], '/chroot/') === false)) {
                                $temp_drive['device'] = $drive[0];
                                $temp_drive['mountpoint'] = $drive[1];
                                $disks[] = $temp_drive;
                                unset($temp_drive);
                            }
                            // TODO: list nfs mount (and other relevant fs type) in $disks[]
                        }
                    } else {
                        // fallback to mount command
                        $data = `mount`;
                        $data = explode("\n", $data);
                        foreach ($data as $disk) {
                            $drive = preg_split("/[\s]+/", $disk);
                            if ((substr($drive[0], 0, 5) == '/dev/') && (stripos($drive[2], '/chroot/') === false)) {
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

            foreach (get_disks() as $disk) {
                $free_space = disk_free_space($disk['mountpoint']);
                $total_space = disk_total_space($disk['mountpoint']);
                if (round($free_space / $total_space, 2) <= 0.1) {
                    $percent = '<span style="color:red">';
                } else {
                    $percent = '<span>';
                }
                $percent .= ' [';
                $percent .= round($free_space / $total_space, 2) * 100;
                $percent .= '%] ';
                $percent .= '</span>';
                echo '    <tr><td>' . $disk['mountpoint'] . '</td><td colspan="2" align="right">' . formatSize($free_space) . $percent . '</td>' . "\n";
            }


        }
        echo '  </table>' . "\n";
        echo '  </td>' . "\n";
    }

    echo '<td align="center" valign="top">' . "\n";

    $sql = "
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
    AND (isspam=0 OR isspam IS NULL)
    AND (ishighspam=0 OR ishighspam IS NULL)
   THEN 1 ELSE 0 END
  ) AS blockedfiles,
  ROUND((
   SUM(
    CASE WHEN
     nameinfected>0
     AND (virusinfected=0 OR virusinfected IS NULL)
     AND (otherinfected=0 OR otherinfected IS NULL)
     AND (isspam=0 OR isspam IS NULL)
     AND (ishighspam=0 OR ishighspam IS NULL)
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
  " . $_SESSION['global_filter'] . "
";

    $sth = dbquery($sql);
    while ($row = mysql_fetch_object($sth)) {
        echo '<table border="0" cellpadding="1" cellspacing="1" class="mail" width="200">' . "\n";
        echo ' <tr><th align="center" colspan="3">Today\'s Totals</th></tr>' . "\n";
        echo ' <tr><td>Processed:</td><td align="right">' . number_format(
                $row->processed
            ) . '</td><td align="right">' . format_mail_size(
                $row->size
            ) . '</td></tr>' . "\n";
        echo ' <tr><td>Clean:</td><td align="right">' . number_format(
                $row->clean
            ) . '</td><td align="right">' . $row->cleanpercent . '%</td></tr>' . "\n";
        echo ' <tr><td>Viruses:</td><td align="right">' . number_format(
                $row->viruses
            ) . '</td><td align="right">' . $row->viruspercent . '%</tr>' . "\n";
        echo ' <tr><td>Top Virus:</td><td colspan="2" align="right" style="white-space:nowrap">' . return_todays_top_virus() . '</td></tr>' . "\n";
        echo ' <tr><td>Blocked files:</td><td align="right">' . number_format(
                $row->blockedfiles
            ) . '</td><td align="right">' . $row->blockedfilespercent . '%</td></tr>' . "\n";
        echo ' <tr><td>Others:</td><td align="right">' . number_format(
                $row->otherinfected
            ) . '</td><td align="right">' . $row->otherinfectedpercent . '%</td></tr>' . "\n";
        echo ' <tr><td>Spam:</td><td align="right">' . number_format(
                $row->spam
            ) . '</td><td align="right">' . $row->spampercent . '%</td></tr>' . "\n";
        echo ' <tr><td style="white-space:nowrap">High Scoring Spam:</td><td align="right">' . number_format(
                $row->highspam
            ) . '</td><td align="right">' . $row->highspampercent . '%</td></tr>' . "\n";
        echo ' <tr><td>MCP:</td><td align="right">' . number_format(
                $row->mcp
            ) . '</td><td align="right">' . $row->mcppercent . '%</td></tr>' . "\n";
        echo ' <tr><td style="white-space:nowrap">High Scoring MCP:</td><td align="right">' . number_format(
                $row->highmcp
            ) . '</td><td align="right">' . $row->highmcppercent . '%</td></tr>' . "\n";
        echo '</table>' . "\n";
    }

    // Navigation links - put them into an array to allow them to be switched
    // on or off as necessary and to allow for the table widths to be calculated.
    $nav = array();
    $nav['status.php'] = "Recent Messages";
    if (LISTS) {
        $nav['lists.php'] = "Lists";
    }
    if (!DISTRIBUTED_SETUP) {
        $nav['quarantine.php'] = "Quarantine";
    }
    $nav['reports.php'] = "Reports";
    $nav['other.php'] = "Tools/Links";

    if (SHOW_SFVERSION == true) {
        if ($_SESSION['user_type'] === 'A') {
            $nav['sf_version.php'] = "Software Versions";
        }
    }

    if (SHOW_DOC == true) {
        $nav['docs.php'] = "Documentation";
    }
    $nav['logout.php'] = "Logout";
    //$table_width = round(100 / count($nav));
    
    //Navigation table
    echo '  </td>' . "\n";
    echo ' </tr>' . "\n";
    echo '<tr>' . "\n";
    echo '<td colspan="4">' . "\n";

    echo '<ul id="menu" class="yellow">' . "\n";

    // Display the different words
    foreach ($nav as $url => $desc) {
        $active_url = "" . MAILWATCH_HOME . "/" . $url . "";
        if ($_SERVER['SCRIPT_FILENAME'] == $active_url) {
            echo "<li class=\"active\"><a href=\"$url\">$desc</a></li>\n";
        } else {
            echo "<li><a href=\"$url\">$desc</a></li>\n";
        }
    }

    echo '
 </ul>
 </td>
 </tr>
 <tr>
  <td colspan="4">';

    if ($report) {
        $return_items = $filter;
    } else {
        $return_items = $refresh;
    }
    return $return_items;
}

function java_time()
{
    echo '
function updateClock ( )
{
  var currentTime = new Date ( );

  var currentHours = currentTime.getHours ( );
  var currentMinutes = currentTime.getMinutes ( );
  var currentSeconds = currentTime.getSeconds ( );

  // Pad the minutes and seconds with leading zeros, if required
  currentMinutes = ( currentMinutes < 10 ? "0" : "" ) + currentMinutes;
  currentSeconds = ( currentSeconds < 10 ? "0" : "" ) + currentSeconds;

  // Choose either "AM" or "PM" as appropriate
  var timeOfDay = ( currentHours < 12 ) ? "AM" : "PM";
';

    if (TIME_FORMAT == '%h:%i:%s') {
        echo '
  // Convert the hours component to 12-hour format if needed
  currentHours = ( currentHours > 12 ) ? currentHours - 12 : currentHours;

  // Convert an hours component of "0" to "12"
  currentHours = ( currentHours == 0 ) ? 12 : currentHours;';
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

function row_highandclick()
{
    echo '
  function ChangeColor(tableRow, highLight)
    {
    if (highLight)
    {
      tableRow.style.backgroundColor = \'#dcfac9\';
    }
	else
	{
		tableRow.sytle.backgroundColor = \'white\';
	}
  }

  
    function DoNav(theUrl)
  {
  document.location.href = theUrl;
  }';
}

function html_end($footer = "")
{
    echo '</td>' . "\n";
    echo '</tr>' . "\n";
    echo '</table>' . "\n";
    echo $footer;
    echo '<p class="center" style="font-size:13px"><i>' . "\n";
    page_creation_timer();
    echo '</i></p>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

function dbconn()
{
    $link = mysql_connect(DB_HOST, DB_USER, DB_PASS, false, 128)
    or die ("Could not connect to database: " . mysql_error());
    mysql_select_db(DB_NAME) or die("Could not select db: " . mysql_error());

    return $link;
}

function dbclose()
{
    return mysql_close();
}

function dbquery($sql)
{
    dbconn();
    if (DEBUG && headers_sent() && preg_match('/\bselect\b/i', $sql)) {
        echo "<!--\n\n";
        $dbg_sql = "EXPLAIN " . $sql;
        echo "SQL:\n\n$sql\n\n";
        $result = mysql_query($dbg_sql) or die("Error executing query: " . mysql_errno() . " - " . mysql_error());
        $fields = mysql_num_fields($result);
        while ($row = mysql_fetch_row($result)) {
            for ($f = 0; $f < $fields; $f++) {
                echo mysql_field_name($result, $f) . ": " . $row[$f] . "\n";
            }
        }
        //dbtable("SHOW STATUS");
        echo "\n-->\n\n";
    }
    $result = mysql_query($sql) or die("<B>Error executing query: </B><BR><BR>" . mysql_errno() . ": " . mysql_error() . "<BR><BR><B>SQL:</B><BR><PRE>$sql</PRE>");
    return $result;
}

function quote_smart($value)
{
    dbconn();
    if (get_magic_quotes_gpc()) {
        $value = stripslashes($value);
    }
    $value = "'" . mysql_real_escape_string($value) . "'";
    return $value;
}

function safe_value($value)
{
    dbconn();
    if (get_magic_quotes_gpc()) {
        $value = stripslashes($value);
    }
    $value = mysql_real_escape_string($value);
    return $value;
}

function sa_autolearn($spamreport)
{
    switch (true) {
        case(preg_match('/autolearn=spam/', $spamreport)):
            return 'spam';
        case(preg_match('/autolearn=not spam/', $spamreport)):
            return 'not spam';
        default:
            return false;
    }
}

function format_spam_report($spamreport)
{
    // Run regex against the MailScanner spamreport picking out the (score=xx, required x, RULES...)
    if (preg_match('/\s\((.+?)\)/i', $spamreport, $sa_rules)) {
        // Get rid of the first match from the array
        array_shift($sa_rules);
        // Split the array
        $sa_rules = explode(", ", $sa_rules[0]);
        // Check to make sure a check was actually run
        if ($sa_rules[0] == "Message larger than max testing size" || $sa_rules[0] == "timed out") {
            return $sa_rules[0];
        }
        // Get rid of the 'score=', 'required' and 'autolearn=' lines
        foreach (array('cached', 'score=', 'required', 'autolearn=') as $val) {
            if (preg_match("/$val/", $sa_rules[0])) {
                array_shift($sa_rules);
            }
        }
        $output_array = array();
        while (list($key, $val) = each($sa_rules)) {
            array_push($output_array, get_sa_rule_desc($val));
        }
        // Return the result as an html formatted string
        if (count($output_array) > 0) {
            return '<table class="sa_rules_report" cellspacing="2">' . '<tr><Th>Score</th><th>Matching Rule</th><th>Description</th></tr>' . implode(
                "\n",
                $output_array
            ) . '</table>' . "\n";
        } else {
            return $spamreport;
        }
    } else {
        // Regular expression did not match, return unmodified report instead
        return $spamreport;
    }
}

function get_sa_rule_desc($rule)
{
    // Check if SA scoring is enabled
    if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
        $rule = $regs[1];
        $rule_score = $regs[2];
    } else {
        $rule_score = "";
    }
    $result = dbquery("SELECT rule, rule_desc FROM sa_rules WHERE rule='$rule'");
    $row = mysql_fetch_object($result);
    if ($row && $row->rule && $row->rule_desc) {
        return ('<tr><td style="text-align:left;">' . $rule_score . '</td><td class="rule_desc">' . $row->rule . '</td><td>' . $row->rule_desc . '</td></tr>' . "\n");
    } else {
        return "<tr><td>$rule_score<td>$rule</td><td>&nbsp;</td></tr>";
    }
}

function return_sa_rule_desc($rule)
{
    $result = dbquery("SELECT rule, rule_desc FROM sa_rules WHERE rule='$rule'");
    $row = mysql_fetch_object($result);
    if ($row) {
        return $row->rule_desc;
    }

    return false;
}

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
        $sa_rules = explode(", ", $sa_rules[0]);
        // Check to make sure a check was actually run
        if ($sa_rules[0] == "Message larger than max testing size" || $sa_rules[0] == "timed out") {
            return $sa_rules[0];
        }
        // Get rid of the 'score=', 'required' and 'autolearn=' lines
        foreach (array('score=', 'required', 'autolearn=') as $val) {
            if (preg_match("/$val/", $sa_rules[0])) {
                array_shift($sa_rules);
            }
        }
        $output_array = array();
        while (list($key, $val) = each($sa_rules)) {
            array_push($output_array, get_mcp_rule_desc($val));
        }
        // Return the result as an html formatted string
        if (count($output_array) > 0) {
            return '<table class="sa_rules_report" cellspacing="2" width="100%">"."<tr><th>Score</th><th>Matching Rule</th><th>Description</th></tr>' . implode(
                "\n",
                $output_array
            ) . '</table>' . "\n";
        } else {
            return $mcpreport;
        }
    } else {
        // Regular expression did not match, return unmodified report instead
        return $mcpreport;
    }
}

function get_mcp_rule_desc($rule)
{
    // Check if SA scoring is enabled
    if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
        $rule = $regs[1];
        $rule_score = $regs[2];
    } else {
        $rule_score = "";
    }
    $result = dbquery("SELECT rule, rule_desc FROM mcp_rules WHERE rule='$rule'");
    $row = mysql_fetch_object($result);
    if ($row && $row->rule && $row->rule_desc) {
        return ('<tr><td align="left">' . $rule_score . '</td><td style="width:200;">' . $row->rule . '</td><td>' . $row->rule_desc . '</td></tr>' . "\n");
    } else {
        return '<tr><td>' . $rule_score . '<td>' . $rule . '</td><td>&nbsp;</td></tr>' . "\n";
    }
}

function return_mcp_rule_desc($rule)
{
    $result = dbquery("SELECT rule, rule_desc FROM mcp_rules WHERE rule='$rule'");
    $row = mysql_fetch_object($result);
    if ($row) {
        return $row->rule_desc;
    }

    return false;
}

function return_todays_top_virus()
{
    $sql = "
SELECT
 report
FROM
 maillog
WHERE
 virusinfected>0
AND
 date = CURRENT_DATE()
";
    $result = dbquery($sql);
    $virus_array = array();
    while ($row = mysql_fetch_object($result)) {
        if (preg_match(VIRUS_REGEX, $row->report, $virus_reports)) {
            $virus = return_virus_link($virus_reports[2]);
            if (!isset($virus_array[$virus])) {
                $virus_array[$virus] = 1;
            } else {
                $virus_array[$virus]++;
            }
        }
    }
    arsort($virus_array);
    reset($virus_array);
    // Get the topmost entry from the array
    if ((list($key, $val) = each($virus_array)) != "") {
        // Check and make sure there first placed isn't tied!
        $saved_key = $key;
        $saved_val = $val;
        list($key, $val) = each($virus_array);
        if ($val != $saved_val) {
            return $saved_key;
        } else {
            // Tied first place - return none
            // FIXME: Should return all top viruses
            return "None";
        }
    } else {
        return "None";
    }
}

function format_mail_size($size_in_bytes, $decimal_places = 1)
{
    // Setup common measurements
    $kb = 1024; // Kilobyte
    $mb = 1024 * $kb; // Megabyte
    $gb = 1024 * $mb; // Gigabyte
    $tb = 1024 * $gb; // Terabyte
    if ($size_in_bytes < $kb) {
        return $size_in_bytes . "b";
    } else {
        if ($size_in_bytes < $mb) {
            return round($size_in_bytes / $kb, $decimal_places) . "Kb";
        } else {
            if ($size_in_bytes < $gb) {
                return round($size_in_bytes / $mb, $decimal_places) . "Mb";
            } else {
                if ($size_in_bytes < $tb) {
                    return round($size_in_bytes / $gb, $decimal_places) . "Gb";
                } else {
                    return round($size_in_bytes / $tb, $decimal_places) . "Tb";
                }
            }
        }
    }
}

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
    $largest = array_pop($temp);

    // Calculate the correct display size for the average value
    if ($average < $kb) {
        $info_out['formula'] = 1;
        $info_out['shortdesc'] = "b";
        $info_out['longdesc'] = "Bytes";
    } else {
        if ($average < $mb) {
            $info_out['formula'] = $kb;
            $info_out['shortdesc'] = "Kb";
            $info_out['longdesc'] = "Kilobytes";
        } else {
            if ($average < $gb) {
                $info_out['formula'] = $mb;
                $info_out['shortdesc'] = "Mb";
                $info_out['longdesc'] = "Megabytes";
            } else {
                if ($average < $tb) {
                    $info_out['formula'] = $gb;
                    $info_out['shortdesc'] = "Gb";
                    $info_out['longdesc'] = "Gigabytes";
                } else {
                    $info_out['formula'] = $tb;
                    $info_out['shortdesc'] = "Tb";
                    $info_out['longdesc'] = "Terabytes";
                }
            }
        }
    }

    // Modify the original data accordingly
    for ($i = 0; $i < sizeof($data_in); $i++) {
        $data_in[$i] = $data_in[$i] / $info_out['formula'];
    }
}

function trim_output($input, $maxlen)
{
    if ($maxlen > 0 && strlen($input) >= $maxlen) {
        $output = substr($input, 0, $maxlen) . "...";
        return $output;
    } else {
        return $input;
    }
}

function get_default_ruleset_value($file)
{
    $fh = fopen($file, 'r') or die("Cannot open ruleset file $file");
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, filesize($file)));
        if (preg_match('/^([^#]\S+:)\s+(\S+)\s+([^#]\S+)/', $line, $regs)) {
            if ($regs[2] == 'default') {
                return $regs[3];
            }
        }
    }
    fclose($fh);
    return false;
}

function get_conf_var($name)
{
    if (DISTRIBUTED_SETUP) {
        return false;
    }
    $conf_dir = get_conf_include_folder();
    $MailScanner_conf_file = '' . MS_CONFIG_DIR . 'MailScanner.conf';
    //$array_output = array();

    $array_output1 = parse_conf_file($MailScanner_conf_file);
    $array_output2 = parse_conf_dir($conf_dir);

    if (is_array($array_output2)) {
        $array_output = array_merge($array_output1, $array_output2);
    } else {
        $array_output = $array_output1;
    }
    //echo '<pre>'; var_dump($array_output); echo '</pre>';
    foreach ($array_output as $parameter_name => $parameter_value) {
        $parameter_name = preg_replace('/ */', '', $parameter_name);

        if ((strtolower($parameter_name)) == (strtolower($name))) {
            if (is_file($parameter_value)) {
                return read_ruleset_default($parameter_value);
            } else {
                return $parameter_value;
            }
        }
    }

    die("Cannot find configuration value: $name in $MailScanner_conf_file\n");
}

function parse_conf_dir($conf_dir)
{
    $array_output1 = array();
    if ($dh = opendir($conf_dir)) {
        while (($file = readdir($dh)) !== false) {
            // remove the . and .. so that it doesn't throw an error when parsing files
            if ($file !== "." && $file !== "..") {
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
    return ($array_output1);
}

function get_conf_truefalse($name)
{
    if (DISTRIBUTED_SETUP) {
        return true;
    }

    $conf_dir = get_conf_include_folder();
    $MailScanner_conf_file = MS_CONFIG_DIR . 'MailScanner.conf';

    $array_output1 = parse_conf_file($MailScanner_conf_file);
    $array_output2 = parse_conf_dir($conf_dir);

    if (is_array($array_output2)) {
        $array_output = array_merge($array_output1, $array_output2);
    } else {
        $array_output = $array_output1;
    }
    foreach ($array_output as $parameter_name => $parameter_value) {
        $parameter_name = preg_replace('/ */', '', $parameter_name);

        if ((strtolower($parameter_name)) == (strtolower($name))) {
            // Is it a ruleset?
            if (is_readable($parameter_value)) {
                $parameter_value = get_default_ruleset_value($parameter_value);
            }
            $parameter_value = strtolower($parameter_value);
            switch ($parameter_value) {
                case "yes":
                case "1":
                    return true;
                case "no":
                case "0":
                    return false;
                default:
                    return false;
            }
        }
    }

    return false;
}

function get_conf_include_folder()
{
    $name = 'include';
    if (DISTRIBUTED_SETUP) {
        return false;
    }

    $msconfig = MS_CONFIG_DIR . "MailScanner.conf";
    $fh = fopen($msconfig, 'r')
    or die("Cannot open MailScanner configuration file");
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, filesize($msconfig)));
        //if (preg_match('/^([^#].+)\s([^#].+)/', $line, $regs)) {
        if (preg_match('/^(?P<name>[^#].+)\s(?P<value>[^#].+)/', $line, $regs)) {
            $regs['name'] = preg_replace('/ */', '', $regs['name']);
            $regs['name'] = preg_replace('/=/', '', $regs['name']);
            //var_dump($line, $regs);
            // Strip trailing comments
            $regs['value'] = preg_replace("/\*/", "", $regs['value']);
            // store %var% variables
            if (preg_match("/%.+%/", $regs['name'])) {
                $var[$regs['name']] = $regs['value'];
            }
            // expand %var% variables
            if (preg_match("/(%.+%)/", $regs['value'], $match)) {
                $regs['value'] = preg_replace("/%.+%/", $var[$match[1]], $regs['value']);
            }
            if ((strtolower($regs[1])) == (strtolower($name))) {
                fclose($fh) or die($php_errormsg);
                if (is_file($regs['value'])) {
                    return read_ruleset_default($regs['value']);
                } else {
                    return $regs['value'];
                }
            }
        }
    }
    fclose($fh);
    die("Cannot find configuration value: $name in $msconfig\n");
}

// Parse conf files
function parse_conf_file($name)
{
    $array_output = array();
    $var = array();
    // open each file and read it
    //$fh = fopen($name . $file, 'r')
    $fh = fopen($name, 'r') or die("Cannot open MailScanner configuration file");
    while (!feof($fh)) {

        // read each line to the $line varable
        $line = rtrim(fgets($fh, 4096));

        //echo "line: ".$line."\n"; // only use for troubleshooting lines

        // find all lines that match
        if (preg_match("/^(?P<name>[^#].+[^\s*$])\s*=\s*(?P<value>[^#]*)/", $line, $regs)) {

            // Strip trailing comments
            $regs['value'] = preg_replace("/#.*$/", "", $regs['value']);

            // store %var% variables
            if (preg_match("/%.+%/", $regs['name'])) {
                $var[$regs['name']] = $regs['value'];
            }

            // expand %var% variables
            if (preg_match("/(%.+%)/", $regs['value'], $match)) {
                $regs['value'] = preg_replace("/%.+%/", $var[$match[1]], $regs['value']);
            }

            // Remove any html entities from the code
            $key = htmlentities($regs['name']);
            $string = htmlentities($regs['value']);

            // Stuff all of the data to an array
            $array_output[$key] = $string;
        }
    }
    fclose($fh) or die($php_errormsg);
    unset($fh);

    return $array_output;
}

function get_primary_scanner()
{
    // Might be more than one scanner defined - pick the first as the primary
    $scanners = explode(" ", get_conf_var('VirusScanners'));
    return $scanners[0];
}

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
            $format = preg_replace("/%y/", $y, $format);
            $format = preg_replace("/%m/", $m, $format);
            $format = preg_replace("/%d/", $d, $format);
            return $format;
    }
}

function subtract_get_vars($preserve)
{
    if (is_array($_GET)) {
        foreach ($_GET as $k => $v) {
            if (strtolower($k) !== strtolower($preserve)) {
                $output[] = "$k=$v";
            }
        }
        if (isset($output) && is_array($output)) {
            $output = join('&amp;', $output);
            return '&amp;' . $output;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function subtract_multi_get_vars($preserve)
{
    if (is_array($_GET)) {
        foreach ($_GET as $k => $v) {
            if (!in_array($k, $preserve)) {
                $output[] = "$k=$v";
            }
        }
        if (isset($output) && is_array($output)) {
            $output = join('&amp;', $output);
            return '&amp;' . $output;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function db_colorised_table($sql, $table_heading = false, $pager = false, $order = false, $operations = false)
{
    require_once('Mail/mimeDecode.php');

    // Ordering
    $orderby = null;
    $orderdir = '';
    if (isset($_GET['orderby'])) {
        $orderby = $_GET['orderby'];
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
            $p = "ORDER BY\n  " . $orderby . ' ' . $orderdir . ',' . substr($p, (strlen('ORDER BY') + 2));
            $p = substr($sql, 0, strpos($sql, 'ORDER BY')) . $p;
            $sql = $p;
        } else {
            // No existing ORDER BY - disable feature
            $order = false;
        }
    }

    if ($pager) {
        require_once('Pager/Pager.php');
        if (isset($_GET['offset'])) {
            $from = intval($_GET['offset']);
        } else {
            $from = 0;
        }

        // Remove any ORDER BY clauses as this will slow the count considerably
        if ($pos = strpos($sql, "ORDER BY")) {
            $sqlcount = substr($sql, 0, $pos);
        }

        // Count the number of rows that would be returned by the query
        $sqlcount = "SELECT COUNT(*) " . strstr($sqlcount, "FROM");
        $rows = mysql_result(dbquery($sqlcount), 0);

        // Build the pager data
        $pager_options = array(
            'mode' => 'Sliding',
            'perPage' => MAX_RESULTS,
            'delta' => 2,
            'totalItems' => $rows,
        );
        $pager = @Pager::factory($pager_options);

        //then we fetch the relevant records for the current page
        list($from, $to) = $pager->getOffsetByPageId();

        echo '<table cellspacing="1" class="mail" >
    <tr>
   <th colspan="5">Displaying page ' . $pager->getCurrentPageID() . ' of ' . $pager->numPages() . ' - Records ' . $from . ' to ' . $to . ' of ' . $pager->numItems() . '</th>
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
 <td colspan="4">';


        // Re-run the original query and limit the rows
        $limit = $from - 1;
        $sql .= " LIMIT $limit," . MAX_RESULTS;
        $sth = dbquery($sql);
        $rows = mysql_num_rows($sth);
        $fields = mysql_num_fields($sth);
        // Account for extra operations column
        if ($operations != false) {
            $fields++;
        }

    } else {
        $sth = dbquery($sql);
        $rows = mysql_num_rows($sth);
        $fields = mysql_num_fields($sth);
        // Account for extra operations column
        if ($operations != false) {
            $fields++;
        }
    }

    if ($rows > 0) {
        if ($operations != false) {
            // Start form for operations
            echo '<form name="operations" action="./do_message_ops.php" method="POST">' . "\n";
        }
        echo '<table cellspacing="1" width="100%" class="mail">' . "\n";
        // Work out which columns to display
        for ($f = 0; $f < $fields; $f++) {
            if ($f == 0 and $operations != false) {
                // Set up display for operations form elements
                $display[$f] = true;
                $orderable[$f] = false;
                // Set it up not to wrap - tricky way to leach onto the align field
                $align[$f] = 'center" style="white-space:nowrap';
                $fieldname[$f] = 'Ops<br><a href="javascript:SetRadios(\'S\')">S</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'H\')">H</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'F\')">F</a>&nbsp;&nbsp;&nbsp;<a href="javascript:SetRadios(\'R\')">R</a>';
                continue;
            }
            $display[$f] = true;
            $orderable[$f] = true;
            $align[$f] = false;
            // Set up the mysql column to account for operations
            if ($operations != false) {
                $colnum = $f - 1;
            } else {
                $colnum = $f;
            }
            switch ($fieldname[$f] = mysql_field_name($sth, $colnum)) {
                case 'host':
                    $fieldname[$f] = "Host";
                    if (DISTRIBUTED_SETUP) {
                        $display[$f] = true;
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'timestamp':
                    $fieldname[$f] = "Date/Time";
                    $align[$f] = "center";
                    break;
                case 'datetime':
                    $fieldname[$f] = "Date/Time";
                    $align[$f] = "center";
                    break;
                case 'id':
                    $fieldname[$f] = "ID";
                    $orderable[$f] = false;
                    $align[$f] = "center";
                    break;
                case 'id2':
                    $fieldname[$f] = "#";
                    $orderable[$f] = false;
                    $align[$f] = "center";
                    break;
                case 'size':
                    $fieldname[$f] = "Size";
                    $align[$f] = "right";
                    break;
                case 'from_address':
                    $fieldname[$f] = "From";
                    break;
                case 'to_address':
                    $fieldname[$f] = "To";
                    break;
                case 'subject':
                    $fieldname[$f] = "Subject";
                    break;
                case 'clientip':
                    if (defined('DISPLAY_IP') && DISPLAY_IP) {
                        $fieldname[$f]= "Client IP";
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
                    $fieldname[$f] = 'Host';
                    $display[$f] = true;
                    break;
                case 'date':
                    $fieldname[$f] = 'Date';
                    break;
                case 'time':
                    $fieldname[$f] = 'Time';
                    break;
                case 'headers':
                    $display[$f] = false;
                    break;
                case 'sascore':
                    if (get_conf_truefalse('UseSpamAssassin')) {
                        $fieldname[$f] = "SA Score";
                        $align[$f] = "right";
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'mcpsascore':
                    if (get_conf_truefalse('MCPChecks')) {
                        $fieldname[$f] = "MCP Score";
                        $align[$f] = "right";
                    } else {
                        $display[$f] = false;
                    }
                    break;
                case 'status':
                    $fieldname[$f] = "Status";
                    $orderable[$f] = false;
                    break;
                case 'message':
                    $fieldname[$f] = "Message";
                    break;
                case 'attempts':
                    $fieldname[$f] = "Tries";
                    $align[$f] = "right";
                    break;
                case 'lastattempt':
                    $fieldname[$f] = "Last";
                    $align[$f] = "right";
                    break;
            }
        }
        // Table heading
        if (isset($table_heading) && $table_heading != "") {
            // Work out how many columns are going to be displayed
            $column_headings = 0;
            for ($f = 0; $f < $fields; $f++) {
                if ($display[$f]) {
                    $column_headings++;
                }
            }
            echo ' <tr>' . "\n";
            echo '  <th colspan="' . $column_headings . '">' . $table_heading . '</th>' . "\n";
            echo ' </tr>' . "\n";
        }
        // Column headings
        echo '<tr>' . "\n";
        for ($f = 0; $f < $fields; $f++) {
            if ($display[$f]) {
                if ($order && $orderable[$f]) {
                    // Set up the mysql column to account for operations
                    if ($operations != false) {
                        $colnum = $f - 1;
                    } else {
                        $colnum = $f;
                    }
                    echo "  <th>\n";
                    echo "  $fieldname[$f] (<a href=\"?orderby=" . mysql_field_name(
                            $sth,
                            $colnum
                        ) . "&amp;orderdir=a" . subtract_multi_get_vars(
                            array('orderby', 'orderdir')
                        ) . "\">A</a>/<a href=\"?orderby=" . mysql_field_name(
                            $sth,
                            $colnum
                        ) . "&amp;orderdir=d" . subtract_multi_get_vars(array('orderby', 'orderdir')) . "\">D</a>)\n";
                    echo "  </th>\n";
                } else {
                    echo '  <th>' . $fieldname[$f] . '</th>' . "\n";
                }
            }
        }
        echo ' </tr>' . "\n";
        // Rows
        $JsFunc = '';
        for ($r = 0; $r < $rows; $r++) {
            $row = mysql_fetch_row($sth);
            if ($operations != false) {
                // Prepend operations elements - later on, replace REPLACEME w/ message id
                array_unshift(
                    $row,
                    "<input name=\"OPT-REPLACEME\" type=\"RADIO\" value=\"S\">&nbsp;<input name=\"OPT-REPLACEME\" type=\"RADIO\" value=\"H\">&nbsp;<input name=\"OPT-REPLACEME\" type=\"RADIO\" value=\"F\">&nbsp;<input name=\"OPT-REPLACEME\" type=\"RADIO\" value=\"R\">"
                );
            }
            // Work out field colourings and mofidy the incoming data as necessary
            // and populate the generate an overall 'status' for the mail.
            $status_array = array();
            $infected = false;
            $highspam = false;
            $spam = false;
            $whitelisted = false;
            $blacklisted = false;
            $mcp = false;
            $highmcp = false;
            for ($f = 0; $f < $fields; $f++) {
                if ($operations != false) {
                    if ($f == 0) {
                        // Skip the first field if it is operations
                        continue;
                    }
                    $field = mysql_field_name($sth, $f - 1);
                } else {
                    $field = mysql_field_name($sth, $f);
                }
                switch ($field) {
                    case 'id':
                        // Store the id for later use
                        $id = $row[$f];
                        // Create a link to detail.php
                        $row[$f] = '<a href="detail.php?id=' . $row[$f] . '">' . $row[$f] . '</a>' . "\n";
                        break;
                    case 'id2':
                        // Store the id for later use
                        $id = $row[$f];
                        // Create a link to detail.php as [<link>]
                        $row[$f] = "[<a href=\"detail.php?id=$row[$f]\">&nbsp;&nbsp;</a>]";
                        break;
                    case 'from_address':
                        $row[$f] = htmlentities($row[$f]);
                        if (FROMTO_MAXLEN > 0) {
                            $row[$f] = trim_output($row[$f], FROMTO_MAXLEN);
                        }
                        break;
                    case 'to_address':
                        $row[$f] = htmlentities($row[$f]);
                        if (FROMTO_MAXLEN > 0) {
                            // Trim each address to specified size
                            $to_temp = explode(",", $row[$f]);
                            for ($t = 0; $t < count($to_temp); $t++) {
                                $to_temp[$t] = trim_output($to_temp[$t], FROMTO_MAXLEN);
                            }
                            // Return the data
                            $row[$f] = implode(",", $to_temp);
                        }
                        // Put each address on a new line
                        $row[$f] = str_replace(",", "<br>", $row[$f]);
                        break;
                    case 'subject':
                        $row[$f] = decode_header($row[$f]);
                        if (function_exists('mb_check_encoding')) {
                            if (!mb_check_encoding($row[$f], 'UTF-8')) {
                                $row[$f] = mb_convert_encoding($row[$f], 'UTF-8');
                            }
                        } else {
                            $row[$f] = utf8_encode($row[$f]);
                        }
                        $row[$f] = htmlspecialchars($row[$f]);
                        if (SUBJECT_MAXLEN > 0) {
                            $row[$f] = trim_output($row[$f], SUBJECT_MAXLEN);
                        }
                        break;
                    case 'isspam':
                        if ($row[$f] == 'Y' || $row[$f] > 0) {
                            $spam = true;
                            array_push($status_array, 'Spam');
                        }
                        break;
                    case 'ishighspam':
                        if ($row[$f] == 'Y' || $row[$f] > 0) {
                            $highspam = true;
                        }
                        break;
                    case 'ismcp':
                        if ($row[$f] == 'Y' || $row[$f] > 0) {
                            $mcp = true;
                            array_push($status_array, 'MCP');
                        }
                        break;
                    case 'ishighmcp':
                        if ($row[$f] == 'Y' || $row[$f] > 0) {
                            $highmcp = true;
                        }
                        break;
                    case 'virusinfected':
                        if ($row[$f] == 'Y' || $row[$f] > 0) {
                            $infected = true;
                            array_push($status_array, 'Virus');
                        }
                        break;
                    case 'report':
                        // IMPORTANT NOTE: for this to work correctly the 'report' field MUST
                        // appear after the 'virusinfected' field within the SQL statement.
                        if (preg_match("/VIRUS_REGEX/", $row[$f], $virus)) {
                            foreach ($status_array as $k => $v) {
                                if ($v = preg_replace('/Virus/', "Virus (" . return_virus_link($virus[2]) . ")", $v)) {
                                    $status_array[$k] = $v;
                                }
                            }
                        }
                        break;
                    case 'nameinfected':
                        if ($row[$f] == 'Y' || $row[$f] > 0) {
                            $infected = true;
                            array_push($status_array, 'Bad Content');
                        }
                        break;
                    case 'otherinfected':
                        if ($row[$f] == 'Y' || $row[$f] > 0) {
                            $infected = true;
                            array_push($status_array, 'Other');
                        }
                        break;
                    case 'size':
                        $row[$f] = format_mail_size($row[$f]);
                        break;
                    case 'spamwhitelisted':
                        if ($row[$f] == 'Y' || $row[$f] > 0) {
                            $whitelisted = true;
                            array_push($status_array, 'W/L');
                        }
                        break;
                    case 'spamblacklisted':
                        if ($row[$f] == 'Y' || $row[$f] > 0) {
                            $blacklisted = true;
                            array_push($status_array, 'B/L');
                        }
                        break;
                    case 'clienthost':
                        $hostname = gethostbyaddr($row[$f]);
                        if ($hostname == $row[$f]) {
                            $row[$f] = "(Hostname lookup failed)";
                        } else {
                            $row[$f] = $hostname;
                        }
                        break;
                    case 'status':
                        // NOTE: this should always be the last row for it to be displayed correctly
                        // Work out status
                        if (count($status_array) == 0) {
                            $status = "Clean";
                        } else {
                            $status = join("<br>", $status_array);
                        }
                        $row[$f] = $status;
                        break;
                }
            }
            // Now add the id to the operations form elements
            if ($operations != false) {
                $row[0] = str_replace("REPLACEME", $id, $row[0]);
                $JsFunc .= "  document.operations.elements[\"OPT-$id\"][val].checked = true;\n";
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
                    if (isset($fieldname['mcpsascore']) && $fieldname['mcpsascore'] != '') {
                        echo '<tr class="mcp">' . "\n";
                    } else {
                        echo '<tr >' . "\n";
                    }
                    break;
            }
            // Display the rows
            for ($f = 0; $f < $fields; $f++) {
                if ($display[$f]) {
                    if ($align[$f]) {
                        echo ' <td align="' . $align[$f] . '">' . $row[$f] . '</td>' . "\n";
                    } else {
                        echo ' <td >' . $row[$f] . '</td>' . "\n";
                    }
                }
            }
            echo ' </tr>' . "\n";
        }
        echo '</table>' . "\n";
        // Javascript function to clear radio buttons
        if ($operations != false) {
            echo '<script type="text/javascript">
   function ClearRadios() {
   e=document.operations.elements
   for(i=0; i<e.length; i++) {
   if (e[i].type=="radio") {
   e[i].checked=false;
     }
    }
   }
   function SetRadios(p) {
    var val;
    if (p == \'S\') {
     val = 0;
    } else if (p == \'H\') {
     val = 1;
    } else if (p == \'F\') {
     val = 2;
    } else if (p == \'R\') {
     val = 3;
    } else if (p == \'C\') {
     ClearRadios();
    return;
     } else {
	  return;
     }
	' . $JsFunc . '
   }
   </script>
   <p>&nbsp; <a href="javascript:SetRadios(\'S\')">S</a>
   &nbsp; <a href="javascript:SetRadios(\'H\')">H</a>
   &nbsp; <a href="javascript:SetRadios(\'F\')">F</a>
   &nbsp; <a href="javascript:SetRadios(\'R\')">R</a>
   &nbsp; or <a href="javascript:SetRadios(\'C\')">Clear</a> all</p>
   <p><input type="SUBMIT" name="SUBMIT" value="Learn"></p>
   </form>
   <p><b>S</b> = Spam &nbsp; <b>H</b> = Ham &nbsp; <b>F</b> = Forget &nbsp; <b>R</b> = Release' . "\n";
        }
        echo '<br>' . "\n";
        if ($pager) {
            require_once('Pager/Pager.php');
            if (isset($_GET['offset'])) {
                $from = intval($_GET['offset']);
            } else {
                $from = 0;
            }

            // Remove any ORDER BY clauses as this will slow the count considerably
            if ($pos = strpos($sql, "ORDER BY")) {
                $sqlcount = substr($sql, 0, $pos);
            }

            // Count the number of rows that would be returned by the query
            $sqlcount = "SELECT COUNT(*) " . strstr($sqlcount, "FROM");
            $rows = mysql_result(dbquery($sqlcount), 0);

            // Build the pager data
            $pager_options = array(
                'mode' => 'Sliding',
                'perPage' => MAX_RESULTS,
                'delta' => 2,
                'totalItems' => $rows,
            );
            $pager = @Pager::factory($pager_options);

            //then we fetch the relevant records for the current page
            list($from, $to) = $pager->getOffsetByPageId();

            echo '<table cellspacing="1" class="mail" >
    <tr>
   <th colspan="5">Displaying page ' . $pager->getCurrentPageID() . ' of ' . $pager->numPages() . ' - Records ' . $from . ' to ' . $to . ' of ' . $pager->numItems() . '</th>
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
 <td colspan="4">';
        }
    }
}

// Function to display data as a table
function dbtable($sql, $title = false, $pager = false, $operations = false)
{
    global $bg_colors;

    /*
    // Query the data
    $sth = dbquery($sql);

    // Count the number of rows in a table
    $rows = mysql_num_rows($sth);

    // Count the nubmer of fields
    $fields = mysql_num_fields($sth);
    */

    // Turn on paging of for the database
    if ($pager) {
        require_once('Pager/Pager.php');
        if (isset($_GET['offset'])) {
            $from = intval($_GET['offset']);
        } else {
            $from = 0;
        }

        // Remove any ORDER BY clauses as this will slow the count considerably
        if ($pos = strpos($sql, "ORDER BY")) {
            $sqlcount = substr($sql, 0, $pos);
        }

        // Count the number of rows that would be returned by the query
        $sqlcount = "SELECT COUNT(*) " . strstr($sqlcount, "FROM");
        $rows = mysql_result(dbquery($sqlcount), 0);

        // Build the pager data
        $pager_options = array(
            'mode' => 'Sliding',
            'perPage' => MAX_RESULTS,
            'delta' => 2,
            'totalItems' => $rows,
        );
        $pager = @Pager::factory($pager_options);

        //then we fetch the relevant records for the current page
        list($from, $to) = $pager->getOffsetByPageId();

        echo '<table cellspacing="1" class="mail" >
    <tr>
   <th colspan="5">Displaying page ' . $pager->getCurrentPageID() . ' of ' . $pager->numPages() . ' - Records ' . $from . ' to ' . $to . ' of ' . $pager->numItems() . '</th>
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
 <td colspan="4">';

        // Re-run the original query and limit the rows
        $sql .= " LIMIT $from," . MAX_RESULTS;
        $sth = dbquery($sql);
        $rows = mysql_num_rows($sth);
        $fields = mysql_num_fields($sth);
        // Account for extra operations column
        if ($operations != false) {
            $fields++;
        }

    } else {
        $sth = dbquery($sql);
        $rows = mysql_num_rows($sth);
        $fields = mysql_num_fields($sth);
        // Account for extra operations column
        if ($operations != false) {
            $fields++;
        }
    }

    if ($rows > 0) {
        echo '<table cellspacing="1" width="100%" class="mail">' . "\n";
        if ($title) {
            echo '<tr><th colspan=' . $fields . '>' . $title . '</TH></tr>' . "\n";
        }
        // Column headings
        echo ' <tr>' . "\n";
        for ($f = 0; $f < $fields; $f++) {
            echo '  <th>' . mysql_field_name($sth, $f) . '</th>' . "\n";
        }
        echo ' </tr>' . "\n";
        // Rows
        $i = 1;
        while ($row = mysql_fetch_row($sth)) {
            $i = 1 - $i;
            $bgcolor = $bg_colors[$i];
            echo ' <tr>' . "\n";
            for ($f = 0; $f < $fields; $f++) {
                echo '  <td style="background-color: ' . $bgcolor . '; ">' . $row[$f] . '</td>' . "\n";
            }
            echo ' </tr>' . "\n";
        }
        echo '</table>' . "\n";
    } else {
        echo "No rows retrieved!\n";
    }
    echo '<br>' . "\n";
    if ($pager) {
        require_once('Pager/Pager.php');
        if (isset($_GET['offset'])) {
            $from = intval($_GET['offset']);
        } else {
            $from = 0;
        }

        // Remove any ORDER BY clauses as this will slow the count considerably
        if ($pos = strpos($sql, "ORDER BY")) {
            $sqlcount = substr($sql, 0, $pos);
        }

        // Count the number of rows that would be returned by the query
        $sqlcount = "SELECT COUNT(*) " . strstr($sqlcount, "FROM");
        $rows = mysql_result(dbquery($sqlcount), 0);

        // Build the pager data
        $pager_options = array(
            'mode' => 'Sliding',
            'perPage' => MAX_RESULTS,
            'delta' => 2,
            'totalItems' => $rows,
        );
        $pager = @Pager::factory($pager_options);

        //then we fetch the relevant records for the current page
        list($from, $to) = $pager->getOffsetByPageId();

        echo '<table cellspacing="1" class="mail" >
    <tr>
   <th colspan="5">Displaying page ' . $pager->getCurrentPageID() . ' of ' . $pager->numPages() . ' - Records ' . $from . ' to ' . $to . ' of ' . $pager->numItems() . '</th>
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
 <td colspan="4">';
    }
}

function db_vertical_table($sql)
{
    $sth = dbquery($sql);
    $rows = mysql_num_rows($sth);
    $fields = mysql_num_fields($sth);

    if ($rows > 0) {
        echo '<table border="1" class="mail">' . "\n";
        while ($row = mysql_fetch_row($sth)) {
            for ($f = 0; $f < $fields; $f++) {
                echo " <tr>\n";
                echo "  <td>" . mysql_field_name($sth, $f) . "</td>\n";
                echo "  <td>" . $row[$f] . "</td>\n";
                echo " </tr>\n";
            }
        }
        echo "</table>\n";
    } else {
        echo "No rows retrieved\n";
    }
}

function get_microtime()
{
    return microtime(true);
}

function page_creation_timer()
{
    if (!isset($GLOBALS['pc_start_time'])) {
        $GLOBALS['pc_start_time'] = get_microtime();
    } else {
        $pc_end_time = get_microtime();
        $pc_total_time = $pc_end_time - $GLOBALS['pc_start_time'];
        printf("Page generated in %f seconds\n", $pc_total_time);
    }
}

function debug($text)
{
    if (DEBUG && headers_sent()) {
        echo "<!-- DEBUG: $text -->\n";
    }
}

function return_24_hour_array()
{
    $hour_array = array();
    for ($h = 0; $h < 24; $h++) {
        if (strlen($h) < 2) {
            $h = "0" . $h;
        }
        $hour_array[$h] = 0;
    }
    return $hour_array;
}

function return_60_minute_array()
{
    $minute_array = array();
    for ($m = 0; $m < 60; $m++) {
        if (strlen($m) < 2) {
            $m = "0" . $m;
        }
        $minute_array[$m] = 0;
    }
    return $minute_array;
}

function return_time_array()
{
    $time_array = array();
    for ($h = 0; $h < 24; $h++) {
        if (strlen($h) < 2) {
            $h = "0" . $h;
        }
        for ($m = 0; $m < 60; $m++) {
            if (strlen($m) < 2) {
                $m = "0" . $m;
            }
            $time_array[$h][$m] = 0;
        }
    }
    return $time_array;
}

function count_files_in_dir($dir)
{
    //TODO: Refactor
    $file_list_array = array();
    if (!$drh = @opendir($dir)) {
        return false;
    } else {
        while (false !== ($file = readdir($drh))) {
            if ($file !== "." && $file !== "..") {
                $file_list_array[] = $file;
            }
        }
    }
    return count($file_list_array);
}

function get_mail_relays($message_headers)
{
    $headers = explode("\\n", $message_headers);
    $relays = null;
    foreach ($headers as $header) {
        $header = preg_replace('/IPv6\:/', '', $header);
        if (preg_match_all('/\[(?P<ip>[\dabcdef.:]+)\]/', $header, $regs)) {
            foreach ($regs['ip'] as $relay) {
                $relays[] = $relay;
            }
        }
    }
    if (is_array($relays)) {
        return array_unique($relays);
    }

    return false;
}

function address_filter_sql($addresses, $type)
{
    $sqladdr = '';
    $sqladdr_arr = array();
    switch ($type) {
        case 'A': // Administrator - show everything
            $sqladdr = "1=1";
            break;
        case 'U': // User - show only specific addresses
            foreach ($addresses as $address) {
                if ((defined('FILTER_TO_ONLY') && FILTER_TO_ONLY)) {
                    $sqladdr_arr[] = "to_address like '%$address%'";
                } else {
                    $sqladdr_arr[] = "to_address like '%$address%' OR from_address = '$address'";
                }
            }
            $sqladdr = join(' OR ', $sqladdr_arr);
            break;
        case 'D': // Domain administrator
            foreach ($addresses as $address) {
                if (strpos($address, '@')) {
                    if ((defined('FILTER_TO_ONLY') && FILTER_TO_ONLY)) {
                        $sqladdr_arr[] = "to_address like '%$address%'";
                    } else {
                        $sqladdr_arr[] = "to_address like '%$address%' OR from_address = '$address'";
                    }
                } else {
                    if ((defined('FILTER_TO_ONLY') && FILTER_TO_ONLY)) {
                        $sqladdr_arr[] = "to_domain='$address'";
                    } else {
                        $sqladdr_arr[] = "to_domain='$address' OR from_domain='$address'";
                    }
                }
            }
            // Join together to form a suitable SQL WHERE clause
            $sqladdr = join(' OR ', $sqladdr_arr);
            break;
        case 'H': // Host
            foreach ($addresses as $hostname) {
                $sqladdr_arr[] = "hostname='$hostname'";
            }
            $sqladdr = join(' OR ', $sqladdr_arr);
            break;
    }

    return $sqladdr;
}

function ldap_authenticate($USER, $PASS)
{
    $USER = strtolower($USER);
    if ($USER != "" && $PASS != "") {
        $ds = ldap_connect(LDAP_HOST, LDAP_PORT) or die ("Could not connect to " . LDAP_HOST);
        ldap_bind($ds, LDAP_USER, LDAP_PASS);
        if (strpos($USER, '@')) {
            $r = ldap_search($ds, LDAP_DN, LDAP_EMAIL_FIELD."=SMTP:$USER") or die ("Could not search");
        } else {
            $r = ldap_search($ds, LDAP_DN, "sAMAccountName=$USER") or die ("Could not search");
        }
        if ($r) {
            $result = ldap_get_entries($ds, $r) or die ("Could not get entries");
            if ($result[0]) {
                $USER = $result[0]['userprincipalname']['0'];
                if (ldap_bind($ds, $USER, "$PASS")) {
                    if (isset ($result[0][LDAP_EMAIL_FIELD])) {
                        foreach ($result[0][LDAP_EMAIL_FIELD] as $email) {
                            if (substr($email, 0, 4) == "SMTP") {
                                $email = strtolower(substr($email, 5));
                                break;
                            }
                        }

                        $sql = sprintf("SELECT username from users where username = %s", quote_smart($email));
                        $sth = dbquery($sql);
                        if (mysql_num_rows($sth) == 0) {
                            $sql = sprintf(
                                "REPLACE into users (username, fullname, type, password) VALUES (%s, %s,'U',NULL)",
                                quote_smart($email),
                                quote_smart($result[0]['cn'][0])
                            );
                            dbquery($sql);
                        }
                        return $email;
                    }
                }
            }
        }
    }
    return null;
}

function ldap_get_conf_var($entry)
{
    // Translate MailScanner.conf vars to internal
    $entry = translate_etoi($entry);

    $lh = @ldap_connect(LDAP_HOST, LDAP_PORT)
        or die("Error: could not connect to LDAP directory on: " . LDAP_HOST . "\n");

    @ldap_bind($lh)
        or die("Error: unable to bind to LDAP directory\n");

    # As per MailScanner Config.pm
    $filter = "(objectClass=mailscannerconfmain)";
    $filter = "(&$filter(mailScannerConfBranch=main))";

    $sh = ldap_search($lh, LDAP_DN, $filter, array($entry));

    $info = ldap_get_entries($lh, $sh);
    if ($info['count'] > 0 && $info[0]['count'] <> 0) {
        if ($info[0]['count'] == 0) {
            // Return single value
            return $info[0][$info[0][0]][0];
        } else {
            // Multi-value option, build array and return as space delimited
            $return = array();
            for ($n = 0; $n < $info[0][$info[0][0]]['count']; $n++) {
                $return[] = $info[0][$info[0][0]][$n];
            }
            return join(" ", $return);
        }
    } else {
        // No results
        die("Error: cannot find configuration value '$entry' in LDAP directory.\n");
    }
}

function ldap_get_conf_truefalse($entry)
{
    // Translate MailScanner.conf vars to internal
    $entry = translate_etoi($entry);

    $lh = @ldap_connect(LDAP_HOST, LDAP_PORT)
        or die("Error: could not connect to LDAP directory on: " . LDAP_HOST . "\n");

    @ldap_bind($lh)
        or die("Error: unable to bind to LDAP directory\n");

    # As per MailScanner Config.pm
    $filter = "(objectClass=mailscannerconfmain)";
    $filter = "(&$filter(mailScannerConfBranch=main))";

    $sh = ldap_search($lh, LDAP_DN, $filter, array($entry));

    $info = ldap_get_entries($lh, $sh);
    debug(debug_print_r($info));
    if ($info['count'] > 0) {
        debug("Entry: " . debug_print_r($info[0][$info[0][0]][0]));
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
        //die("Error: cannot find configuration value '$entry' in LDAP directory.\n");
        return false;
    }
}

function translate_etoi($name)
{
    $name = strtolower($name);
    $file = MS_LIB_DIR . 'MailScanner/ConfigDefs.pl';
    $fh = fopen($file, 'r')
    or die("Cannot open MailScanner ConfigDefs file: $file\n");
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
    } else {
        return $name;
    }
}

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

function debug_print_r($input)
{
    ob_start();
    print_r($input);
    $return = ob_get_contents();
    ob_end_clean();
    return $return;
}

function return_geoip_country($ip)
{
    require_once 'lib/geoip.inc';
    //check if ipv4 has a port specified (e.g. 10.0.0.10:1025), strip it if found
    if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\:\d{1,5}/', $ip)) {
        $ip = current(array_slice(explode(':', $ip), 0, 1));
    }
    $countryname = false;
    if (strpos($ip, ':') === false) {
        //ipv4
        if (file_exists('./temp/GeoIP.dat')) {
            $gi = geoip_open('./temp/GeoIP.dat', GEOIP_STANDARD);
            $countryname = geoip_country_name_by_addr($gi, $ip);
            geoip_close($gi);
        }
    } else {
        //ipv6
        if (file_exists('./temp/GeoIPv6.dat')) {
            $gi = geoip_open('./temp/GeoIPv6.dat', GEOIP_STANDARD);
            $countryname = geoip_country_name_by_addr_v6($gi, $ip);
            geoip_close($gi);
        }
    }

    return $countryname;
}

function quarantine_list($input = "/")
{
    $quarantinedir = get_conf_var('QuarantineDir') . '/';
    $item = array();
    switch ($input) {
        case '/':
            // Return top-level directory
            $d = @opendir($quarantinedir);

            while (false !== ($f = @readdir($d))) {
                if ($f !== "." && $f !== "..") {
                    $item[] = $f;
                }
            }
            @closedir($d);
            break;
        default:
            $current_dir = $quarantinedir . $input;
            $dirs = array($current_dir, $current_dir . '/spam', $current_dir . '/nonspam', $current_dir . '/mcp');
            foreach ($dirs as $dir) {
                if (is_dir($dir) && is_readable($dir)) {
                    $d = @opendir($dir);
                    while (false !== ($f = readdir($d))) {
                        if ($f !== "." && $f !== "..") {
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

function is_local($host)
{
    $host = strtolower($host);
    // Is RPC required to look-up??
    $sys_hostname = strtolower(chop(`hostname`));
    switch ($host) {
        case $sys_hostname:
        case gethostbyaddr('127.0.0.1'):
            return true;
        default:
            // Remote - RPC needed
            return false;
    }
}

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
    $rows = mysql_num_rows($sth);
    if ($rows <= 0) {
        die("Message ID $msgid not found.\n");
    }
    $row = mysql_fetch_object($sth);
    if (!$rpc_only && is_local($row->hostname)) {
        $quarantinedir = get_conf_var("QuarantineDir");
        $quarantine = $quarantinedir . '/' . $row->date . '/' . $row->id;
        $spam = $quarantinedir . "/" . $row->date . '/spam/' . $row->id;
        $nonspam = $quarantinedir . "/" . $row->date . '/nonspam/' . $row->id;
        $mcp = $quarantinedir . "/" . $row->date . '/mcp/' . $row->id;
        if ($row->virusinfected == "Y" || $row->nameinfected == "Y" || $row->otherinfected == "Y") {
            $infected = "Y";
        } else {
            $infected = "N";
        }

        $count = 0;
        // Check for non-spam first
        if (file_exists($nonspam) && is_readable($nonspam)) {
            $quarantined[$count]['id'] = $count;
            $quarantined[$count]['host'] = $row->hostname;
            $quarantined[$count]['msgid'] = $row->id;
            $quarantined[$count]['to'] = $row->to_address;
            $quarantined[$count]['file'] = "message";
            $quarantined[$count]['type'] = "message/rfc822";
            $quarantined[$count]['path'] = $nonspam;
            $quarantined[$count]['md5'] = md5($nonspam);
            $quarantined[$count]['dangerous'] = $infected;
            $quarantined[$count]['isspam'] = $row->isspam;
            $count++;
        }
        // Check for spam
        if (file_exists($spam) && is_readable($spam)) {
            $quarantined[$count]['id'] = $count;
            $quarantined[$count]['host'] = $row->hostname;
            $quarantined[$count]['msgid'] = $row->id;
            $quarantined[$count]['to'] = $row->to_address;
            $quarantined[$count]['file'] = "message";
            $quarantined[$count]['type'] = "message/rfc822";
            $quarantined[$count]['path'] = $spam;
            $quarantined[$count]['md5'] = md5($spam);
            $quarantined[$count]['dangerous'] = $infected;
            $quarantined[$count]['isspam'] = $row->isspam;
            $count++;
        }
        // Check for mcp
        if (file_exists($mcp) && is_readable($mcp)) {
            $quarantined[$count]['id'] = $count;
            $quarantined[$count]['host'] = $row->hostname;
            $quarantined[$count]['msgid'] = $row->id;
            $quarantined[$count]['to'] = $row->to_address;
            $quarantined[$count]['file'] = "message";
            $quarantined[$count]['type'] = "message/rfc822";
            $quarantined[$count]['path'] = $mcp;
            $quarantined[$count]['md5'] = md5($spam);
            $quarantined[$count]['dangerous'] = $infected;
            $quarantined[$count]['isspam'] = $row->isspam;
            $count++;
        }
        // Check the main quarantine
        if (is_dir($quarantine) && is_readable($quarantine)) {
            $d = opendir($quarantine) or die("Cannot open quarantine dir: $quarantine\n");
            while (false !== ($f = readdir($d))) {
                if ($f !== '..' && $f !== '.') {
                    $quarantined[$count]['id'] = $count;
                    $quarantined[$count]['host'] = $row->hostname;
                    $quarantined[$count]['msgid'] = $row->id;
                    $quarantined[$count]['to'] = $row->to_address;
                    $quarantined[$count]['file'] = $f;
                    $file = escapeshellarg($quarantine . '/' . $f);
                    $quarantined[$count]['type'] = ltrim(rtrim(`/usr/bin/file -bi $file`));
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
    } else {
        // Host is remote call quarantine_list_items by RPC
        debug("Calling quarantine_list_items on $row->hostname by XML-RPC");
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$row->hostname,80);
        //if(DEBUG) { $client->setDebug(1); }
        //$parameters = array($input);
        //$msg = new xmlrpcmsg('quarantine_list_items',$parameters);
        $msg = new xmlrpcmsg('quarantine_list_items', array(new xmlrpcval($msgid)));
        $rsp = xmlrpc_wrapper($row->hostname, $msg); //$client->send($msg);
        if ($rsp->faultcode() == 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            $response = "XML-RPC Error: " . $rsp->faultstring();
        }
        return $response;
    }
}

function quarantine_release($list, $num, $to, $rpc_only = false)
{
    if (!is_array($list) || !isset($list[0]['msgid'])) {
        return "Invalid argument";
    } else {
        $new = quarantine_list_items($list[0]['msgid']);
        $list =& $new;
    }

    if (!$rpc_only && is_local($list[0]['host'])) {
        if (!QUARANTINE_USE_SENDMAIL) {
            // Load in the required PEAR modules
            require_once('PEAR.php');
            require_once('Mail.php');
            require_once('Mail/mime.php');
            $crlf = "\r\n";
            $hdrs = array('From' => QUARANTINE_FROM_ADDR, 'Subject' => QUARANTINE_SUBJECT, 'Date' => date("r"));
            $mime = new Mail_mime($crlf);
            $mime->setTXTBody(QUARANTINE_MSG_BODY);
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
            $mail_param = array('host' => QUARANTINE_MAIL_HOST);
            $body = $mime->get();
            $hdrs = $mime->headers($hdrs);
            $mail =& Mail::factory('smtp', $mail_param);
            $m_result = $mail->send($to, $hdrs, $body);
            if (PEAR::isError($m_result)) {
                // Error
                $status = 'Release: error (' . $m_result->getMessage() . ')';
                global $error;
                $error = true;
            } else {
                $status = "Release: message released to " . str_replace(",", ", ", $to);
                audit_log('Quarantined message (' . $list[$val]['msgid'] . ') released to ' . $to);
            }
            return ($status);
        } else {
            // Use sendmail to release message
            // We can only release message/rfc822 files in this way.
            $cmd = QUARANTINE_SENDMAIL_PATH . " -i -f " . QUARANTINE_FROM_ADDR . " " . escapeshellarg($to) . " < ";
            foreach ($num as $key => $val) {
                if (preg_match('/message\/rfc822/', $list[$val]['type'])) {
                    debug($cmd . $list[$val]['path']);
                    exec($cmd . $list[$val]['path'] . " 2>&1", $output_array, $retval);
                    if ($retval == 0) {
                        $status = "Release: message released to " . str_replace(",", ", ", $to);
                        audit_log('Quarantined message (' . $list[$val]['msgid'] . ') released to ' . $to);
                    } else {
                        $status = "Release: error code " . $retval . " returned from Sendmail:\n" . join(
                                "\n",
                                $output_array
                            );
                        global $error;
                        $error = true;
                    }
                    return ($status);
                }
            }
        }
    } else {
        // Host is remote - handle by RPC
        debug("Calling quarantine_release on " . $list[0]['host'] . " by XML-RPC");
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
        // Convert input parameters
        foreach ($list as $list_array) {
            foreach ($list_array as $key => $val) {
                $list_struct[$key] = new xmlrpcval($val);
            }
            $list_output[] = new xmlrpcval($list_struct, 'struct');
        }
        foreach ($num as $key => $val) {
            $num_output[$key] = new xmlrpcval($val);
        }
        // Build input parameters
        $param1 = new xmlrpcval($list_output, 'array');
        $param2 = new xmlrpcval($num_output, 'array');
        $param3 = new xmlrpcval($to, 'string');
        $parameters = array($param1, $param2, $param3);
        $msg = new xmlrpcmsg('quarantine_release', $parameters);
        $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
        if ($rsp->faultcode() == 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            $response = "XML-RPC Error: " . $rsp->faultstring();
        }
        return $response . " (RPC)";
    }
}

function quarantine_learn($list, $num, $type, $rpc_only = false)
{
    dbconn();
    if (!is_array($list) || !isset($list[0]['msgid'])) {
        return "Invalid argument";
    } else {
        $new = quarantine_list_items($list[0]['msgid']);
        $list =& $new;
    }
    $status = array();
    if (!$rpc_only && is_local($list[0]['host'])) {
        foreach ($num as $key => $val) {
            $use_spamassassin = false;
            switch ($type) {
                case "ham":
                    $learn_type = "ham";
                    if ($list[$val]['isspam'] == 'Y') {
                        // Learning SPAM as HAM - this is a false-positive
                        $sql = "UPDATE maillog SET timestamp=timestamp, isfp=1, isfn=0 WHERE id='" . mysql_real_escape_string(
                                $list[$val]['msgid']
                            ) . "'";
                    } else {
                        // Learning HAM as HAM - better reset the flags just in case
                        $sql = "UPDATE maillog SET timestamp=timestamp, isfp=0, isfn=0 WHERE id='" . mysql_real_escape_string(
                                $list[$val]['msgid']
                            ) . "'";
                    }
                    break;
                case "spam":
                    $learn_type = "spam";
                    if ($list[$val]['isspam'] == 'N') {
                        // Learning HAM as SPAM - this is a false-negative
                        $sql = "UPDATE maillog SET timestamp=timestamp, isfp=0, isfn=1 WHERE id='" . mysql_real_escape_string(
                                $list[$val]['msgid']
                            ) . "'";
                    } else {
                        // Learning SPAM as SPAM - better reset the flags just in case
                        $sql = "UPDATE maillog SET timestamp=timestamp, isfp=0, isfn=0 WHERE id='" . mysql_real_escape_string(
                                $list[$val]['msgid']
                            ) . "'";
                    }
                    break;
                case "forget":
                    $learn_type = "forget";
                    $sql = "UPDATE maillog SET timestamp=timestamp, isfp=0, isfn=0 WHERE id='" . mysql_real_escape_string(
                            $list[$val]['msgid']
                        ) . "'";
                    break;
                case "report":
                    $use_spamassassin = true;
                    $learn_type = "-r";
                    $sql = "UPDATE maillog SET timestamp=timestamp, isfp=0, isfn=1 WHERE id='" . mysql_real_escape_string(
                            $list[$val]['msgid']
                        ) . "'";
                    break;
                case "revoke":
                    $use_spamassassin = true;
                    $learn_type = "-k";
                    $sql = "UPDATE maillog SET timestamp=timestamp, isfp=1, isfn=0 WHERE id='" . mysql_real_escape_string(
                            $list[$val]['msgid']
                        ) . "'";
                    break;
            }
            if (true === $use_spamassassin) {
                // Run SpamAssassin to report or revoke spam/ham
                exec(
                    SA_DIR . 'spamassassin -p ' . SA_PREFS . ' ' . $learn_type . ' < ' . $list[$val]['path'] . ' 2>&1',
                    $output_array,
                    $retval
                );
                if ($retval == 0) {
                    // Command succeeded - update the database accordingly
                    if (isset($sql)) {
                        debug("Learner - running SQL: $sql");
                        dbquery($sql);
                    }
                    $status[] = "SpamAssassin: " . join(", ", $output_array);
                    switch ($learn_type) {
                        case "-r":
                            $learn_type = "spam";
                            break;
                        case "-k":
                            $learn_type = "ham";
                            break;
                    }
                    audit_log(
                        'SpamAssassin was trained and reported on message ' . $list[$val]['msgid'] . ' as ' . $learn_type
                    );
                } else {
                    $status[] = "SpamAssassin: error code " . $retval . " returned from SpamAssassin:\n" . join(
                            "\n",
                            $output_array
                        );
                    global $error;
                    $error = true;
                }
            } else {
                // Only sa-learn required
                exec(
                    SA_DIR . 'sa-learn -p ' . SA_PREFS . ' --' . $learn_type . ' --file ' . $list[$val]['path'] . ' 2>&1',
                    $output_array,
                    $retval
                );
                if ($retval == 0) {
                    // Command succeeded - update the database accordingly
                    if (isset($sql)) {
                        debug("Learner - running SQL: $sql");
                        dbquery($sql);
                    }
                    $status[] = "SA Learn: " . join(", ", $output_array);
                    audit_log('SpamAssassin was trained on message ' . $list[$val]['msgid'] . ' as ' . $learn_type);
                } else {
                    $status[] = "SA Learn: error code " . $retval . " returned from sa-learn:\n" . join(
                            "\n",
                            $output_array
                        );
                    global $error;
                    $error = true;
                }
            }
        }
        return join("\n", $status);
    } else {
        // Call by RPC
        debug("Calling quarantine_learn on " . $list[0]['host'] . " by XML-RPC");
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
        // Convert input parameters
        foreach ($list as $list_array) {
            foreach ($list_array as $key => $val) {
                $list_struct[$key] = new xmlrpcval($val);
            }
            $list_output[] = new xmlrpcval($list_struct, 'struct');
        }
        foreach ($num as $key => $val) {
            $num_output[$key] = new xmlrpcval($val);
        }
        // Build input parameters
        $param1 = new xmlrpcval($list_output, 'array');
        $param2 = new xmlrpcval($num_output, 'array');
        $param3 = new xmlrpcval($type, 'string');
        $parameters = array($param1, $param2, $param3);
        $msg = new xmlrpcmsg('quarantine_learn', $parameters);
        $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
        if ($rsp->faultcode() == 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            $response = "XML-RPC Error: " . $rsp->faultstring();
        }
        return $response . " (RPC)";
    }
}

function quarantine_delete($list, $num, $rpc_only = false)
{
    if (!is_array($list) || !isset($list[0]['msgid'])) {
        return "Invalid argument";
    } else {
        $new = quarantine_list_items($list[0]['msgid']);
        $list =& $new;
    }

    if (!$rpc_only && is_local($list[0]['host'])) {
        foreach ($num as $key => $val) {
            if (@unlink($list[$val]['path'])) {
                $status[] = "Delete: deleted file " . $list[$val]['path'];
                dbquery("UPDATE maillog SET quarantined=NULL WHERE id='" . $list[$val]['msgid'] . "'");
                audit_log('Delete file from quarantine: ' . $list[$val]['path']);
            } else {
                $status[] = "Delete: error deleting file " . $list[$val]['path'];
                global $error;
                $error = true;
            }
        }
        return join("\n", $status);
    } else {
        // Call by RPC
        debug("Calling quarantine_delete on " . $list[0]['host'] . " by XML-RPC");
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
        // Convert input parameters
        foreach ($list as $list_array) {
            foreach ($list_array as $key => $val) {
                $list_struct[$key] = new xmlrpcval($val);
            }
            $list_output[] = new xmlrpcval($list_struct, 'struct');
        }
        foreach ($num as $key => $val) {
            $num_output[$key] = new xmlrpcval($val);
        }
        // Build input parameters
        $param1 = new xmlrpcval($list_output, 'array');
        $param2 = new xmlrpcval($num_output, 'array');
        $parameters = array($param1, $param2);
        $msg = new xmlrpcmsg('quarantine_delete', $parameters);
        $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
        if ($rsp->faultcode() == 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            $response = "XML-RPC Error: " . $rsp->faultstring();
        }
        return $response . " (RPC)";
    }
}

function audit_log($action)
{
    dbconn();
    if (AUDIT) {
        $user = mysql_real_escape_string($_SESSION['myusername']);
        $action = mysql_real_escape_string($action);
        $ip = mysql_real_escape_string($_SERVER['REMOTE_ADDR']);
        $ret = dbquery("INSERT INTO audit_log (user, ip_address, action) VALUES ('$user', '$ip', '$action')");
        if ($ret){
            return true;
        }
    }
    return false;
}

function mailwatch_array_sum($array)
{
    if (!is_array($array)) {
        // Not an array
        return array();
    } else {
        return array_sum($array);
    }
}

function read_ruleset_default($file)
{
    $fh = fopen($file, 'r')
    or die("Cannot open MailScanner ruleset file ($file)");
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, filesize($file)));
        if (preg_match('/(\S+)\s+(\S+)\s+(\S+)/', $line, $regs)) {
            if (strtolower($regs[2]) == 'default') {
                // Check that it isn't another ruleset
                if (is_file($regs[3])) {
                    return read_ruleset_default($regs[3]);
                } else {
                    return $regs[3];
                }
            }
        }
    }
}

function get_virus_conf($scanner)
{
    $fh = fopen(MS_CONFIG_DIR . 'virus.scanners.conf', 'r');
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, 1048576));
        if (preg_match("/(^[^#]\S+)\s+(\S+)\s+(\S+)/", $line, $regs)) {
            if ($regs[1] == $scanner) {
                fclose($fh);
                return $regs[2] . " " . $regs[3];
            }
        }
    }
    // Not found
    fclose($fh);
    return false;
}

function return_quarantine_dates()
{
    date_default_timezone_set(TIME_ZONE);
    $array = array();
    for ($d = 0; $d < (QUARANTINE_DAYS_TO_KEEP); $d++) {
        $array[] = date('Ymd', mktime(0, 0, 0, date("m"), date("d") - $d, date("Y")));
    }
    return $array;
}

function return_virus_link($virus)
{
    if ((defined('VIRUS_INFO') && VIRUS_INFO !== false)) {
        $link = sprintf(VIRUS_INFO, $virus);
        return sprintf("<a href=\"%s\">%s</a>", $link, $virus);
    } else {
        return $virus;
    }
}

function net_match($network, $ip)
{
    // Skip invalid entries
    if (long2ip(ip2long($ip)) === false) {
        return false;
    }
    // From PHP website
    // determines if a network in the form of 192.168.17.1/16 or
    // 127.0.0.1/255.255.255.255 or 10.0.0.1 matches a given ip
    $ip_arr = explode('/', $network);
    // Skip invalid entries
    if (long2ip(ip2long($ip_arr[0])) === false) {
        return false;
    }
    $network_long = ip2long($ip_arr[0]);

    $x = ip2long($ip_arr[1]);
    $mask = long2ip($x) == $ip_arr[1] ? $x : 0xffffffff << (32 - $ip_arr[1]);
    $ip_long = ip2long($ip);

    return ($ip_long & $mask) == ($network_long & $mask);
}

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
            if ($client == 'allprivate' && (net_match('10.0.0.0/8', $_SERVER['SERVER_ADDR']) || net_match(
                        '172.16.0.0/12',
                        $_SERVER['SERVER_ADDR']
                    ) || net_match('192.168.0.0/16', $_SERVER['SERVER_ADDR']))
            ) {
                return true;
            }
            if ($client == 'local24') {
                // Get machine IP address from the hostname
                $ip = gethostbyname(chop(`hostname`));
                // Change IP address to a /24 network
                $ipsplit = explode('.', $ip);
                $ipsplit[3] = '0';
                $ip = implode('.', $ipsplit);
                if (net_match("{$ip}/24", $_SERVER['SERVER_ADDR'])) {
                    return true;
                }
            }
            // All any others
            if (net_match($client, $_SERVER['SERVER_ADDR'])) {
                return true;
            }
            // Try hostname
            $iplookup = gethostbyname($client);
            if ($client !== $iplookup && net_match($iplookup, $_SERVER['SERVER_ADDR'])) {
                return true;
            }
        }
        // If all else fails
        return false;
    } else {
        return false;
    }
}

function xmlrpc_wrapper($host, $msg)
{
    $method = 'http';
    // Work out port
    if ((defined('SSL_ONLY') && SSL_ONLY)) {
        $port = 443;
        $method = 'https';
    } elseif (defined('RPC_PORT')) {
        $port = RPC_PORT;
        if ((defined('RPC_SSL') && RPC_SSL)) {
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
    $response = $client->send($msg, 0, $method);
    return $response;
}

// Clean Cache folder
function clear_cache_dir()
{
    $cache_dir = MAILWATCH_HOME . '/' . CACHE_DIR;
    $files = glob($cache_dir . '/*');
    // Life of cached images: hard set to 60 seconds
    $life = '60';
    // File not to delete
    $placeholder_file = $cache_dir . "/place_holder.txt";
    foreach ($files as $file) {
        if (is_file($file) || is_link($file)) {
            if (((time() - filemtime($file) >= $life) && ($file != $placeholder_file))) {
                unlink($file);
            }
        }
    }
}

function mailwatch_version()
{
    return ("1.2.0 - Beta 6 DEV");
}
