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
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace MailWatch;
require_once __DIR__ . '/functions.php';

require __DIR__ . '/login.function.php';

html_start(__('mqviewer24'), STATUS_REFRESH, false, false);

if (false === checkToken($_GET['token'])) {
    die(__('dietoken99'));
}

$queue = deepSanitizeInput($_GET['queue'], 'url');
if (!validateInput($queue, 'mailq')) {
    die(__('dievalidate99'));
}

switch ($queue) {
    case 'inq':
        $queue = 'inq';
        $display = __('inq24');
        break;
    case 'outq':
        $queue = 'outq';
        $display = __('outq24');
        break;
    default:
        die(__('diemq24') . "\n");
}

db_colorised_table(
    "
    SELECT
     id AS id2,
     CONCAT(DATE_FORMAT(cdate, '" . DATE_FORMAT . "'),' ',ctime) AS datetime,
 hostname,
 from_address,
 to_address,
 subject,
 message,
 size,
 attempts,
 CASE WHEN lastattempt=0 THEN '00:00:00' ELSE SEC_TO_TIME((UNIX_TIMESTAMP() - lastattempt)) END AS lastattempt
FROM
 " . $queue . '
WHERE
 ' . $_SESSION['global_filter'] . '
ORDER BY
 cdate, ctime',
    $display,
    true,
    true
);

// Add the footer
html_end();
// close the connection to the Database
dbclose();
