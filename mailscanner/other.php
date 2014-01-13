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

// Include of nessecary functions
require_once("./functions.php");

// Authenication checking
session_start();
require('login.function.php');

html_start('Tools', "0", false, false);


echo '<table width="100%" class="boxtable">
 <tr>
  <td>
   <p>Tools</p>
   <ul>';
if (!MSEE) {
    echo '<li><a href="user_manager.php">User Management</a>';
}
if (preg_match('/sophos/i', get_conf_var('VirusScanners')) && $_SESSION['user_type'] == 'A') {
    echo '<li><a href="sophos_status.php">Sophos Status</a>';
}
if (preg_match('/f-secure/i', get_conf_var('VirusScanners')) && $_SESSION['user_type'] == 'A') {
    echo '<li><a href="f-secure_status.php">F-Secure Status</a>';
}
if (preg_match('/clam/i', get_conf_var('VirusScanners')) && $_SESSION['user_type'] == 'A') {
    echo '<li><a href="clamav_status.php">ClamAV Status</a>';
}
if (preg_match('/mcafee/i', get_conf_var('VirusScanners')) && $_SESSION['user_type'] == 'A') {
    echo '<li><a href="mcafee_status.php">McAfee Status</a>';
}
if (preg_match('/f-prot/i', get_conf_var('VirusScanners')) && $_SESSION['user_type'] == 'A') {
    echo '<li><a href="f-prot_status.php">F-Prot Status</a>';
}
if ($_SESSION['user_type'] == 'A') {
    echo '<li><a href="mysql_status.php">MySQL Database Status</a>';
}
if ($_SESSION['user_type'] == 'A') {
    echo '<li><a href="msconfig.php">View MailScanner Configuration</a>';
}
if (!DISTRIBUTED_SETUP && get_conf_truefalse('UseSpamAssassin') && $_SESSION['user_type'] == 'A') {
    echo '
     <li><a href="bayes_info.php">SpamAssassin Bayes Database Info</a>
     <li><a href="sa_lint.php">SpamAssassin Lint (Test)</a>
     <li><a href="ms_lint.php">MailScanner Lint (Test)</a>
     <li><a href="sa_rules_update.php">Update SpamAssasin Rule Descriptions</a>';
}
if (!DISTRIBUTED_SETUP && get_conf_truefalse('MCPChecks') && $_SESSION['user_type'] == 'A') {
    echo '<li><a href="mcp_rules_update.php">Update MCP Rule Descriptions</a>';
}
if ($_SESSION['user_type'] == 'A') {
    echo '<li><a href="geoip_update.php">Update GeoIP Database</a>';
}
echo '</ul>';
if ($_SESSION['user_type'] == 'A') {
    echo '
   <p>Links</p>
   <ul>
    <li><a href="http://mailwatch.sourceforge.net">MailWatch for MailScanner</a>
    <li><a href="http://www.mailscanner.info">MailScanner</a>';

    if (get_conf_truefalse('UseSpamAssassin')) {
        echo '<li><a href="http://www.spamassassin.org">SpamAssassin</a>';
    }

    if (preg_match('/sophos/i', get_conf_var('VirusScanners'))) {
        echo '<li><a href="http://www.sophos.com">Sophos</a>';
    }

    if (preg_match('/clam/i', get_conf_var('VirusScanners'))) {
        echo '<li><a href="http://clamav.sourceforge.net">ClamAV</A>';
    }

    echo '
    <li><a href="http://www.dnsstuff.com">DNSstuff</a>
    <li><a href="http://www.samspade.org">Sam Spade</a>
    <li><a href="http://spam.abuse.net">spam.abuse.net</a>
    <li><a href="http://www.dnsreport.com">DNS Report</a>
   </ul>';
}

echo '
   </td>
 </tr>
</table>';

// Add footer
html_end();
// Close any open db connections
dbclose();
