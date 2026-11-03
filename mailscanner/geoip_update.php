<?php

/**
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2021  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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

// Require files
require_once __DIR__ . '/functions.php';

// Authentication verification
require __DIR__ . '/login.function.php';

if ('A' !== $_SESSION['user_type']) {
    header('Location: login.php');
    exit;
}

html_start(__('geoipupdate15'), 0, false, false);

if (!defined('MAXMIND_LICENSE_KEY') || !validateInput(MAXMIND_LICENSE_KEY, 'maxmind')) {
    $error_message = __('geoipnokey15') . '<br>' . "\n";
    exit($error_message);
} elseif (!isset($_POST['run'])) {
    echo '<form method="POST" action="geoip_update.php">
            <input type="hidden" name="run" value="true">
            <table class="boxtable" width="100%">
            <tr><th>';
    echo __('updategeoip15');
    echo '</th></tr>
               <tr>
                   <td>
                    <br>
                       ' . __('message115') . ' <a href="https://dev.maxmind.com/geoip/geolite2-free-geolocation-data" target="_maxmind">MaxMind</a> ' . __('message215') . '<br><br>
                   </td>
               </tr>
               <tr>
                   <td align="center"><br><input type="SUBMIT" value="' . __('input15') . '"><br><br></td>
               </tr>
            </table>
            </form>' . "\n";
} else {
    require_once __DIR__ . '/geoip_update.inc.php';

    ob_start();
    $result = geoip_update_download(__DIR__ . '/temp/', '<br>' . "\n");
    if (true !== $result) {
        exit($result);
    }

    echo __('processok15') . "\n";
    ob_flush();
    flush();
    audit_log(__('auditlog15', true));
}

// Add the footer
html_end();
// Close the connection to the Database
dbclose();
