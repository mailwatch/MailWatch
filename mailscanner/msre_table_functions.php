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

// table_functions.php
// table functions for MSRE

function TR()
{
    // Writes a table row using row_data to fill the td's.
    // nothing else is needed, since colors and fonts will be
    // determined by the style sheet.
    //
    // Optionally a 2nd parameter may be provided, tr_param,
    // which can contain any parameters to the <tr> tag..

    $arg = func_get_args();

    if (count($arg) > 1) {
        list($row_data, $tr_param) = $arg;
    } else {
        $row_data = $arg[0];
        $tr_param = null;
    }

    echo '<tr';
    if ($tr_param) {
        echo ' ' . $tr_param;
    }
    echo '>' . "\n";
    foreach ($row_data as $td_data) {
        echo "\t" . '<td>' . $td_data . '</td>' . "\n";
    }
    echo '</tr>' . "\n";
}

function TR_Single($td_data, $td_param)
{
    // Writes a table row with a single <td>.
    // accepts td_param as parameters to the td...
    // nothing else is needed, since colors and fonts will be
    // determined by the style sheet.
    echo '<tr>' . "\n";
    echo "\t" . '<td';
    if ($td_param) {
        echo ' ' . $td_param;
    }
    echo '>' . $td_data . '</td>' . "\n";
    echo '</tr>' . "\n";
}

function TRH($row_data)
{
    // Similar to TR, but writes <th>'s instead of <td>'s
    echo '<tr>' . "\n";
    foreach ($row_data as $th_data) {
        echo "\t" . '<th>' . $th_data . '</th>' . "\n";
    }
    echo '</tr>' . "\n";
}

function TRH_Single($th_data, $th_param)
{
    // Writes a table header row with a single <th>.
    // accepts td_param as parameters to the td...
    // nothing else is needed, since colors and fonts will be
    // determined by the style sheet.
    echo '<tr>' . "\n";
    echo "\t" . '<th';
    if ($th_param) {
        echo ' ' . $th_param;
    }
    echo '>' . $th_data . '</th>' . "\n";
    echo '</tr>' . "\n";
}

function TR_Extended($row_data, $tr_param)
{
    // Writes a table row using row_data to fill the td's
    // each element of row_data will become a separate td.
    // row_data is a keyed array (hash) , consisting
    // of a key that contains the data to go into the <td>,
    // and the value containing any parameters to the <td>
    //
    // Example:
    //	$tablerow = array (
    //		"some text" => "bgcolor=\"#aaaaaa\" align=\"center\"",
    //		"more text" => ""
    //	);
    //	TR ($tablerow, "bgcolor=\"#ffffff\"");
    //
    // The result would be a table with one cell with a grey background
    // that says "some text", and one with a white bg that says
    // "more text"  (because the 2nd td didn't specify a bgcolor, and
    // the tr did)

    // Start out by making the initial <tr>
    $my_tr = '<tr';
    if ($tr_param) {
        //echo "tr param: $tr_param<br>\n";
        $my_tr .= ' ' . $tr_param;
    }
    $my_tr .= '>' . "\n";
    echo $my_tr;

    // Now the <td>'s
    $td_param = [];
    foreach ($row_data as $td_text => $td_param) {
        $my_td = "\t" . '<td';
        if ($td_param) {
            //echo "td_param: $td_param<br>\n";
            $my_td .= ' ' . $td_param;
        }
        $my_td .= '>' . $td_text . '</td>' . "\n";
        echo $my_td;
    }

    echo '</tr>' . "\n";
    return 0;
}
