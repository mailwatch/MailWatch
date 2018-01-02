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

namespace MailWatch;

class Html
{
    /**
     * @param $title
     * @param int $refresh
     * @param bool|true $cacheable
     * @param bool|false $report
     * @return Filter|int
     */
    public static function start($title, $refresh = 0, $cacheable = true, $report = false)
    {
        if (PHP_SAPI !== 'cli') {
            if (!$cacheable) {
                // Cache control (as per PHP website)
                Security::disableBrowserCache();
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
        if (Security::checkPrivilegeChange($_SESSION['myusername']) === true) {
            header('Location: logout.php?error=timeout');
            die();
        }

/*        if (\MailWatch\Security::checkLoginExpiry($_SESSION['myusername']) === true) {
            header('Location: logout.php?error=timeout');
            die();
        }*/

        if ($refresh === 0) {
            // User is moving about on non-refreshing pages, keep session alive
            Security::updateLoginExpiry($_SESSION['myusername']);
        }

        echo page_creation_timer();
        echo '<!DOCTYPE HTML>' . "\n";
        echo '<html>' . "\n";
        echo '<head>' . "\n";
        echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . "\n";
        echo '<link rel="shortcut icon" href="images/favicon.png" >' . "\n";
        echo '<script type="text/javascript">';
        echo '' . static::javascriptTime() . '';
        //$current_url = "".MAILWATCH_HOME."/status.php";
        //if($_SERVER['SCRIPT_FILENAME'] === $active_url){
        echo '</script>';
        if ($report) {
            echo '<title>' . Translation::__('mwfilterreport03') . ' ' . $title . ' </title>' . "\n";
            if (!isset($_SESSION['filter'])) {
                $filter = new Filter();
                $_SESSION['filter'] = $filter;
            } else {
                // Use existing filters
                $filter = $_SESSION['filter'];
            }
            Security::audit_log(Translation::__('auditlogreport03', true) . ' ' . $title);
        } else {
            echo '<title>' . Translation::__('mwforms03') . $title . '</title>' . "\n";
        }
        echo '<link rel="stylesheet" type="text/css" href="./style.css">' . "\n";
        if (is_file(__DIR__ . '/skin.css')) {
            echo '<link rel="stylesheet" href="./skin.css" type="text/css">';
        }

        if ($refresh > 0) {
            echo '<meta http-equiv="refresh" content="' . $refresh . '">' . "\n";
        }

        if (isset($_GET['id'])) {
            $message_id = trim(htmlentities(Sanitize::safe_value(Sanitize::sanitizeInput($_GET['id']))), ' ');
            if (!Sanitize::validateInput($message_id, 'msgid')) {
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
        echo '<td align="left"><a href="index.php" class="logo"><img src=".' . IMAGES_DIR . MW_LOGO . '" alt="' . Translation::__('mailwatchtitle03') . '"></a></td>' . "\n";
        echo '</tr>' . "\n";
        echo '<tr>' . "\n";
        echo '<td valign="bottom" align="left" class="jump">' . "\n";
        echo '<form action="./detail.php">' . "\n";
        echo '<p>' . Translation::__('jumpmessage03') . '<input type="text" name="id" value="' . $message_id . '"></p>' . "\n";
        echo '<input type="hidden" name="token" value="' . $_SESSION['token'] . '">' . "\n";
        echo '</form>' . "\n";
        echo '</td>';
        echo '</tr>';
        echo '</table>' . "\n";
        echo '<table cellspacing="1" class="mail">' . "\n";
        echo '<tr><td class="heading" align="center">' . Translation::__('cuser03') . '</td><td class="heading" align="center">' . Translation::__('cst03') . '</td></tr>' . "\n";
        echo '<tr><td>' . $_SESSION['fullname'] . '</td><td><span id="clock">&nbsp;</span></td></tr>' . "\n";
        echo '</table>' . "\n";
        echo '</td>' . "\n";

        if ($_SESSION['user_type'] === 'A' || $_SESSION['user_type'] === 'D') {
            echo '  <td align="center" valign="top">' . "\n";

            // Status table
            echo '   <table border="0" cellpadding="1" cellspacing="1" class="mail">' . "\n";
            echo '    <tr><th colspan="3">' . Translation::__('status03') . '</th></tr>' . "\n";

            static::printServiceStatus();
            static::printAverageLoad();

            if ($_SESSION['user_type'] === 'A') {
                static::printMTAQueue();
                static::printFreeDiskSpace();
            }
            echo '  </table>' . "\n";
            echo '  </td>' . "\n";

            static::printTrafficGraph();
        }

        echo '<td align="center" valign="top">' . "\n";
        static::printTodayStatistics();
        echo '  </td>' . "\n";

        echo ' </tr>' . "\n";

        static::printNavBar();
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

    /**
     * @param string $footer
     */
    public static function end($footer = '')
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
        echo Translation::__('footer03');
        echo mailwatch_version();
        echo ' - &copy; 2006-' . date('Y');
        echo '</p>' . "\n";
        echo '</body>' . "\n";
        echo '</html>' . "\n";
    }

    public static function javascriptTime()
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

    public static function printNavBar()
    {
        // Navigation links - put them into an array to allow them to be switched
        // on or off as necessary and to allow for the table widths to be calculated.
        $nav = [];
        $nav['status.php'] = Translation::__('recentmessages03');
        if (LISTS) {
            $nav['lists.php'] = Translation::__('lists03');
        }
        if (!DISTRIBUTED_SETUP) {
            $nav['quarantine.php'] = Translation::__('quarantine03');
        }
        $nav['reports.php'] = Translation::__('reports03');
        $nav['other.php'] = Translation::__('toolslinks03');

        if (SHOW_SFVERSION === true && $_SESSION['user_type'] === 'A') {
            $nav['sf_version.php'] = Translation::__('softwareversions03');
        }

        if (SHOW_DOC === true) {
            $nav['docs.php'] = Translation::__('documentation03');
        }
        $nav['logout.php'] = Translation::__('logout03');
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
                for ($i = 0; $i < $langCount; $i++) {
                    echo '<option value="' . $langCodes[$i] . '"'
                        . ($langCodes[$i] === $langCode ? ' selected' : '')
                        . '>' . Translation::__($langCodes[$i]) . '</option>' . "\n";
                }
                echo '</select></li>' . "\n";
            }
        }

        echo '
 </ul>
 </td>
 </tr>';
    }

    public static function printTodayStatistics()
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

        $sth = Db::query($sql);
        while ($row = $sth->fetch_object()) {
            echo '<table border="0" cellpadding="1" cellspacing="1" class="mail todaystatistics" width="220">' . "\n";
            echo ' <tr><th align="center" colspan="3">' . Translation::__('todaystotals03') . '</th></tr>' . "\n";
            echo ' <tr><td>' . Translation::__('processed03') . '</td><td>' . number_format(
                    $row->processed
                ) . '</td><td>' . Format::formatSize(
                    $row->size
                ) . '</td></tr>' . "\n";
            echo ' <tr><td>' . Translation::__('cleans03') . '</td><td>' . number_format(
                    $row->clean
                ) . '</td><td>' . $row->cleanpercent . '%</td></tr>' . "\n";
            echo ' <tr><td>' . Translation::__('viruses03') . '</td><td>' . number_format(
                    $row->viruses
                ) . '</td><td>' . $row->viruspercent . '%</tr>' . "\n";
            echo ' <tr><td>' . Translation::__('topvirus03') . '</td><td colspan="2">' . Antivirus::return_todays_top_virus() . '</td></tr>' . "\n";
            echo ' <tr><td>' . Translation::__('blockedfiles03') . '</td><td>' . number_format(
                    $row->blockedfiles
                ) . '</td><td>' . $row->blockedfilespercent . '%</td></tr>' . "\n";
            echo ' <tr><td>' . Translation::__('others03') . '</td><td>' . number_format(
                    $row->otherinfected
                ) . '</td><td>' . $row->otherinfectedpercent . '%</td></tr>' . "\n";
            echo ' <tr><td>' . Translation::__('spam03') . '</td><td>' . number_format(
                    $row->spam
                ) . '</td><td>' . $row->spampercent . '%</td></tr>' . "\n";
            echo ' <tr><td>' . Translation::__('hscospam03') . '</td><td>' . number_format(
                    $row->highspam
                ) . '</td><td>' . $row->highspampercent . '%</td></tr>' . "\n";
            if (MailScanner::getConfTrueFalse('mcpchecks')) {
                echo ' <tr><td>MCP:</td><td>' . number_format(
                        $row->mcp
                    ) . '</td><td>' . $row->mcppercent . '%</td></tr>' . "\n";
                echo ' <tr><td>' . Translation::__('hscomcp03') . '</td><td>' . number_format(
                        $row->highmcp
                    ) . '</td><td>' . $row->highmcppercent . '%</td></tr>' . "\n";
            }
            echo '</table>' . "\n";
        }
    }

    public static function printFreeDiskSpace()
    {
        if (!DISTRIBUTED_SETUP) {
            // Drive display
            echo '    <tr><td colspan="3" class="heading" align="center">' . Translation::__('freedspace03') . '</td></tr>' . "\n";
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
                echo '    <tr><td>' . $disk['mountpoint'] . '</td><td colspan="2" align="right">' . Format::formatSize($free_space) . $percent . '</td>' . "\n";
            }
        }
    }

