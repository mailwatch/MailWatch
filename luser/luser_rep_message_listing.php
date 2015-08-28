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

require_once('../functions.php');
require_once('./luser_functions.php');
require_once('Pager.php');
require_once('../filter.inc');
session_start();
//authenticate();
$luser = $_SESSION['luser'];
$pass = $_SESSION['pass'];
if (!luser_auth($luser, $pass)) {
    echo "Error: You are not logged in - please \n";
    echo "<a href=\"luser_login.php\">log in</a> first.\n";
    html_end();
    exit;
}
// $myfilter = " and ( to_address=\"".$luser."\" ";
$myfilter = " and (to_address=\"" . $luser . "\" or from_address=\"" . $luser . "\") ";
// I don't know why the following line causes things to puke. :-(
// $myfilter = " and (timestamp>(now() - interval 3 day)) and (to_address=\"".$luser."\" or from_address=\"".$luser."\") ";

$refresh = luser_html_start("Message Listing");

$dbh = DB::connect(DB_DSN, true);
if (DB::isError($dbh)) {
    die($dbh->getMessage());
}

$sth = $dbh->query(
    "
     SELECT
      DATE_FORMAT(timestamp, '%d/%m/%y %H:%i:%s') AS datetime,
      id AS id,
      from_address,
      to_address,
      subject,
      size,
      CASE WHEN isspam=1 THEN 'Y' ELSE 'N' END AS isspam,
      CASE WHEN ishighspam=1 THEN 'Y' ELSE 'N' END AS ishighspam,
      CASE WHEN isrblspam=1 THEN 'Y' ELSE 'N' END AS isrblspam,
      CASE WHEN spamwhitelisted=1 THEN 'Y' ELSE 'N' END AS iswhitelisted,
      sascore,
      CASE WHEN virusinfected=1 THEN 'Y' ELSE 'N' END AS virusinfected,
      CASE WHEN nameinfected=1 THEN 'Y' ELSE 'N' END AS nameinfected,
      CASE WHEN otherinfected=1 THEN 'Y' ELSE 'N' END AS otherinfected,
      report
     FROM
      maillog
     WHERE
      1=1
    " . $myfilter . "
 ORDER BY 
  date DESC, time DESC
"
) or die("Error executing query: " . mysql_error());
//"); // or die("Error executing query: ".mysql_error());
//  and to_address='tgfurnish@herff-jones.com'
//".$_SESSION["filter"]->CreateSQL()."

// print_r($sth);

$offset = intval($_REQUEST['offset']);
$per_page = MAX_RESULTS;

$pager = new DB_Pager($sth, $offset, $per_page);
$data = $pager->build();

// Display table headings
echo "<TABLE CLASS=\"mail\" BORDER=0 WIDTH=100% CELLSPACING=2 CELLPADDING=2>\n";
echo " <THEAD>\n";

// Previous page link
// tgf - Don't show a Prev link on the first page.
if ($data['current'] != '1') {
    printf('<TH ALIGN="CENTER"><A HREF="%s?offset=%d">&lt;&lt;Prev</A></TH>', sanitizeInput($_SERVER['PHP_SELF']), $data['prev']);
} else {
    printf('<TH ALIGN="CENTER">&nbsp;</TH>');
}

echo "  <TH COLSPAN=6>Showing records " . $data['from'] . " to " . $data['to'] . " of " . $data['numrows'] . "\n";

// Next page link
// tgf - Don't show a Next link on the last page.
if ($data['current'] != $data['numpages']) {
    printf('<TH ALIGN="CENTER"><A HREF="%s?offset=%d">Next&gt;&gt;</A></TH>', sanitizeInput($_SERVER['PHP_SELF']), $data['next']);
} else {
    printf('<TH ALIGN="CENTER">&nbsp;</TH>');
}

