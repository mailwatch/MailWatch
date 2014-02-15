<?php

/*
 MailWatch for MailScanner
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

// Include of necessary functions
require_once("./functions.php");

// Authentication checking
session_start();
require('login.function.php');

if($_SESSION['user_type'] != 'A'){
header("Location: index.php");
audit_log('Non-admin user attemped to view Software Version Page');
}else{

html_start('Mailwatch and MailScanner Version information', '0', false, false);
$mailwatch_version = mw_version();
$mailscanner_version = get_conf_var('MailScannerVersionNumber');
$php_version = funcs_phpversion();
$mysql_version = dbquery("SELECT VERSION()");

echo '<table width="100%" class="boxtable">'."\n";
echo '<tr>'."\n";
echo '<td>'."\n";

echo '<p class="center" style="font-size:20px"><b>Software Versions</b></p>'."\n";
echo 'MailWatch Version = '.$mailwatch_version.'<br>'."\n";
echo '<br>'."\n";
echo 'MailScanner Version = '.$mailscanner_version.'<br>'."\n";
echo '<br>'."\n";
echo 'PHP Version = '.$php_version.'<br>'."\n";
echo '<br>'."\n";
echo 'MySQL Version = '.mysql_result($mysql_version,0).'<BR>'."\n";

echo '</td>'."\n";
echo '</tr>'."\n";
echo '</table>'."\n";

// Add footer
html_end();
// Close any open db connections
dbclose();
}
