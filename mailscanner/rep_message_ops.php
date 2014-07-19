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

// Include of necessary functions
require_once("./functions.php");
require_once("MDB2.php");
require_once("Pager/Pager.php");
require_once("./filter.inc");

// Authentication checking
session_start();
require('login.function.php');

// add the header information such as the logo, search, menu, ....
$filter = html_start("Message Listing", 0, false, true);

// Checks to see if you are looking for quarantined files only
if (QUARANTINE_USE_FLAG) {
    $flag_sql = "quarantined=1";
} else {
    $flag_sql = "1=1";
}

// SQL query
$sql = "
 SELECT
  id AS id2,
  DATE_FORMAT(timestamp, '" . DATE_FORMAT . " " . TIME_FORMAT . "') AS datetime,
  from_address,";
if (defined('DISPLAY_IP') && DISPLAY_IP) {
    $sql .= "clientip,";
}
$sql .= "
  to_address,
  subject,
  size,
  isspam,
  ishighspam,
  isrblspam,
  spamwhitelisted,
  spamblacklisted,
  virusinfected,
  nameinfected,
  otherinfected,
  sascore,
  report,
  ismcp,
  ishighmcp,
  mcpwhitelisted,
  mcpblacklisted,
  mcpsascore,
  '' AS status
 FROM
  maillog
 WHERE
  $flag_sql
" . $_SESSION["filter"]->CreateSQL() . "
 ORDER BY
  date DESC, time DESC
";

// function to display the data from functions.php
db_colorised_table($sql, 'Message Operations', true, true, "SPAM");

// Add footer
html_end();
// Close any open db connections
dbclose();
