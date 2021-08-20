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

require_once __DIR__ . '/filter.inc.php';
require_once __DIR__ . '/functions.php';

require __DIR__ . '/login.function.php';

$refresh = html_start(__('opresult21'));

if ($_SESSION['token'] !== deepSanitizeInput($_POST['token'], 'url')) {
    header('Location: login.php?error=pagetimeout');
    die();
}
if (false === checkFormToken('/do_message_ops.php form token', $_POST['formtoken'])) {
    header('Location: login.php?error=pagetimeout');
    die();
}

echo '<table border="0" width="100%" class="mail" cellspacing="2" align="center">' . "\n";
echo ' <tr>' . "\n";
echo '  <th colspan="3">' . __('spamlearnresult21') . '</th>' . "\n";
echo ' </tr>' . "\n";
echo ' <tr>' . "\n";
echo '  <th colspan="1">' . __('messageid21') . '</th>' . "\n";
echo '  <th colspan="1">' . __('result21') . '</th>' . "\n";
echo '  <th colspan="1">' . __('message21') . '</th>' . "\n";
echo ' </tr>' . "\n";

// Iterate through the POST variables
unset($_POST['SUBMIT'], $_POST['token'], $_POST['formtoken']);
if (isset($_POST) && !empty($_POST)) {
    foreach ($_POST as $k => $v) {
        if (preg_match('/^OPT-(.+)$/', $k, $Regs)) {
            $id = deepSanitizeInput($Regs[1], 'url');
            $id = fixMessageId($id);
            if (!validateInput($id, 'msgid')) {
                die();
            }
        } elseif (preg_match('/^OPTRELEASE-(.+)$/', $k, $Regs)) {
            $id = deepSanitizeInput($Regs[1], 'url');
            $id = fixMessageId($id);
            if (!validateInput($id, 'msgid')) {
                die();
            }
        } else {
            continue;
        }
        switch (deepSanitizeInput($v, 'url')) {
            case 'S':
                $type = 'spam';
                break;
            case 'H':
                $type = 'ham';
                break;
            case 'F':
                $type = 'forget';
                break;
            case 'R':
                $type = 'release';
                break;
            default:
                continue 2; //continue with next foreach loop
        }
        $items = quarantine_list_items($id, RPC_ONLY);
        echo '<tr>' . "\n";
        echo '<td><a href="detail.php?token=' . $_SESSION['token'] . '&amp;id=' . $id . '">' . $id . '</a></td>';
        echo '<td>' . $type . '</td>';
        if (empty($items)) {
            echo '<td class="error">' . __('diemnf21') . '</td>' . "\n";
        } elseif (is_string($items)) {
            echo '<td class="error">' . $items . '</td>' . "\n";
        } else {
            if (count($items) > 0) {
                $num = 0;
                $itemnum = [$num];
                echo '<td>';
                if ($type === 'release') {
                    $quarantined = quarantine_list_items($id, RPC_ONLY);
                    if (is_array($quarantined)) {
                        $to = $quarantined[0]['to'];
                        echo quarantine_release(
                            $quarantined,
                            $itemnum,
                            $to,
                            RPC_ONLY
                        );
                    } else {
                        echo $quarantined;
                    }
                } else {
                    echo quarantine_learn(
                        $items,
                        $itemnum,
                        $type,
                        RPC_ONLY
                    );
                }
                echo '</td>' . "\n";
            }
        }
        echo '</tr>' . "\n";
    }
} else {
    echo '<tr><td colspan="3">' . __('diemnf21') . '</td></tr>' . "\n";
}
echo ' </table>' . "\n";
echo '<p class="center"><a href="javascript:history.back(1)">' . __('back21') . '</a></p><br>' . "\n";

//Add footer
html_end();
//Close database connection
dbclose();
