<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2017  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
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
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filter.inc.php';

// Authentication checking
session_start();
require __DIR__ . '/login.function.php';

// add the header information such as the logo, search, menu, ....
$filter = html_start(__('totalmaildate49'), 0, false, true);

// Set Date format
$date_format = "'" . DATE_FORMAT . "'";

// File name
$filename = CACHE_DIR . '/total_mail_by_date.png.' . time();

// Check if MCP is enabled
$is_MCP_enabled = get_conf_truefalse('mcpchecks');

// SQL query to pull the data from maillog
$sql = "
 SELECT
  DATE_FORMAT(date, $date_format) AS xaxis,
  COUNT(*) AS total_mail,
  SUM(CASE WHEN virusinfected>0 THEN 1 ELSE 0 END) AS total_virus,

  SUM(CASE WHEN (
    isspam>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    ) THEN 1 ELSE 0 END
  ) AS total_spam,

  SUM(CASE WHEN (
    isspam>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    AND (ishighspam=0 OR ishighspam IS NULL)
    ) THEN 1 ELSE 0 END
  ) AS total_lowspam,

  SUM(CASE WHEN (
    ishighspam>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    ) THEN 1 ELSE 0 END
  ) AS total_highspam,

  SUM(CASE WHEN (
    ismcp>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    AND (isspam=0 OR isspam IS NULL)
    AND (ishighspam=0 OR ishighspam IS NULL)
    ) THEN 1 ELSE 0 END
  ) AS total_mcp,

  SUM(CASE WHEN (
    nameinfected>0
    AND (virusinfected=0 OR virusinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    ) THEN 1 ELSE 0 END
  ) AS total_blocked,

  SUM(CASE WHEN (
    (virusinfected=0 OR virusinfected IS NULL)
    AND (nameinfected=0 OR nameinfected IS NULL)
    AND (otherinfected=0 OR otherinfected IS NULL)
    AND (isspam=0 OR isspam IS NULL)
    AND (ishighspam=0 OR ishighspam IS NULL)
    AND (ismcp=0 OR ismcp IS NULL)
    AND (ishighmcp=0 OR ishighmcp IS NULL)
    ) THEN 1 ELSE 0 END
  ) as total_clean,

  SUM(size) AS total_size
 FROM
  maillog
 WHERE
  1=1
" . $filter->CreateSQL() . '
 GROUP BY
  xaxis
 ORDER BY
  date
';

// Fetch MTA stats
$sql1 = "
SELECT
 DATE_FORMAT(timestamp, $date_format) AS xaxis,
 type,
 count(*) as count
FROM
 mtalog
WHERE
 1=1
" . $filter->CreateMtalogSQL() . "
AND
 type<>'relay'
GROUP BY
 xaxis, type
ORDER BY
 timestamp
";

// Check permissions to see if apache can actually create the file
if (is_writable(CACHE_DIR)) {

    // Includes for JPgraph
    include_once './lib/jpgraph/src/jpgraph.php';
    include_once './lib/jpgraph/src/jpgraph_log.php';
    include_once './lib/jpgraph/src/jpgraph_bar.php';
    include_once './lib/jpgraph/src/jpgraph_line.php';

    // Must be one or more row
    $result = dbquery($sql);
    if (!$result->num_rows > 0) {
        die(__('diemysql99') . "\n");
    }

    // Connecting to the DB and running the query
    $result1 = dbquery($sql1);

    // pulling the data in variables
    while ($row = $result->fetch_object()) {
        $data_labels[] = $row->xaxis;
        $data_total_mail[] = $row->total_mail;
        $data_total_virii[] = $row->total_virus;
        $data_total_blocked[] = $row->total_blocked;
        $data_total_spam[] = $row->total_spam;
        $data_total_lowspam[] = $row->total_lowspam;
        $data_total_highspam[] = $row->total_highspam;
        $data_total_mcp[] = $row->total_mcp;
        $data_total_clean[] = $row->total_clean;
        $data_total_size[] = $row->total_size;
    }

    // Merge in MTA data
    $data_total_unknown_users = array();
    $data_total_rbl = array();
    $data_total_unresolveable = array();
    while ($row1 = $result1->fetch_object()) {
        if (is_numeric($key = array_search($row1->xaxis, $data_labels, true))) {
            switch (true) {
                case($row1->type === 'unknown_user'):
                    $data_total_unknown_users[$key] = $row1->count;
                    break;
                case($row1->type === 'rbl'):
                    $data_total_rbl[$key] = $row1->count;
                    break;
                case($row1->type === 'unresolveable'):
                    $data_total_unresolveable[$key] = $row1->count;
                    break;
            }
        }
    }

    // Setting the graph labels
    $graph_labels = $data_labels;

    // Reduce the number of labels on the graph to prevent them being sqashed.
    if (count($graph_labels) > 20) {
        $b = substr(count($graph_labels), 0, 1);
        for ($a = 0, $graphLabelsCount = count($graph_labels); $a < $graphLabelsCount; $a++) {
            if ($a % $b) {
                $graph_labels[$a] = '';
            }
        }
    }

    format_report_volume($data_total_size, $size_info);

    $graph = new Graph(850, 350, 0, false);
    $graph->SetShadow();
    $graph->SetScale('textlin');
    $graph->SetY2Scale('lin');
    $graph->img->SetMargin(60, 60, 30, 70);
    $graph->title->Set(__('totalmailprocdate49'));
    $graph->y2axis->title->Set(__('volume49') . ' (' . $size_info['longdesc'] . ')');
    $graph->y2axis->title->SetMargin(10);
    $graph->y2axis->SetTitleMargin(40);
    $graph->yaxis->title->Set(__('nomessages49'));
    $graph->yaxis->title->SetMargin(20);
    $graph->yaxis->SetTitleMargin(30);
    $graph->xaxis->title->Set(__('date49'));
    $graph->xaxis->SetTitleMargin(30);
    $graph->xaxis->SetTickLabels($graph_labels);
    $graph->xaxis->SetLabelAngle(45);
    $graph->legend->SetLayout(LEGEND_HOR);
    $graph->legend->Pos(0.52, 0.92, 'center');

    $bar1 = new BarPlot($data_total_mail);
    $bar2 = new BarPlot($data_total_virii);
    $bar3 = new BarPlot($data_total_spam);
    if ($is_MCP_enabled === true) {
        $bar4 = new BarPlot($data_total_mcp);
    }
    $line1 = new LinePlot($data_total_size);
    if ($is_MCP_enabled === true) {
        $abar1 = new AccBarPlot(array($bar2, $bar3, $bar4));
    } else {
        $abar1 = new AccBarPlot(array($bar2, $bar3));
    }
    $gbplot = new GroupBarPlot(array($bar1, $abar1));

    $graph->Add($gbplot);
    $graph->AddY2($line1);

    $bar1->SetColor('blue');
    $bar1->SetFillColor('blue');
    $bar1->SetLegend(__('barmail49'));
    $bar2->SetColor('orange');
    $bar2->SetFillColor('orange');
    $bar2->SetLegend(__('barvirus49'));
    $bar3->SetColor('red');
    $bar3->SetFillColor('red');
    $bar3->SetLegend(__('barspam49'));
    if ($is_MCP_enabled === true) {
        $bar4->SetFillColor('lightblue');
        $bar4->SetLegend(__('barmcp49'));
    }
    $line1->SetColor('lightgreen');
    $line1->SetFillColor('lightgreen');
    $line1->SetLegend(__('barvolume49') . ' (' . $size_info['shortdesc'] . ')');
    $line1->SetCenter();

    $graph->Stroke($filename);
}

// HTML Code to display the graph
echo "<TABLE BORDER=\"0\" CELLPADDING=\"10\" CELLSPACING=\"0\" WIDTH=\"100%\">\n";
echo " <TR><TD ALIGN=\"CENTER\"><IMG SRC=\"" . IMAGES_DIR . MS_LOGO . "\" ALT=\"" . __('mslogo99') . "\"></TD></TR>";
echo " <TR>\n";

//  Check Permissions to see if the file has been written and that apache to read it.
if (is_readable($filename)) {
    echo " <TD ALIGN=\"CENTER\"><IMG SRC=\"" . $filename . "\" ALT=\"Graph\"></TD>";
} else {
    echo "<TD ALIGN=\"CENTER\"> " . __('message199') . ' ' . CACHE_DIR . ' ' . __('message299');
}

echo " </TR>\n";
echo " <TR>\n";
echo "  <TD ALIGN=\"CENTER\">\n";
echo "<TABLE BORDER=0 cellspacing=1 cellpadding=2>\n";
echo " <TR BGCOLOR=\"#F7CE4A\">\n";
echo "  <TH rowspan='2'>" . __('date49') . "</TH>\n";
echo "  <TH rowspan='2' align='right'>" . __('total49') . "</TH>\n";
echo "  <TH colspan='2'>" . __('clean49') . "</TH>\n";
echo "  <TH nowrap colspan='2'>" . __('lowespam49') . "</TH>\n";
echo "  <TH nowrap colspan='2'>" . __('highspam49') . "</TH>\n";
echo "  <TH nowrap colspan='2'>" . __('blocked49') . "</TH>\n";
echo "  <TH colspan='2'>" . __('virus49') . "</TH>\n";
if ($is_MCP_enabled === true) {
    echo "  <TH colspan='2'>" . __('mcp49') . "</TH>\n";
}
echo "  <TH rowspan='2'>" . __('volume49') . "</TH>\n";
echo "  <TH bgcolor='#ffffff' rowspan='2'>&nbsp;</TH>\n";
if (SHOW_MORE_INFO_ON_REPORT_GRAPH === true) {
    echo "  <TH rowspan='2'>" . __('unknoweusers49') . "</TH>\n";
    echo "  <TH rowspan='2'>" . __('resolve49') . "</TH>\n";
    echo "  <TH rowspan='2'>" . __('rbl49') . "</TH>\n";
}
echo " </TR>\n";

echo "<tr BGCOLOR='#F7CE4A'>\n";
echo "<th width='50' align='right'>#</th><th width='40' align='right'>%</th>\n";
echo "<th width='50' align='right'>#</th><th width='40' align='right'>%</th>\n";
echo "<th width='50' align='right'>#</th><th width='40' align='right'>%</th>\n";
echo "<th width='50' align='right'>#</th><th width='40' align='right'>%</th>\n";
echo "<th width='50' align='right'>#</th><th width='40' align='right'>%</th>\n";
if ($is_MCP_enabled === true) {
    echo "<th width='50' align='right'>#</th><th width='40' align='right'>%</th>\n";
}
echo "</tr>\n";
for ($i = 0, $count_data_total_mail = count($data_total_mail); $i < $count_data_total_mail; $i++) {
    echo "<TR BGCOLOR=\"#EBEBEB\">\n";
    echo " <TD ALIGN=\"CENTER\">$data_labels[$i]</TD>\n";
    echo " <TD bgcolor='#ffffff' ALIGN=\"RIGHT\">" . number_format($data_total_mail[$i]) . "</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . number_format($data_total_clean[$i]) . "</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . number_format($data_total_clean[$i] / $data_total_mail[$i] * 100, 1) . "</TD>\n";
    echo " <TD bgcolor='#ffffff' ALIGN=\"RIGHT\">" . number_format($data_total_lowspam[$i]) . "</TD>\n";
    echo " <TD bgcolor='#ffffff' ALIGN=\"RIGHT\">" . number_format($data_total_lowspam[$i] / $data_total_mail[$i] * 100, 1) . "</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . number_format($data_total_highspam[$i]) . "</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . number_format($data_total_highspam[$i] / $data_total_mail[$i] * 100, 1) . "</TD>\n";
    echo " <TD bgcolor='#ffffff' ALIGN=\"RIGHT\">" . suppress_zeros(number_format($data_total_blocked[$i])) . "</TD>\n";
    echo " <TD bgcolor='#ffffff' ALIGN=\"RIGHT\">" . suppress_zeros(number_format($data_total_blocked[$i] / $data_total_mail[$i] * 100, 1)) . "</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . suppress_zeros(number_format($data_total_virii[$i])) . "</TD>\n";
    echo " <TD ALIGN=\"RIGHT\">" . suppress_zeros(number_format($data_total_virii[$i] / $data_total_mail[$i] * 100, 1)) . "</TD>\n";
    if ($is_MCP_enabled === true) {
        echo " <TD bgcolor='#ffffff' ALIGN=\"RIGHT\">" . suppress_zeros(number_format($data_total_mcp[$i])) . "</TD>\n";
        echo " <TD bgcolor='#ffffff' ALIGN=\"RIGHT\">" . suppress_zeros(number_format($data_total_mcp[$i] / $data_total_mail[$i] * 100, 1)) . "</TD>\n";
    }
    echo " <TD ALIGN=\"RIGHT\">" . formatSize($data_total_size[$i] * $size_info['formula']) . "</TD>\n";
    echo " <TD bgcolor='#ffffff'><BR></TD>\n";
    if (SHOW_MORE_INFO_ON_REPORT_GRAPH === true) {
        echo " <TD ALIGN=\"CENTER\">" . suppress_zeros(number_format(isset($data_total_unknown_users[$i]) ? $data_total_unknown_users[$i] : 0)) . "</TD>\n";
        echo " <TD ALIGN=\"CENTER\">" . suppress_zeros(number_format(isset($data_total_unresolveable[$i]) ? $data_total_unresolveable[$i] : 0)) . "</TD>\n";
        echo " <TD ALIGN=\"CENTER\">" . suppress_zeros(number_format(isset($data_total_rbl[$i]) ? $data_total_rbl[$i] : 0)) . "</TD>\n";
    }
    echo "</TR>\n";
}

echo " <TR BGCOLOR=\"#F7CE4A\">\n";
echo " <TH ALIGN=\"RIGHT\">" . __('totals49') . "</TH>\n";
echo " <TH ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_mail)) . "</TH>\n";

echo " <TH ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_clean)) . "</TH>\n";
echo " <TH nowrap ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_clean) / mailwatch_array_sum($data_total_mail) * 100, 0) . "%</TH>\n";

echo " <TH ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_lowspam)) . "</TH>\n";
echo " <TH nowrap ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_lowspam) / mailwatch_array_sum($data_total_mail) * 100, 0) . "%</TH>\n";

echo " <TH ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_highspam)) . "</TH>\n";
echo " <TH nowrap ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_highspam) / mailwatch_array_sum($data_total_mail) * 100, 0) . "%</TH>\n";

echo " <TH ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_blocked)) . "</TH>\n";
echo " <TH nowrap ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_blocked) / mailwatch_array_sum($data_total_mail) * 100, 0) . "%</TH>\n";

echo " <TH ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_virii)) . "</TH>\n";
echo " <TH nowrap ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_virii) / mailwatch_array_sum($data_total_mail) * 100, 0) . "%</TH>\n";
if ($is_MCP_enabled === true) {
    echo " <TH ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_mcp)) . "</TH>\n";
    echo " <TH nowrap ALIGN=\"RIGHT\">" . number_format(mailwatch_array_sum($data_total_mcp) / mailwatch_array_sum($data_total_mail) * 100, 0) . "%</TH>\n";
}
echo " <TH ALIGN=\"RIGHT\">" . formatSize(mailwatch_array_sum($data_total_size) * $size_info['formula']) . "</TH>\n";
echo " <TD bgcolor='#ffffff'><BR></TD>\n";
if (SHOW_MORE_INFO_ON_REPORT_GRAPH === true) {
    echo " <TH ALIGN=\"CENTER\">" . number_format(mailwatch_array_sum($data_total_unknown_users)) . "</TH>\n";
    echo " <TH ALIGN=\"CENTER\">" . number_format(mailwatch_array_sum($data_total_unresolveable)) . "</TH>\n";
    echo " <TH ALIGN=\"CENTER\">" . number_format(mailwatch_array_sum($data_total_rbl)) . "</TH>\n";
}
echo "</TR>\n";
echo "</TABLE>\n";
echo "</TABLE>\n";

// Add footer
html_end();
// Close any open db connections
dbclose();
