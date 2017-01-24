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

// Require the functions page
require_once __DIR__ . '/functions.php';

// Start the session
session_start();
// Require the login function code
require __DIR__ . '/login.function.php';

// Start the header code and Title
html_start(__('spamassassinbayesdatabaseinfo18'), 0, false, false);

// Enter the Action in the Audit log
audit_log(__('auditlog18'));

// Create the table
echo '<table align="center" class="boxtable" border="0" cellspacing="1" cellpadding="1" width="690">';
// Add a Header to the table
echo '<tr><th colspan="2">' . __('bayesdatabaseinfo18') . '</th></tr>';

// Open the spamassassin file
if (!is_file(SA_DIR . 'sa-learn')) {
    die(__('cannotfind18') . ' ' . SA_DIR . 'sa-learn');
}
$fh = popen(SA_DIR . 'sa-learn -p ' . SA_PREFS . ' --dump magic', 'r');

while (!feof($fh)) {
    $line = rtrim(fgets($fh, 4096));

    debug('line: ' . $line . "\n");

    if (preg_match('/(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+non-token data: (.+)/', $line, $regs)) {
        switch ($regs[5]) {
            case 'nspam':
                echo '<tr><td class="heading">' . __('nbrspammessage18') . '</td><td align="right">' . number_format(
                        $regs[3]
                    ) . '</td></tr>';
                break;

            case 'nham':
                echo '<tr><td class="heading">' . __('nbrhammessage18') . '</td><td align="right">' . number_format(
                        $regs[3]
                    ) . '</td></tr>';
                break;

            case 'ntokens':
                echo '<tr><td class="heading">' . __('nbrtoken18') . '</td><td align="right">' . number_format(
                        $regs[3]
                    ) . '</td></tr>';
                break;

            case 'oldest atime':
                echo '<tr><td class="heading">' . __('oldesttoken18') . '</td><td align="right">' . date(
                        'r',
                        $regs[3]
                    ) . '</td></tr>';
                break;

            case 'newest atime':
                echo '<tr><td class="heading">' . __('newesttoken18') . '</td><td align="right">' . date(
                        'r',
                        $regs[3]
                    ) . '</td></tr>';
                break;

            case 'last journal sync atime':
                echo '<tr><td class="heading">' . __('lastjournalsync18') . '</td><td align="right">' . date(
                        'r',
                        $regs[3]
                    ) . '</td></tr>';
                break;

            case 'last expiry atime':
                echo '<tr><td class="heading">' . __('lastexpiry18') . '</td><td align="right">' . date('r', $regs[3]) . '</td></tr>';
                break;

            case 'last expire reduction count':
                echo '<tr><td class="heading">' . __('lastexpirycount18') . '</td><td align="right">' . number_format(
                        $regs[3]
                    ) . ' ' . __('tokens18') .'</td></tr>';
                break;
        }
    }
}

// Close the file
pclose($fh);

// End the table html tag
echo '</table>';

if ($_SESSION['user_type'] == 'A') {
    $return = 0;
    echo '  <div style="text-align: center; ">';
    echo '     <form method="post" action="bayes_info.php" onsubmit="return confirm(\'Are you sure you want to clear?\');" >';
    echo '           <br>';
    echo '           <input type="submit" value="' . __('cleardbbayes18') . '">';
    echo '           <input type="hidden" name="clear" value="true">';
    echo '           <br>';
    echo '     </form>';
    echo '  </div>';
    if (isset($_POST['clear'])) {
        if (!is_file(SA_DIR . 'sa-learn')) {
            echo '<div style="font-size: 10pt; font-weight: 700; text-align: center; color: red; ">';
            echo '<br>' . __('cannotfind18') . ' ' . SA_DIR . 'sa-learn';
            echo '</div>';
        } else {
            // Using --force-expire instead of --clear for testing purpose - TO REMOVE BEFORE PUBLISHING IT
            passthru(SA_DIR . 'sa-learn -p ' . SA_PREFS . ' --force-expire', $return);
            if ($return === 0) {
                audit_log(__('auditlogwipe18'));
                require_once __DIR__ . '/lib/request/Requests.php';
                Requests::register_autoloader();
            } else {
                echo '<div style="font-size: 10pt; font-weight: 700; text-align: center; color: red; ">';
                echo '<br>' . __('error18') . ' ' . $return;
                echo '</div>';
            }
        }
    }
    echo '<br>';
}

// Add footer
html_end();

// Close any open db connections
dbclose();
