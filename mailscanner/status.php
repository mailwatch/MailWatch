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

require_once("./functions.php");

session_start();
require('login.function.php');

$refresh = html_start("Recent Messages", STATUS_REFRESH, false, false);

$sql = "
SELECT
 id AS id2,
 hostname AS host,
 DATE_FORMAT(timestamp, '" . DATE_FORMAT . " " . TIME_FORMAT . "') AS datetime,
 from_address,";
if (defined('DISPLAY_IP') && DISPLAY_IP) {
    $sql .= "clientip,";
}
$sql .= "
 to_address,
 subject,
 size as size,
 isspam,
 ishighspam,
 spamwhitelisted,
 spamblacklisted,
 virusinfected,
 nameinfected,
 otherinfected,
 sascore,
 report,
 ismcp,
 issamcp,
 ishighmcp,
 mcpsascore,
 '' AS status
FROM
 maillog
WHERE
 " . $_SESSION['global_filter'] . "
ORDER BY
 date DESC,
 time DESC
LIMIT " . MAX_RESULTS;

db_colorised_table($sql, "Last " . MAX_RESULTS . " Messages (Refreshing every $refresh seconds)");

// Add footer
html_end();
// Close any open db connections
dbclose();
