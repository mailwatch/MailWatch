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

// Require the functions page
require_once __DIR__ . '/functions.php';

// Require the login function code
require __DIR__ . '/login.function.php';

// Check to see if the user is an administrater
if ($_SESSION['user_type'] !== 'A') {
    // If the user isn't an administrater send them back to the index page.
    header('Location: index.php');
    audit_log(__('auditlog19', true));
} else {
    // Start the header code and Title
    html_start(__('avclamavstatus19'), 0, false, false);
    // Create the table
    echo '<table class="boxtable" width="100%">';
    echo '<tr>';
    echo '<td align="center">';

    // Obtain ClamAV and pattern versions from clamscan
    exec("which clamscan", $clamscan);
    if (isset($clamscan[0])) {
        passthru("$clamscan[0] -V | awk -f " . __DIR__ . '/clamav.awk');
    }
    echo '</td>';
    echo '</tr>';
    echo '</table>';

    // Add footer
    html_end();
    // Close any open db connections
    dbclose();
}
