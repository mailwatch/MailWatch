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

require_once("./functions.php");

session_start();
require('login.function.php');

if($_SESSION['user_type'] != 'A'){
header( 'Location: index.php');
}
else{
html_start("MCP Rule Description Update",0,false,false);

echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'" >'."\n";
echo '<input type="hidden" name="run" value="true">'."\N";
echo '<table class="boxtable" width="100%">'."\n";
echo ' <tr>'."\n";
echo '  <td>'."\n";
echo '   This utility is used to update the SQL database with up-to-date descriptions of the MCP rules which are displayed on the Message Detail screen.<br>
   <br>
   This utility should generally be run after an update to your MCP rules, however it is safe to run at any time as it only replaces the existing values and inserts only new values in the table (therefore preserving descriptions from potentially deprecated or removed rules).<br>
  </td>
 </tr>
 <tr>'."\n";
echo '  <td align="center"><br><input type="submit" value="Run Now"><br><br></td>'."\n";
echo ' </tr>'."\n";

if($_POST['run']) {
 echo '<tr><td align="CENTER"><table class="mail" border="0" cellpadding="1" cellspacing="1"><tr><th>Rule</th><th>Description</th></tr>'."\n";
 $mcp_prefs_file = get_conf_var('MCPSpamAssassinPrefsFile');
 $mcp_local_rules_dir = get_conf_var('MCPSpamAssassinLocalRulesDir');
 $mcp_default_rules_dir = get_conf_var('MCPSpamAssassinDefaultRulesDir');
 $fh = popen("ls $mcp_prefs_file $mcp_local_rules_dir/*.cf $mcp_default_rules_dir/*.cf | xargs grep -h '^describe'",'r');
 audit_log('Ran MCP Rules Description Update');
 while (!feof($fh)) {
  $line = rtrim(fgets($fh,4096));
  debug("line: ".$line."\n");
  preg_match("/^describe\s+(\S+)\s+(.+)$/",$line,$regs);
  if ($regs[1] && $regs[2]) {
   $regs[1] = mysql_real_escape_string(ltrim(rtrim($regs[1])));
   $regs[2] = mysql_real_escape_string(ltrim(rtrim($regs[2])));
   echo '<tr><td>'.htmlentities($regs[1]).'</td><td>'.htmlentities($regs[2]).'</td></tr>'."\n";
   dbquery("REPLACE INTO mcp_rules VALUES ('$regs[1]','$regs[2]')");
   //debug("\t\tinsert: ".$regs[1].", ".$regs[2]);
  } else {
    debug("$line - did not match regexp, not inserting into database");
  }
 }
 pclose($fh);
 echo '</table><br></td></tr>'."\n";

}
echo '</table>'."\n";
echo '</form>'."\n";
}
// Add footer
html_end();
// Close any open db connections
dbclose();
