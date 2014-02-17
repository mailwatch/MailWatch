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

// Require the functions page
require_once("./functions.php");

// Start the session
session_start();
// Require the login function code
require('./login.function.php');

// Check to see if the user is an administrator
if($_SESSION['user_type'] != 'A'){
// If the user isn't an administrator send them back to the index page.
header("Location: index.php");
audit_log('Non-admin user attemped to view ClamAV Status page');
}
else{

// Start the header code and Title
html_start("ClamAV Status",0,false,false);

// Create the table
echo '<table class="boxtable" width="100%">';
echo '<tr>';
echo '<td align="center">';

// Output the information from the conf file
 passthru(get_virus_conf('clamav')." -V | awk -f ./clamav.awk");

 echo '</td>';
echo '</tr>';
echo '</table>';

// Add footer
html_end();
// Close any open db connections
dbclose();
}
