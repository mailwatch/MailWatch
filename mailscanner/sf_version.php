<?php

/*
 MailWatch for MailScanner
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

// Include of necessary functions
require_once("./functions.php");

// Authentication checking
session_start();
require('login.function.php');

if ($_SESSION['user_type'] != 'A') {
    header("Location: index.php");
    audit_log('Non-admin user attemped to view Software Version Page');
} else {
    html_start('MailWatch and MailScanner Version information', '0', false, false);
    $mailwatch_version = mailwatch_version();
    $mailscanner_version = get_conf_var('MailScannerVersionNumber');
    $php_version = phpversion();
    $mysql_version = mysql_result(dbquery("SELECT VERSION()"), 0);
    $geoipv4_version = FALSE;
    $geoipv6_version = FALSE;
    if (file_exists('./temp/GeoIP.dat')) {
        $geoipv4_version = date('r', filemtime('./temp/GeoIP.dat')) . ' (download date)';
    }
    if (file_exists('./temp/GeoIPv6.dat')) {
        $geoipv6_version = date('r', filemtime('./temp/GeoIPv6.dat')) . ' (download date)';
    }

    echo '<table width="100%" class="boxtable">' . "\n";
    echo '<tr>' . "\n";
    echo '<td>' . "\n";

    echo '<p class="center" style="font-size:20px"><b>Software Versions</b></p>' . "\n";
    echo 'MailWatch Version = ' . $mailwatch_version . '<br>' . "\n";
    echo '<br>' . "\n";
    echo 'MailScanner Version = ' . $mailscanner_version . '<br>' . "\n";
    echo '<br>' . "\n";
    echo 'PHP Version = ' . $php_version . '<br>' . "\n";
    echo '<br>' . "\n";
    echo 'MySQL Version = ' . $mysql_version . '<br>' . "\n";
    echo '<br>' . "\n";
    echo 'GeoIP Database Version = ';
    if (FALSE != $geoipv4_version) {
        echo $geoipv4_version;
    } else {
        echo 'No database downloaded';
    }
    echo "<br>\n<br>\n";
    echo 'GeoIPv6 Database Version = ';
    if (FALSE != $geoipv6_version) {
        echo $geoipv6_version;
    } else {
        echo 'No database downloaded';
    }
    echo "<br>\n<br>\n";
    echo '</td>' . "\n";
    echo '</tr>' . "\n";
    echo '</table>' . "\n";

    // Add footer
    html_end();
    // Close any open db connections
    dbclose();
}