    public static function printMTAQueue()
    {
        // Display the MTA queue
        // Postfix if mta = postfix
        if (MailScanner::getConfVar('MTA', true) === 'postfix') {
            // Mail Queues display
            $incomingdir = MailScanner::getConfVar('incomingqueuedir', true);
            $outgoingdir = MailScanner::getConfVar('outgoingqueuedir', true);
            $inq = null;
            $outq = null;
            if (is_readable($incomingdir) || is_readable($outgoingdir)) {
                $inq = MTA\Postfix::postfixinq();
                $outq = MTA\Postfix::postfixallq() - $inq;
            } elseif (!defined('RPC_REMOTE_SERVER')) {
                echo '    <tr><td colspan="3">' . Translation::__('verifyperm03') . ' ' . $incomingdir . ' ' . Translation::__('and03') . ' ' . $outgoingdir . '</td></tr>' . "\n";
            }

            if (defined('RPC_REMOTE_SERVER')) {
                $pqerror = '';
                $servers = explode(' ', RPC_REMOTE_SERVER);

                for ($i = 0, $count_servers = count($servers); $i < $count_servers; $i++) {
                    if ($servers[$i] !== gethostbyname(gethostname())) {
                        $msg = new \xmlrpcmsg('postfix_queues', []);
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
                        echo '    <tr><td colspan="3">' . Translation::__('errorWarning03') . ' ' . $pqerror . '</td>' . "\n";
                    }
                }
            }
            if ($inq !== null && $outq !== null) {
                echo '    <tr><td colspan="3" class="heading" align="center">' . Translation::__('mailqueue03') . '</td></tr>' . "\n";
                echo '    <tr><td colspan="2"><a href="postfixmailq.php">' . Translation::__('inbound03') . '</a></td><td align="right">' . $inq . '</td>' . "\n";
                echo '    <tr><td colspan="2"><a href="postfixmailq.php">' . Translation::__('outbound03') . '</a></td><td align="right">' . $outq . '</td>' . "\n";
            }

            // Else use MAILQ from conf.php which is for Sendmail or Exim
        } elseif (defined('MAILQ') && MAILQ === true && !DISTRIBUTED_SETUP) {
            if (MailScanner::getConfVar('MTA') === 'exim') {
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
            echo '    <tr><td colspan="3" class="heading" align="center">' . Translation::__('mailqueue03') . '</td></tr>' . "\n";
            echo '    <tr><td colspan="2"><a href="mailq.php?token=' . $_SESSION['token'] . '&amp;queue=inq">' . Translation::__('inbound03') . '</a></td><td align="right">' . $inq . '</td>' . "\n";
            echo '    <tr><td colspan="2"><a href="mailq.php?token=' . $_SESSION['token'] . '&amp;queue=outq">' . Translation::__('outbound03') . '</a></td><td align="right">' . $outq . '</td>' . "\n";
        }
    }

    public static function printAverageLoad()
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
            <td align="left" rowspan="3">' . Translation::__('loadaverage03') . '&nbsp;</td>
            <td align="right">' . Translation::__('1minute03') . '&nbsp;</td>
            <td align="right">' . $la_1m . '</td>
        </tr>
        </tr>
            <td align="right" colspan="1">' . Translation::__('5minutes03') . '&nbsp;</td>
            <td align="right">' . $la_5m . '</td>
        </tr>
            <td align="right" colspan="1">' . Translation::__('15minutes03') . '&nbsp;</td>
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
            <td align="left" rowspan="3">' . Translation::__('loadaverage03') . '&nbsp;</td>
            <td align="right">' . Translation::__('1minute03') . '&nbsp;</td>
            <td align="right">' . $la_1m . '</td>
        </tr>
        </tr>
            <td align="right" colspan="1">' . Translation::__('5minutes03') . '&nbsp;</td>
            <td align="right">' . $la_5m . '</td>
        </tr>
            <td align="right" colspan="1">' . Translation::__('15minutes03') . '&nbsp;</td>
            <td align="right">' . $la_15m . '</td>
        </tr>
        ' . "\n";
        }
    }

    public static function printServiceStatus()
    {
        // MailScanner running?
        if (!DISTRIBUTED_SETUP) {
            $no = '<span class="yes">&nbsp;' . Translation::__('no03') . '&nbsp;</span>' . "\n";
            $yes = '<span class="no">&nbsp;' . Translation::__('yes03') . '&nbsp;</span>' . "\n";
            exec('ps ax | grep MailScanner | grep -v grep', $output);
            if (count($output) > 0) {
                $running = $yes;
                $procs = count($output) - 1 . ' ' . Translation::__('children03');
            } else {
                $running = $no;
                $procs = count($output) . ' ' . Translation::__('procs03');
            }
            echo '     <tr><td>' . Translation::__('mailscanner03') . '</td><td align="center">' . $running . '</td><td align="right">' . $procs . '</td></tr>' . "\n";

            // is MTA running
            $mta = MailScanner::getConfVar('mta');
            exec("ps ax | grep $mta | grep -v grep | grep -v php", $output);
            if (count($output) > 0) {
                $running = $yes;
            } else {
                $running = $no;
            }
            $procs = count($output) . ' ' . Translation::__('procs03');
            echo '    <tr><td>' . ucwords($mta) . Translation::__('colon99') . '</td>'
                . '<td align="center">' . $running . '</td><td align="right">' . $procs . '</td></tr>' . "\n";
        }
    }

    public static function printColorCodes()
    {
        echo '   <table border="0" cellpadding="1" cellspacing="3"  align="center" class="mail colorcodes">' . "\n";
        echo '    <tr><td class="infected"></td> <td>' . Translation::__('badcontentinfected03') . '</td>' . "\n";
        echo '    <td class="spam"></td> <td>' . Translation::__('spam103') . ' </td>' . "\n";
        echo '    <td class="highspam"></td> <td>' . Translation::__('highspam03') . '</td>' . "\n";
        if (MailScanner::getConfTrueFalse('mcpchecks')) {
            echo '    <td class="mcp"></td> <td>' . Translation::__('mcp03') . '</td>' . "\n";
            echo '    <td class="highmcp"></td> <td>' . Translation::__('highmcp03') . '</td>' . "\n";
        }
        echo '    <td class="whitelisted"></td> <td>' . Translation::__('whitelisted03') . '</td>' . "\n";
        echo '    <td class="blacklisted"></td> <td>' . Translation::__('blacklisted03') . '</td>' . "\n";
        echo '    <td class="notscanned"></td> <td>' . Translation::__('notverified03') . '</td>' . "\n";
        echo '    <td class="clean"></td> <td>' . Translation::__('clean03') . '</td></tr>' . "\n";
        echo '   </table><br>' . "\n";
    }

    public static function printTrafficGraph()
    {
        $graphInterval = (defined('STATUSGRAPH_INTERVAL') ? STATUSGRAPH_INTERVAL : 60);

        echo '<td align="center" valign="top">' . "\n";
        echo '   <table border="0" cellpadding="1" cellspacing="1" class="mail">' . "\n";
        if ($graphInterval <= 60) {
            echo '    <tr><th colspan="1">' . Translation::__('trafficgraph03') . '</th></tr>' . "\n";
        } else {
            echo '    <tr><th colspan="1">' . sprintf(Translation::__('trafficgraphmore03'), $graphInterval / 60) . '</th></tr>' . "\n";
        }
        echo '    <tr>' . "\n";
        echo '    <td>' . "\n";

        $graphgenerator = new GraphGenerator();
        $graphgenerator->sqlQuery = '
     SELECT
      timestamp AS xaxis,
      1 AS total_mail,
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
                [Translation::__('barvirus03'), Translation::__('barspam03'), Translation::__('barmail03')],
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
            'fillBelowLine' => ['true'],
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
}
