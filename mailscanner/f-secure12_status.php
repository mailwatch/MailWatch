<?php

/*
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

// Include of necessary functions
require_once __DIR__ . '/functions.php';

// Authentication checking
require __DIR__ . '/login.function.php';

if ('A' !== $_SESSION['user_type']) {
    header('Location: index.php');
} else {
    html_start(__('fsecurestatus23'));

    echo '
<table class="boxtable" style="width: 100%">
 <tr>
  <td style="text-align: center">';

    $re = '@.*F-Secure Corporation Aquarius/(?<aquarius_version>.*)/(?<aquarius_date>.*)\s.*F-Secure Corporation Hydra/(?<hydra_version>.*)/(?<hydra_date>.*)\sF-Secure Corporation FMLib/(?<fmlib_version>.*)/(?<fmlib_date>.*)\sfsicapd/(?<fsicapd>.*)@m';

    $output = shell_exec('/opt/f-secure/linuxsecurity/bin/fsanalyze ' . escapeshellarg(__DIR__ . '/notexistingfile.txt'));

    // --FOR TESTING--
    // $output = shell_exec('cat ' . escapeshellarg(__DIR__ . '/../tests/fixtures/assets/antivirus/fsecure12.txt'));

    preg_match_all($re, $output, $matches, PREG_SET_ORDER, 0);

    echo '<table class="sophos" cellpadding="1" cellspacing="1">';
    echo '<tr><th colspan="3">F-Secure 12 Information</th></tr>';
    echo '<tr><th>Engine</th><th>Version</th><th>Date</th></tr>';
    echo '<tr><td>Aquarius</td><td>' . $matches[0]['aquarius_version'] . '</td><td>' . $matches[0]['aquarius_date'] . '</td></tr>';
    echo '<tr><td>Hydra</td><td>' . $matches[0]['hydra_version'] . '</td><td>' . $matches[0]['hydra_date'] . '</td></tr>';
    echo '<tr><td>FMLib</td><td>' . $matches[0]['fmlib_version'] . '</td><td>' . $matches[0]['fmlib_date'] . '</td></tr>';
    echo '<tr><td>fsicapd</td><td>' . $matches[0]['fsicapd'] . '</td><td>&nbsp;</td></tr>';
    echo '</table>';

    echo '
 </td>
 </tr>
</table>';

    // Add footer
    html_end();
    // Close any open db connections
    dbclose();
}
