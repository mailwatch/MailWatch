<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2016  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
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
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/filter.inc.php');

// Authentication checking
session_start();
require(__DIR__ . '/login.function.php');

// add the header information such as the logo, search, menu, ....
$filter = html_start(__('topvirus48'), 0, false, true);

// File name
$filename = CACHE_DIR . "/top_viruses.png." . time();

// SQL query to find all emails with a virus found
$sql = "
SELECT
 report
FROM
 maillog
WHERE
 virusinfected = 1
AND
 report IS NOT NULL
" . $filter->CreateSQL();

// Check permissions to see if apache can actually create the file
if (is_writable(CACHE_DIR)) {

    // JpGraph functions
    include_once("./lib/jpgraph/jpgraph.php");
    include_once("./lib/jpgraph/jpgraph_pie.php");
    include_once("./lib/jpgraph/jpgraph_pie3d.php");

    // Must be one or more rows
    $result = dbquery($sql);
    if (mysql_num_rows($result) <= 0) {
        die(__('dienorow48') . mysql_num_rows($result) . "\n");
    }

    $virus_array = array();

    while ($row = mysql_fetch_object($result)) {
        if (preg_match(VIRUS_REGEX, $row->report, $virus_report)) {
            $virus = $virus_report[2];
            if (isset($virus_array[$virus])) {
                $virus_array[$virus]++;
            } else {
                $virus_array[$virus] = 1;
            }
        }
    }

    arsort($virus_array);
    reset($virus_array);

    $count = 0;
    $data = array();
    $data_names = array();
    while ((list($key, $val) = each($virus_array)) && $count < 10) {
        $data[] = $val;
        $data_names[] = "$key";
        $count++;
    }

    // Graphing code
    $graph = new PieGraph(850, 385, 0, false);
    $graph->SetShadow();
    $graph->img->SetAntiAliasing();
    $graph->title->Set(__('top10virus48'));

    $p1 = new PiePlot3d($data);
    $p1->SetTheme('sand');
    $p1->SetLegends($data_names);

    $p1->SetCenter(0.75, 0.4);
    $graph->legend->SetLayout(LEGEND_VERT);
    $graph->legend->Pos(0.25, 0.20, 'center');

    $graph->Add($p1);
    try {
        $graph->Stroke($filename);
        $graphok = true;
    } catch (JpGraphException $e) {
        $graphok = false;
    }
}

// HTML to display the graph
echo "<TABLE BORDER=\"0\" CELLPADDING=\"10\" CELLSPACING=\"0\" WIDTH=\"100%\">";
echo "<TR>";
echo " <TD ALIGN=\"CENTER\"><IMG SRC=\"" . IMAGES_DIR . MS_LOGO . "\" ALT=\"" . __('mslogo99') . "\"></TD>";
echo "</TR>";
echo "<TR>";

//  Check Permissions to see if the file has been written and that apache to read it.
echo '<TD ALIGN="CENTER">';
if ($graphok === true) {
    if (is_readable($filename)) {
        echo '<IMG SRC="' . $filename . '" ALT="Graph">';
    } else {
        echo "<TD ALIGN=\"CENTER\"> " . __('message199') . " " . CACHE_DIR . " " . __('message299');
    }
} else {
    echo __('nodata48');
}
echo '</TD>';
echo "</TR>";
echo "<TR>";
echo " <TD ALIGN=\"CENTER\">";
echo "  <TABLE WIDTH=\"500\">";
echo "   <TR style=\"background-color: #f7ce4a\">";
echo "    <TH>" . __('virus48') . "</TH>";
echo "    <TH>" . __('count48') . "</TH>";
echo "   </TR>";

// Write the data out
for ($i = 0; $i < count($data_names); $i++) {
    echo "<TR style=\"background-color: #EBEBEB\">
 <TD>$data_names[$i]</TD>
 <TD ALIGN=\"RIGHT\">" . number_format($data[$i]) . "</TD>
</TR>\n";
}

echo "  </TABLE>";
echo " </TD>";
echo "</TR>";
echo "</TABLE>";

// Add footer
html_end();
// Close any open db connections
dbclose();
