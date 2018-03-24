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

// add the header information such as the logo, search, menu, ....
$filter = html_start(__('mcprulehits34'), 0, false, true);

$sql = '
 SELECT
  mcpreport,
  ismcp
 FROM
  maillog
 WHERE
  mcpreport IS NOT NULL
 AND mcpreport != ""
' . $filter->CreateSQL();

$result = dbquery($sql);
if (!$result->num_rows > 0) {
    die(__('diemysql99') . "\n");
}

// Initialise the array
$sa_array = array();

// Retrieve rows and insert into array
while ($row = $result->fetch_object()) {
    // Clean-up input
    $row->mcpreport = preg_replace('/\n/', '', $row->mcpreport);
    $row->mcpreport = preg_replace('/\t/', ' ', $row->mcpreport);
    preg_match('/ \((.+?)\)/i', $row->mcpreport, $sa_rules);
    // Get rid of first match from the array
    $junk = array_shift($sa_rules);
    // Split the array, and get rid of the score and required values
    $sa_rules = explode(', ', $sa_rules[0]);
    $junk = array_shift($sa_rules); // score=
    $junk = array_shift($sa_rules); // required
    foreach ($sa_rules as $rule) {
        // Check if SA scoring is present
        if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
            $rule = $regs[1];
        }
        $sa_array[$rule]['total']++;
        if ($row->ismcp !== '0') {
            $sa_array[$rule]['mcp']++;
        } else {
            $sa_array[$rule]['not-mcp']++;
        }
        // Initialise the other dimensions of the array
        if (!$sa_array[$rule]['mcp']) {
            $sa_array[$rule]['mcp'] = 0;
        }
        if (!$sa_array[$rule]['not-mcp']) {
            $sa_array[$rule]['not-mcp'] = 0;
        }
    }
}

reset($sa_array);
arsort($sa_array);

echo '<table border="0" cellpadding="10" cellspacing="0" width="100%">
 <tr><td align="center">
 <table class="boxtable" align="center" border="0">
 <tr bgcolor="#F7CE4A">
 <th>' . __('rule34') . '</th>
 <th>' . __('des34') . '</th>
 <th>' . __('total34') . '</th>
 <th>' . __('clean34') . '</th>
 <th>%</th>
 <th>' . __('mcp34') . '</th>
 <th>%</th>
 </tr>' . "\n";
foreach ($sa_array as $key => $val) {
    if ($count >= 10) {
        break;
    }
    echo '
<tr bgcolor="#ebebeb">
 <td>' . $key . '</td>
 <td>' . return_mcp_rule_desc(strtoupper($key)) . '</td>
 <td align="right">' . number_format($val['total']) . '</td>
 <td align="right">' . number_format($val['not-mcp']) . '</td>
 <td align="right">' . round(($val['not-mcp'] / $val['total']) * 100, 1) . '</td>
 <td align="right">' . number_format($val['mcp']) . '</td>
 <td align="right">' . round(($val['mcp'] / $val['total']) * 100, 1) . '</td>
 </tr>' . "\n";
}
echo '
  </table>
 </td>
</tr>
</table>';

// Add footer
html_end();
// Close any open db connections
dbclose();
