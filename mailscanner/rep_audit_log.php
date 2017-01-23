<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2017  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filter.inc.php';

// Authentication checking
session_start();
require __DIR__ . '/login.function.php';

// If the user isn't an administrator to send them back to the main page
if ($_SESSION['user_type'] !== 'A') {
    header('Location: index.php');
} else {
    // add the header information such as the logo, search, menu, ....
    $filter = html_start(__('auditlog33'), 0, false, true);

    $auditFilter = '';
    $startDate=filter_input(INPUT_GET,'startDate');
    $endDate=filter_input(INPUT_GET,'endDate');
    $username=filter_input(INPUT_GET,'username');
    $ipaddress=filter_input(INPUT_GET,'ipaddress',FILTER_VALIDATE_IP);
    $actions=filter_input(INPUT_GET,'actions');
    if($startDate === null || $startDate == '') {
        $startDate = '';
    } else {
        $auditFilter .= ' AND a.timestamp >= "' . safe_value($startDate) . '"';
    }
    if($endDate === null || $endDate == '') {
        $endDate = '';
    } else {
        $auditFilter .= ' AND a.timestamp <= "' . safe_value($endDate) . '"';
    }
    if($username === null || $username == '') {
        $username = '';
    } else {
        $auditFilter .= ' AND b.username = "' . safe_value($username) . '"';
    }
    if($ipaddress === null || $ipaddress == '') {
        $ipaddress = '';
    } else {
        $auditFilter .= ' AND a.ip_address = "' . safe_value($ipaddress) . '"';
    }
    if($actions === null || $actions == '') {
        $actions = '';
    } else {
        $auditFilter .= ' AND a.action like "%' . safe_value($actions) . '%"';
    }

    // SQL query for the audit log
    $sql = "
 SELECT
  DATE_FORMAT(a.timestamp,'" . DATE_FORMAT . ' ' . TIME_FORMAT . "') AS '" . __('datetime33') . "',
  b.fullname AS '" . __('user33') . "',
  a.ip_address AS '" . __('ipaddress33') . "',
  a.action AS '" . __('action33') . "'
 FROM
  audit_log a,
  users b
 WHERE
  a.user=b.username
" . $auditFilter . '
 ORDER BY timestamp DESC';

    echo '<table border="0" cellpadding="10" cellspacing="0" width="100%">
 <tr><td>
<form action="rep_audit_log.php" method="GET" class="floatleft">
<div class="mail table" id="auditFilters">
  <div class="caption head">' . __('filter33') . <button type="submit">' . __('applyfilter33') . '</button></div>
  <div class="row"><div class="cell head">' . __('startdate33') . '</div><div class="cell data"><input name="startDate" type="datetime-local" value="' . $startDate . '"/></div></div>
  <div class="row"><div class="cell head">' . __('enddate33') . '</div><div class="cell data"><input name="endDate" type="datetime-local" value="' . $endDate . '"/></div></div>
  <div class="row"><div class="cell head">' . __('user33') . '</div><div class="cell data"><input name="username" type="text" value="' . $username . '"/></div></div>
  <div class="row"><div class="cell head">' . __('ipaddress33') . '</div><div class="cell data"><input name="ipaddress" type="text" value="' . $ipaddress . '"/></div></div>
  <div class="row"><div class="cell head">' . __('action33') . '</div><div class="cell data"><input name="actions" type="text" value="' . $actions . '"/></div></div>
</div>
</form>';

echo '<div class="center"><img src="' . IMAGES_DIR . MS_LOGO . '" alt="' .  __('mslogo99') . '"></div>';

echo '</td></tr>
<tr><td>' . "\n";

    // Function to to query and display the data
    dbtable($sql, __('auditlog33'), true);

    // close off the table
    echo '</td></tr>
      </table>' . "\n";

    // Add footer
    html_end();
    // Close any open db connections
    dbclose();
}
