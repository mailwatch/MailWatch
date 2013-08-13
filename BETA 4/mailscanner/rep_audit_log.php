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
require_once("./filter.inc");

// Authenication checking
session_start();
require('login.function.php');

// If the user isn't an administrator to send them back to the main page
if($_SESSION['user_type'] !=A){
header("Location: index.php");
}
else{

// add the header information such as the logo, search, menu, ....
$filter=html_start("Audit Log",0,false,true);

// SQL query for the audit log
$sql = "
 SELECT
  DATE_FORMAT(a.timestamp,'".DATE_FORMAT." ".TIME_FORMAT."') AS 'Date/Time',
  b.fullname AS 'User',
  a.ip_address AS 'IP Address',
  a.action AS 'Action'
 FROM
  audit_log a,
  users b
 WHERE
  a.user=b.username
 AND
  1=1
".$filter->CreateMtalogSQL()."
 ORDER BY timestamp DESC";
 echo '<table border="0" cellpadding="10" cellspacing="0" width="100%">
 <tr><td align="center"><img src="".IMAGES_DIR."/mailscannerlogo.gif" alt="MailScanner Logo"></td></tr>
 <tr><td>'."\n";

 // Function to to query and display the data
 dbtable($sql,"Audit Log",true);
 
 // close off the table
 echo '</td></tr>
      </table>'."\n";
  
// Add footer
html_end();
// Close any open db connections
dbclose();
}
?>
