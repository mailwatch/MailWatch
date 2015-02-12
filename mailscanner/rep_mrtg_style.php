<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 Copyright (C) 2014-2015  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 In addition, as a special exception, the copyright holder gives permission to link the code of this program 
 with those files in the PEAR library that are licensed under the PHP License (or with modified versions of those 
 files that use the same license as those files), and distribute linked combinations including the two. 
 You must obey the GNU General Public License in all respects for all of the code used other than those files in the 
 PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to 
 your version of the program, but you are not obligated to do so.
 If you do not wish to do so, delete this exception statement from your version.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Include of necessary functions
require_once("./functions.php");
require_once("./filter.inc");

// Authentication checking
session_start();
require('login.function.php');

// add the header information such as the logo, search, menu, ....
$filter = html_start("MRTG Style Mail Report", 0, false, true);

// File name
$filename = "" . CACHE_DIR . "/rep_mrtg_style.png." . time() . "";

list($hour_format, $minute_format, $second_format) = explode(":", TIME_FORMAT);

$date_format = "'" . DATE_FORMAT . " " . $hour_format . ":" . $minute_format . "'";

$sql_last24hrs = "
 SELECT
  DATE_FORMAT(timestamp, $date_format) AS xaxis,
  COUNT(*) AS total_mail,
  SUM(virusinfected) AS total_virii,
  SUM(isspam) AS total_spam,
  SUM(size) AS total_size
 FROM
  maillog
 WHERE
  1=1
 AND
  timestamp BETWEEN (NOW() - INTERVAL 24 HOUR) AND NOW()
" . $filter->CreateSQL() . "
 GROUP BY
  xaxis
 ORDER BY
  timestamp DESC
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
    $result = dbquery($sql_last24hrs);
    if (mysql_num_rows($result) <= 1) {
        die("Error: Needs 2 or more rows of data to be retrieved from database\n");
    }


    $last = "";
    while ($row = mysql_fetch_object($result)) {
        if ($last == substr($row->xaxis, 0, 2)) {
            $data_labels_hour[] = "";
        } else {
            $data_labels_hour[] = substr($row->xaxis, 0, 2);
            $last = substr($row->xaxis, 0, 2);
        }
        //$data_labels[] = $row->xaxis;
        $data_total_mail[] = $row->total_mail;
        $data_total_virii[] = $row->total_virii;
        $data_total_spam[] = $row->total_spam;
        $data_total_size[] = $row->total_size;
    }

    /*
    while($row=mysql_fetch_object($result)) {
     $data_labels[] = $row->xaxis;
     $data_total_mail[] = $row->total_mail;
     $data_total_virii[] = $row->total_virii;
     $data_total_spam[] = $row->total_spam;
     $data_total_mcp[] = $row->total_mcp;
     $data_total_size[] = $row->total_size;
    }
    */

    format_report_volume($data_total_size, $size_info);

    $graph = new Graph(750, 350, 0, false);
    //$graph->SetShadow();
    $graph->SetScale("textlin");
    //$graph->SetY2Scale("lin");
    //$graph->y2axis->title->Set("Volume (".$size_info['longdesc'].")");
    $graph->yaxis->SetTitleMargin(40);
    $graph->img->SetMargin(60, 60, 30, 70);
    $graph->title->Set("Last 24 Hrs");
    $graph->xaxis->title->Set("Date");
    $graph->xaxis->SetTextLabelInterval(60);
    $graph->xaxis->SetTickLabels($data_labels_hour);
    $graph->xaxis->SetLabelAngle(50);
    $graph->yaxis->title->Set("No. of messages");
    //$graph->legend->SetLayout(LEGEND_HOR);
    $graph->legend->Pos(0.52, 0.92, 'center');
    $bar1 = new LinePlot($data_total_mail);
    $bar1->SetColor('blue');
    $bar1->SetFillColor('blue');
    $bar1->SetLegend('Mail');
    $bar2 = new LinePlot($data_total_virii);
    $bar2->SetColor('red');
    $bar2->SetFillColor('red');
    $bar2->SetLegend('Virii');
    $bar3 = new LinePlot($data_total_spam);
    $bar3->SetColor('pink');
    $bar3->SetFillColor('pink');
    $bar3->SetLegend('Spam');

    $line1 = new LinePlot($data_total_size);
    //$line1->SetColor('green');
    $line1->SetFillColor('green');
    $line1->SetLegend('Volume (' . $size_info['shortdesc'] . ')');
    $line1->SetCenter();

    //$abar1 = new AccBarPlot(array($bar2,$bar3));
    //$gbplot = new GroupBarPlot(array($bar1,$abar1));

    $graph->Add($bar1);
    $graph->Add($bar2);
    $graph->Add($bar3);
    //$graph->Add($gbplot);
    $graph->Stroke($filename);
}

echo "<TABLE BORDER=\"0\" CELLPADDING=\"10\" CELLSPACING=\"0\" WIDTH=\"100%\">\n";
echo " <TD ALIGN=\"CENTER\"><IMG SRC=\"" . IMAGES_DIR . "mailscannerlogo.gif\" ALT=\"MailScanner Logo\"></TD>";
echo " <TR>\n";

//  Check Permissions to see if the file has been written and that apache to read it.
if (is_readable($filename)) {
    echo " <TD ALIGN=\"CENTER\"><IMG SRC=\"" . $filename . "\" ALT=\"Graph\"></TD>";
} else {
    echo "<TD ALIGN=\"CENTER\"> File isn't readable. Please make sure that " . CACHE_DIR . " is readable and writable by Mailwatch.";
}

echo " </TR>\n";
echo " <TR>\n";
echo "  <TD ALIGN=\"CENTER\">\n";
echo "<TABLE BORDER=\"0\" WIDTH=\"500\">\n";
echo " <TR BGCOLOR=\"#F7CE4A\">\n";
echo "  <TH>Date</TH>\n";
echo "  <TH>Mail</TH>\n";
echo "  <TH>Spam</TH>\n";
echo "  <TH>Virus</TH>\n";
echo "  <TH>Volume</TH>\n";
echo " </TR>\n";
for ($i = 0; $i < count($data_total_mail); $i++) {
    echo "<TR BGCOLOR=\"#EBEBEB\">\n";
    echo " <TD ALIGN=\"CENTER\">$data_labels[$i]</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . number_format($data_total_mail[$i]) . "</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . number_format($data_total_spam[$i]) . "</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . number_format($data_total_virii[$i]) . "</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . format_mail_size(
            $data_total_size[$i] * $size_info['formula']
        ) . "&nbsp;&nbsp;</TD>\n";
    echo "</TR>\n";
}
echo "</TABLE>\n";
echo "</TR>\n";
echo "</TABLE>";

// Add footer
html_end();
// Close any open db connections
dbclose();
