#!/usr/bin/php -q
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

// Change the following to reflect the location of functions.php
require('/var/www/html/mailscanner/functions.php');

ini_set('error_log','syslog');
ini_set('html_errors','off');
ini_set('display_errors','on');
ini_set('implicit_flush','false');

// Cleaning the maillog table
if( RECORD_DAYS_TO_KEEP > 0) {
dbquery("DELETE LOW_PRIORITY FROM maillog WHERE timestamp < (now() - INTERVAL ".RECORD_DAYS_TO_KEEP." DAY)");
}else {
 echo "The variable RECORD_DAYS_TO_KEEP is empty, please give this a value.";
}
 
 // Cleaning the mta_log and optionally the mta_log_id table
 $sqlcheck = "Show tables like 'mtalog_ids'";
 $tablecheck = dbquery($sqlcheck);
if ($mta == 'postfix' && mysql_num_rows($tablecheck) > 0){ //version for postfix
dbquery("delete i.*, m.* from mtalog as m inner join mtalog_ids as i on i.smtp_id = m.msg_id where m.timestamp < (now() - INTERVAL ".RECORD_DAYS_TO_KEEP." DAY)");
}else{
dbquery("delete from mtalog where timestamp < (now() - INTERVAL ".RECORD_DAYS_TO_KEEP." DAY)");
}

// Clean the audit log
dbquery("DELETE FROM audit_log WHERE timestamp < (now() - INTERVAL ".AUDIT_DAYS_TO_KEEP." DAY)");

// Optimize all of tables
dbquery("OPTIMIZE TABLE maillog, mtalog, audit_log");

?>
