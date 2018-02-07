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

if ($_SESSION['user_type'] !== 'A') {
    header('Location: index.php');
} else {
    html_start(__('rules30'));

    // Limit accessible files to the ones in MailScanner etc directory or reports directory
    $MailscannerRepDir = realpath(get_conf_var('%report-dir%'));
    $MailscannerEtcDir = realpath(get_conf_var('%etc-dir%'));
    if (!isset($_GET['file'])) {
        $FilePath = false;
    } else {
        $FilePath = realpath(sanitizeInput($_GET['file']));
    }

    if ($FilePath === false || (strpos($FilePath, $MailscannerEtcDir) !== 0 && strpos($FilePath, $MailscannerRepDir) !== 0)) {
        // Directory Traversal
        echo __('dirblocked30') . "\n";
    } else {
        echo '<table cellspacing="1" class="maildetail" width="100%">' . "\n";
        echo '<tr><td class="heading">' . __('file30') . ' ' . $FilePath . '</td></tr>' . "\n";
        echo '<tr><td><pre>' . "\n";
        if ($fh = @fopen($FilePath, 'rb')) {
            while (!feof($fh)) {
                $line = rtrim(fgets($fh, 4096));
                if (isset($_GET['strip_comments']) && $_GET['strip_comments']) {
                    if (!preg_match('/^#/', $line) && !preg_match('/^$/', $line)) {
                        echo $line . "\n";
                    }
                } else {
                    echo $line . "\n";
                }
            }
            fclose($fh);
        } else {
            echo __('unableopenfile30') . "\n";
        }
        echo '</pre></td></tr>' . "\n";
        echo '</table>' . "\n";
    }
    // Add the footer
    html_end();
}

dbclose();
