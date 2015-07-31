<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2015  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
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

// Include of necessary functions
require_once("./functions.php");
require_once("./filter.inc");

// Authentication checking
session_start();
require('login.function.php');

// add the header information such as the logo, search, menu, ....
$filter = html_start("SpamAssassin Rule Hits", 0, false, true);

$sql = "
 SELECT
  spamreport,
  isspam
 FROM
  maillog
 WHERE
  spamreport IS NOT NULL
 AND spamreport != \"\"
" . $filter->CreateSQL();

$result = dbquery($sql);
if (!mysql_num_rows($result) > 0) {
    die("Error: no rows retrieved from database\n");
}

// Initialise the array
$sa_array = array();

// Retrieve rows and insert into array
while ($row = mysql_fetch_object($result)) {
    //##### TODEL/TODO #
    //##### TODEL/TODO # stdClass Object
    //##### TODEL/TODO # (
    //##### TODEL/TODO # [spamreport] => not spam (too large)
    //##### TODEL/TODO # [isspam] => 0
    //##### TODEL/TODO #)
    //##### TODEL/TODO #
    //##### TODEL/TODO # printf("<pre>\n");print_r($row);printf("</pre>\n");
    //##### TODEL/TODO #
    preg_match('/SpamAssassin \((.+?)\)/i', $row->spamreport, $sa_rules);
    // Get rid of first match from the array
    $junk = array_shift($sa_rules);
    // Split the array, and get rid of the score and required values
    if (isset($sa_rules[0])) {
        $sa_rules = explode(", ", $sa_rules[0]);
    } else {
        $sa_rules = array();
    }
    $junk = array_shift($sa_rules); // score=
    $junk = array_shift($sa_rules); // required
    foreach ($sa_rules as $rule) {
        // Check if SA scoring is present
        if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
            $rule = $regs[1];
        }
        if (isset($sa_array[$rule]['total'])) {
            $sa_array[$rule]['total']++;
        } else {
            $sa_array[$rule]['total'] = 1;
        }

        // Initialise the other dimensions of the array
        if (!isset($sa_array[$rule]['spam'])) {
            $sa_array[$rule]['spam'] = 0;
        }
        if (!isset($sa_array[$rule]['not-spam'])) {
            $sa_array[$rule]['not-spam'] = 0;
        }

        if ($row->isspam <> 0) {
            $sa_array[$rule]['spam']++;
        } else {
            $sa_array[$rule]['not-spam']++;
        }
    }
}

reset($sa_array);
arsort($sa_array);

echo "<TABLE BORDER=\"0\" CELLPADDING=\"10\" CELLSPACING=\"0\" WIDTH=\"100%\">";
echo "<TR><TD ALIGN=\"CENTER\"><IMG SRC=\"" . IMAGES_DIR . MS_LOGO . "\" ALT=\"MailScanner Logo\"></TD></TR>";
echo "<TR><TD ALIGN=\"CENTER\">";

echo "<TABLE CLASS=\"boxtable\" ALIGN=\"CENTER\" BORDER=\"0\">\n";
echo "
<TR BGCOLOR=\"#F7CE4A\">
 <TH>Rule</TH>
 <TH>Description</TH>
 <TH>Total</TH>
 <TH>Ham</TH>
 <TH>%</TH>
 <TH>Spam</TH>
 <TH>%</TH>
</TR>\n";

while ((list($key, $val) = each($sa_array))) {
    echo "
<TR BGCOLOR=\"#EBEBEB\">
 <TD>$key</TD>
 <TD>" . return_sa_rule_desc(strtoupper($key)) . "</TD>
 <TD ALIGN=\"RIGHT\">" . number_format($val['total']) . "</TD>
 <TD ALIGN=\"RIGHT\">" . number_format($val['not-spam']) . "</TD>
 <TD ALIGN=\"RIGHT\">" . round(($val['not-spam'] / $val['total']) * 100, 1) . "</TD>
 <TD ALIGN=\"RIGHT\">" . number_format($val['spam']) . "</TD>
 <TD ALIGN=\"RIGHT\">" . round(($val['spam'] / $val['total']) * 100, 1) .
        "</TD></TR>";
}
echo "</TABLE>\n";

echo "
  </TABLE>
";

// Add footer
html_end();
// Close any open db connections
dbclose();
