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

if ($_SESSION['user_type'] != 'A') {
    header('Location: index.php');
} else {
    html_start("SpamAssassin Rule Description Update", 0, false, false);

    echo "<FORM METHOD=\"POST\" ACTION=\"" . $_SERVER['PHP_SELF'] . "\">";
    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"run\" VALUE=\"true\">";
    echo "<TABLE CLASS=\"boxtable\" WIDTH=\"100%\">";
    echo "<TR>";
    echo "  <TD>";
    echo "   This utility is used to update the SQL database with up-to-date descriptions of the SpamAssassin rules which are displayed on the Message Detail screen.<BR>";
    echo "   <BR>";
    echo "   This utility should generally be run after a SpamAssassin update, however it is safe to run at any time as it only replaces the existing values and inserts only new values in the table (therefore preserving descriptions from potentially deprecated or removed rules).<BR>";
    echo "  </TD>";
    echo "</TR>";
    echo " <TR>";
    echo "  <TD ALIGN=\"CENTER\"><BR><INPUT TYPE=\"SUBMIT\" VALUE=\"Run Now\"><BR><BR></TD>";
    echo "</TR>";

    if ($_POST['run']) {
        echo "<TR><TD ALIGN=\"CENTER\"><TABLE CLASS=\"mail\" BORDER=\"0\" CELLPADDING=\"1\" CELLSPACING=\"1\"><TR><TH>Rule</TH><TH>Description</TH></TR>\n";
        $fh = popen(
            "grep -hr '^describe' " . SA_RULES_DIR . " /usr/share/spamassassin /usr/local/share/spamassassin /etc/MailScanner/spam.assassin.prefs.conf /opt/MailScanner/etc/spam.assassin.prefs.conf /usr/local/etc/mail/spamassassin /etc/mail/spamassassin /var/lib/spamassassin 2>/dev/null | sort | uniq",
            'r'
        );
        audit_log('Ran SpamAssasin Rules Description Update');
        while (!feof($fh)) {
            $line = rtrim(fgets($fh, 4096));
            // debug("line: ".$line."\n");
            preg_match("/^describe\s+(\S+)\s+(.+)$/", $line, $regs);
            if ($regs[1] && $regs[2]) {
                $regs[1] = mysql_real_escape_string(ltrim(rtrim($regs[1])));
                $regs[2] = mysql_real_escape_string(ltrim(rtrim($regs[2])));
                echo "<TR><TD>" . htmlentities($regs[1]) . "</TD><TD>" . htmlentities($regs[2]) . "</TD></TR>\n";
                dbquery("REPLACE INTO sa_rules VALUES ('$regs[1]','$regs[2]')");
                //debug("\t\tinsert: ".$regs[1].", ".$regs[2]);
            } else {
                debug("$line - did not match regexp, not inserting into database");
            }
        }
        pclose($fh);
        echo "</TABLE><BR></TD></TR>\n";

        echo "</TABLE>";

    }
    echo "</TABLE>\n";
    echo "</FORM>\n";
}
// Add footer
html_end();
// Close any open db connections
dbclose();
