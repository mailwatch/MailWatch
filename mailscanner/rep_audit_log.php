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

// Include of necessary functions
require_once __DIR__ . '/filter.inc.php';
require_once __DIR__ . '/functions.php';

// Authentication checking
require __DIR__ . '/login.function.php';

// If the user isn't an administrator to send them back to the main page
if ($_SESSION['user_type'] !== 'A') {
    header('Location: index.php');
} else {
    // add the header information such as the logo, search, menu, ....
    html_start(__('auditlog33'), 0, false, false);
    if (isset($_POST['token'])) {
        if (false === checkToken($_POST['token'])) {
            header('Location: login.php?error=pagetimeout');
            die();
        }
    } else {
        if (false === checkToken($_GET['token'])) {
            header('Location: login.php?error=pagetimeout');
            die();
        }
    }

    if (isset($_GET['pageID']) && !validateInput(deepSanitizeInput($_GET['pageID'], 'num'), 'num')) {
        die(__('dievalidate99'));
    }

    $auditFilter = '';
    $startDate='';
    $endDate='';
    $ipaddress = '';
    $actions ='';
    $username ='';
    if (isset($_POST['formtoken'])) {
        if (false === checkFormToken('/rep_audit_log.php form token', $_POST['formtoken'])) {
            header('Location: login.php?error=pagetimeout');
            die();
        }
        if (isset($_POST['startDate'])) {
            $startDate=deepSanitizeInput($_POST['startDate'], 'url');
            if ($startDate !== '' && $startDate !== null && !validateInput($startDate, 'date')) {
                $startDate = '';
            }
        }
        if (isset($_POST['endDate'])) {
            $endDate=deepSanitizeInput($_POST['endDate'], 'url');
            if ($endDate !== '' && $endDate !== null && !validateInput($endDate, 'date')) {
                $endDate = '';
            }
        }
        if (isset($_POST['username'])) {
            $username=deepSanitizeInput($_POST['username'], 'string');
            if ($username !== '' && $username !== null && !validateInput($username, 'user')) {
                $username = '';
            }
        }
        if (isset($_POST['ipaddress'])) {
            $ipaddress=deepSanitizeInput($_POST['ipaddress'], 'url');
            if (!validateInput($ipaddress, 'ip')) {
                $ipaddress = '';
            }
        }
        if (isset($_POST['actions'])) {
            $actions=deepSanitizeInput($_POST['actions'], 'string');
            if ($actions !== '' && $actions !== null && !validateInput($actions, 'general')) {
                $actions = '';
            }
        }
    }
    if ($startDate !== '') {
        $auditFilter .= ' AND a.timestamp >= "' . safe_value($startDate) . ' 00:00:00"';
    }
    if ($endDate !== '') {
        $auditFilter .= ' AND a.timestamp <= "' . safe_value($endDate) . ' 23:59:59"';
    }
    if ($username !== '') {
        $auditFilter .= ' AND b.username = "' . safe_value($username) . '"';
    }
    if ($ipaddress !== '') {
        $auditFilter .= ' AND a.ip_address = "' . safe_value($ipaddress) . '"';
    }
    if ($actions !== '') {
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
  audit_log AS a
 LEFT JOIN
  users AS b ON a.user=b.username
  WHERE 1=1
" . $auditFilter . '
 ORDER BY timestamp DESC';

    echo '<table border="0" cellpadding="10" cellspacing="0" width="100%">
 <tr><td>
  <form action="rep_audit_log.php" method="POST" class="floatleft">' . "\n";
    echo '<INPUT TYPE="HIDDEN" NAME="token" VALUE="' . $_SESSION['token'] . '">' . "\n";
    echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . generateFormToken('/rep_audit_log.php form token') . '">' . "\n";
    echo '<div class="mail table" id="auditFilters">
      <div class="caption head">' . __('filter33') . '</div>
      <div class="row"><div class="cell head">' . __('startdate33') . '</div><div class="cell data"><input name="startDate" type="text" placeholder="YYYY-MM-DD" value="' . $startDate . '"/></div></div>
      <div class="row"><div class="cell head">' . __('enddate33') . '</div><div class="cell data"><input name="endDate" type="text" placeholder="YYYY-MM-DD" value="' . $endDate . '"/></div></div>
      <div class="row"><div class="cell head">' . __('user33') . '</div><div class="cell data"><input name="username" type="text" value="' . $username . '"/></div></div>
      <div class="row"><div class="cell head">' . __('ipaddress33') . '</div><div class="cell data"><input name="ipaddress" type="text" value="' . $ipaddress . '"/></div></div>
      <div class="row"><div class="cell head">' . __('action33') . '</div><div class="cell data"><input name="actions" type="text" value="' . $actions . '"/></div></div>
      <div class="row"><div class="cell head"></div><div class="cell head"><button type="submit">' . __('applyfilter33') . '</button></div></div>
    </div>
  </form>
</td></tr>
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