echo " </THEAD>\n";
echo " <THEAD>\n";
echo "  <TH>Date/Time</TH>\n";
echo "  <TH>ID</TH>\n";
echo "  <TH>From</TH>\n";
echo "  <TH>To</TH>\n";
echo "  <TH class=\"subject\">Subject</TH>\n";
echo "  <TH ALIGN=\"CENTER\">Size</TH>\n";
echo "  <TH ALIGN=\"CENTER\">SA Score</TH>\n";
echo "  <TH>Status</TH>\n";
echo " </THEAD>\n";

// Get rows
while ($row = $pager->fetchRow(DB_FETCHMODE_OBJECT)) {
    $status_array = array();

    if ($row->isspam == 'Y') {
        array_push($status_array, "Spam");
    }

    if ($row->iswhitelisted == 'Y') {
        array_push($status_array, "Whitelisted");
    }

    if ($row->virusinfected == 'Y') {
        // Get virus name
        if (preg_match(VIRUS_REGEX, $row->report, $virus)) {
            array_push($status_array, "Virus ($virus[2])");
        } else {
            array_push($status_array, "Virus");
        }
    }

    if ($row->nameinfected == 'Y') {
        array_push($status_array, "Blocked attachment");
    }

    if ($row->otherinfected == 'Y') {
        array_push($status_array, "Other infection");
    }

    if (count($status_array) == 0) {
        array_push($status_array, "Clean");
    }

    $status = join($status_array, ", ");

    # Row colorings
    if ($row->virusinfected == 'Y' || $row->nameinfected == "Y" || $row->otherinfected == "Y") {
        echo " <TR CLASS=\"infected\">\n";
    } elseif ($row->isspam == 'Y') {
        echo " <TR CLASS=\"spam\">\n";
    } elseif ($row->iswhitelisted == 'Y') {
        echo " <TR CLASS=\"whitelisted\">\n";
    } else {
        echo " <TR>";
    }

    echo "  <TD>" . $row->datetime . "</TD>\n";
    echo "  <TD>" . $row->id . "</TD>\n";
    echo "  <TD>" . htmlentities($row->from_address) . "</TD>\n";
    echo "  <TD>" . str_replace(",", "<BR />", htmlentities($row->to_address)) . "</TD>\n";
    echo "  <TD class=\"subject\" width=\"10\">" . htmlentities($row->subject) . "</TD>\n";
    echo "  <TD ALIGN=\"RIGHT\">" . format_mail_size($row->size) . "</TD>\n";
    echo "  <TD ALIGN=\"RIGHT\">" . $row->sascore . "</TD>\n";
    echo "  <TD>" . $status . "</TD>\n";
    echo " </TR>\n";
}
echo " <TR><TD COLSPAN=8>\n";
echo "  <TABLE WIDTH=100% BORDER=0><TR>\n";

// Previous page link
// tgf - Don't show a Prev link on the first page.
if ($data['current'] != '1') {
    printf(
        '<TD ALIGN="CENTER"><A HREF="%s?offset=%d">&lt;&lt;Prev</A></TD><TD ALIGN="CENTER">',
        sanitizeInput($_SERVER['PHP_SELF']),
        $data['prev']
    );
} else {
    printf('<TD ALIGN="CENTER">&nbsp;</TD><TD ALIGN="CENTER">');
}

// Links to each page
foreach ($data['pages'] as $page => $start) {
    if ($data['current'] != $page) {
        printf('<A HREF="%s?offset=%d">%s</A> ', sanitizeInput($_SERVER['PHP_SELF']), $start, $page);
    } else {
        printf('%s ', $page);
    }
}

// Next page link
// tgf - Don't show a Next link on the last page.
if ($data['current'] != $data['numpages']) {
    printf('</TD><TD ALIGN="CENTER"><A HREF="%s?offset=%d">Next&gt;&gt;</A></TD>', sanitizeInput($_SERVER['PHP_SELF']), $data['next']);
} else {
    printf('</TD><TD ALIGN="CENTER">&nbsp;</TD>');
}

echo " </TR></TABLE>\n";
echo "</TD></TR></TABLE>\n";
html_end();
