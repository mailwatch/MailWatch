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

require_once __DIR__ . '/functions.php';

require __DIR__ . '/login.function.php';

$refresh = html_start(__('recentmsg05'), STATUS_REFRESH, false, false);

$sql = "
SELECT
 id AS id2,
 hostname AS host,
 DATE_FORMAT(timestamp, '" . DATE_FORMAT . ' ' . TIME_FORMAT . "') AS datetime,
 from_address,";
if (defined('DISPLAY_IP') && DISPLAY_IP) {
    $sql .= 'clientip,';
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
 released,
 salearn,
 '' AS status
FROM
 maillog
WHERE
 " . $_SESSION['global_filter'];
// Hide high spam/mcp from regular users if enabled
if (defined('HIDE_HIGH_SPAM') && HIDE_HIGH_SPAM === true && $_SESSION['user_type'] === 'U') {
    $sql .= '
    AND
     ishighspam=0
    AND
     COALESCE(ishighmcp,0)=0';
}
$sql .= '  
ORDER BY
 date DESC,
 time DESC
LIMIT ' . MAX_RESULTS;

db_colorised_table($sql, __('last05') . ' ' . MAX_RESULTS . ' ' . __('messages05') . ' (' . __('refevery05') . " $refresh " . __('seconds05') . ')');

// Add footer
html_end();
// Close any open db connections
dbclose();
