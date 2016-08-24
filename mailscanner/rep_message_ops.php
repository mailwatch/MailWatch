<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2016  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
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
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// Include of necessary functions
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/filter.inc.php');

// Authentication checking
session_start();
require(__DIR__ . '/login.function.php');

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
" . $_SESSION["filter"]->CreateSQL();

// Hide high spam/mcp from regular users if enabled
if (defined('HIDE_HIGH_SPAM') && HIDE_HIGH_SPAM === true && $_SESSION['user_type'] == 'U') {
    $sql .= "
    AND
     ishighspam=0
    AND
     COALESCE(ishighmcp,0)=0";
}

$sql .= "
 ORDER BY
  date DESC, time DESC
";

// function to display the data from functions.php
db_colorised_table($sql, __('messageops17'), true, true, "SPAM");

// Add footer
html_end();
// Close any open db connections
dbclose();
