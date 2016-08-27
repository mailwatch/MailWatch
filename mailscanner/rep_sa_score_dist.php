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
$filter = html_start(__('sascoredist38'), 0, false, true);

// File name
$filename = CACHE_DIR . "/sa_score_dist.png." . time();

$sql = "
 SELECT
  ROUND(sascore) AS score,
  COUNT(*) AS count
 FROM
  maillog
 WHERE
  spamwhitelisted=0
" . $filter->CreateSQL() . "
 GROUP BY
  score
 ORDER BY
  score
";

// Check permissions to see if apache can actually create the file
if (is_writable(CACHE_DIR)) {

    // JPGraph
    include_once("./lib/jpgraph/src/jpgraph.php");
    include_once("./lib/jpgraph/src/jpgraph_log.php");
    include_once("./lib/jpgraph/src/jpgraph_bar.php");
    include_once("./lib/jpgraph/src/jpgraph_line.php");

    // ##### AJOS1 NOTE #####
    // ### AjosNote - Must be 2 or more rows...
    // ##### AJOS1 NOTE #####
    $result = dbquery($sql);
    if (mysql_num_rows($result) <= 1) {
        die("Error: Needs 2 or more rows of data to be retrieved from database\n");
    }

    while ($row = mysql_fetch_object($result)) {
        $data_labels[] = $row->score;
        $data_count[] = $row->count;
    }
    // ##### AJOS1 CHANGE #####
    $labelinterval = 5;
    if (count($data_labels) <= 30) {
        $labelinterval = 2;
    }
    if (count($data_labels) <= 5) {
        $labelinterval = 1;
    }
    // ##### AJOS1 CHANGE #####

    $graph = new Graph(850, 350, 0, false);
    $graph->SetShadow();
    $graph->SetScale("textlin");
    $graph->img->SetMargin(60, 60, 30, 70);
    $graph->title->Set("SpamAssassin Score Distribution");
    $graph->xaxis->title->Set("Score (rounded)");
    $graph->xaxis->SetTextLabelInterval($labelinterval);
    $graph->xaxis->SetTickLabels($data_labels);
    $graph->yaxis->title->Set("No. of messages");
    $graph->yaxis->SetTitleMargin(30);
    $graph->yaxis->title->SetMargin(20);
    $graph->legend->SetLayout(LEGEND_HOR);
    $graph->legend->Pos(0.52, 0.87, 'center');
    $bar1 = new LinePlot($data_count);

    $graph->Add($bar1);

    $bar1->SetFillColor('blue');

    $graph->Stroke($filename);
}

echo "<TABLE BORDER=\"0\" CELLPADDING=\"10\" CELLSPACING=\"0\" WIDTH=\"100%\">\n";
echo " <TR><TD ALIGN=\"CENTER\"><IMG SRC=\"" . IMAGES_DIR . MS_LOGO . "\" ALT=\"MailScanner Logo\"></TD></TR>";
echo " <TR>\n";

//  Check Permissions to see if the file has been written and that apache to read it.
if (is_readable($filename)) {
    echo " <TD ALIGN=\"CENTER\"><IMG SRC=\"" . $filename . "\" ALT=\"Graph\"></TD>";
} else {
    echo "<TD ALIGN=\"CENTER\"> File isn't readable. Please make sure that " . CACHE_DIR . " is readable and writable by MailWatch.";
}

echo " </TR>\n";
echo " <TR>\n";
echo "  <TD ALIGN=\"CENTER\">\n";
echo "<TABLE BORDER=\"0\" WIDTH=\"500\">\n";
echo " <TR BGCOLOR=\"#F7CE4A\">\n";
echo "  <TH>Score</TH>\n";
echo "  <TH>Count</TH>\n";
echo " </TR>\n";

for ($i = 0; $i < count($data_count); $i++) {
    echo "<TR BGCOLOR=\"#EBEBEB\">\n";
    echo " <TD ALIGN=\"CENTER\">$data_labels[$i]</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . number_format($data_count[$i]) . "</TD>\n";
    echo "</TR>\n";
}
echo "</TABLE>\n";
echo "</TR>\n";
echo "</TABLE>\n";

// Add footer
html_end();
// Close any open db connections
dbclose();
