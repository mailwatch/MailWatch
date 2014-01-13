<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Include of nessecary functions
require_once("./functions.php");
require_once("./filter.inc");

// Authenication checking
session_start();
require('login.function.php');

// add the header information such as the logo, search, menu, ....
$filter = html_start("SpamAssassin Rule Hits",0,false,true);

$sql = "
 SELECT
  spamreport,
  isspam
 FROM
  maillog
 WHERE
  spamreport IS NOT NULL
 AND spamreport != \"\"
".$filter->CreateSQL();

$result = dbquery($sql);
if(!mysql_num_rows($result) > 0) {
 die("Error: no rows retrieved from database\n");
}

// Initialise the array
$sa_array = array();

// Retrieve rows and insert into array
while ($row = mysql_fetch_object($result)) {
##### TODEL/TODO #
##### TODEL/TODO # stdClass Object
##### TODEL/TODO # (
##### TODEL/TODO # [spamreport] => not spam (too large)
##### TODEL/TODO # [isspam] => 0
##### TODEL/TODO #)
##### TODEL/TODO #
##### TODEL/TODO # printf("<pre>\n");print_r($row);printf("</pre>\n");
##### TODEL/TODO #
 preg_match('/SpamAssassin \((.+?)\)/i',$row->spamreport,$sa_rules);
 // Get rid of first match from the array
 $junk = array_shift($sa_rules);
 // Split the array, and get rid of the score and required values
 $sa_rules = explode(", ",$sa_rules[0]);
 $junk = array_shift($sa_rules);  // score=
 $junk = array_shift($sa_rules);  // required
 foreach($sa_rules as $rule) {
  // Check if SA scoring is present
  if(preg_match('/^(.+) (.+)$/',$rule,$regs)) {
   $rule = $regs[1];
  }
  $sa_array[$rule]['total']++;
  if ($row->isspam <> 0) {
   $sa_array[$rule]['spam']++;
  } else {
   $sa_array[$rule]['not-spam']++;
  }
  // Initialise the other dimensions of the array
  if (!$sa_array[$rule]['spam']) { $sa_array[$rule]['spam']=0; }
  if (!$sa_array[$rule]['not-spam']) { $sa_array[$rule]['not-spam']=0; }
 }
}

reset($sa_array);
arsort($sa_array);

echo "<TABLE BORDER=\"0\" CELLPADDING=\"10\" CELLSPACING=\"0\" WIDTH=\"100%\">";
echo "<TR><TD ALIGN=\"CENTER\"><IMG SRC=\"".IMAGES_DIR."mailscannerlogo.gif\" ALT=\"MailScanner Logo\"></TD></TR>";
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
while ((list($key,$val) = each($sa_array)) && $count < 10) {
 echo "
<TR BGCOLOR=\"#EBEBEB\">
 <TD>$key</TD>
 <TD>".return_sa_rule_desc(strtoupper($key))."</TD>
 <TD ALIGN=\"RIGHT\">".number_format($val['total'])."</TD>
 <TD ALIGN=\"RIGHT\">".number_format($val['not-spam'])."</TD>
 <TD ALIGN=\"RIGHT\">".round(($val['not-spam']/$val['total'])*100,1)."</TD>
 <TD ALIGN=\"RIGHT\">".number_format($val['spam'])."</TD>
 <TD ALIGN=\"RIGHT\">".round(($val['spam']/$val['total'])*100,1).
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
