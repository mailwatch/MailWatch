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

require_once("./functions.php");

session_start();
require('login.function.php');

if ($_SESSION['user_type'] != 'A') {
    header('Location: index.php');
} else {
    html_start("SpamAssassin Rule Description Update", 0, false, false);
    echo "<table class=\"boxtable\" width=\"100%\">";
    echo "<tr><th>SpamAssassin Rule Description Update</th></tr>";
    echo "<tr>";
    echo "  <td>";
    echo "   <br>This utility is used to update the SQL database with up-to-date descriptions of the SpamAssassin rules which are displayed on the Message Detail screen.<br>";
    echo "   <br>";
    echo "   This utility should generally be run after a SpamAssassin update, however it is safe to run at any time as it only replaces the existing values and inserts only new values in the table (therefore preserving descriptions from potentially deprecated or removed rules).<br><br>";
    echo "  </td>";
    echo "</tr>";
    echo " <tr>";
    echo '  <td align="center">
    <form method="post" action="sa_rules_update.php">
    <div style="margin: 5px">' . "\n";
    echo "<input type=\"submit\" value=\"" . __('input01') . "\"><br><br>";
    echo "<input type=\"hidden\" name=\"run\" value=\"true\">
    </div>
    </form>
    </td>";
    echo "</tr>";
    echo "</table>\n";

    if (isset($_POST['run'])) {
        echo "<table width=\"100%\">";
        echo "<tr><td align=\"center\"><table class=\"mail\" border=\"0\" cellpadding=\"1\" cellspacing=\"1\"><tr><th>Rule</th><th>Description</th></tr>\n";
        $fh = popen(
            "grep -hr '^describe' " . SA_RULES_DIR . " /usr/share/spamassassin /usr/local/share/spamassassin /etc/MailScanner/spam.assassin.prefs.conf /opt/MailScanner/etc/spam.assassin.prefs.conf /usr/local/etc/mail/spamassassin /etc/mail/spamassassin /var/lib/spamassassin 2>/dev/null | sort | uniq",
            'r'
        );
        audit_log('Ran SpamAssassin Rules Description Update');
        while (!feof($fh)) {
            $line = rtrim(fgets($fh, 4096));
            // debug("line: ".$line."\n");
            preg_match("/^describe\s+(\S+)\s+(.+)$/", $line, $regs);
            if (isset($regs[1]) && isset($regs[2])) {
                $regs[1] = trim($regs[1]);
                $regs[2] = trim($regs[2]);
                echo "<tr><td>" . htmlentities($regs[1]) . "</td><td>" . htmlentities($regs[2]) . "</td></tr>\n";
                $regs[1] = mysql_real_escape_string($regs[1]);
                $regs[2] = mysql_real_escape_string($regs[2]);
                dbquery("REPLACE INTO sa_rules VALUES ('$regs[1]','$regs[2]')");
                //debug("\t\tinsert: ".$regs[1].", ".$regs[2]);
            } else {
                debug("$line - did not match regexp, not inserting into database");
            }
        }
        pclose($fh);
        echo "</table><br></td></tr>\n";
        echo "</table>";
    }
}
// Add footer
html_end();
// Close any open db connections
dbclose();
