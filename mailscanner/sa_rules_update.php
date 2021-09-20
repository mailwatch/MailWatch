<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2018  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once __DIR__ . '/functions.php';

require __DIR__ . '/login.function.php';

if ($_SESSION['user_type'] !== 'A') {
    header('Location: index.php');
} else {
    html_start(__('saruldesupdate13'), 0, false, false);
    echo '<table class="boxtable" style="width: 100%">';
    echo '<tr><th>' . __('updatesadesc13') . '</th></tr>';
    echo '<tr>';
    echo '  <td>';
    echo '   <br>' . __('message113') . '<br>';
    echo '   <br>';
    echo '   ' . __('message213') . '<br><br>';
    echo '  </td>';
    echo '</tr>';
    echo ' <tr>';
    echo '  <td style="text-align: center">
    <form method="post" action="sa_rules_update.php">
    <div>' . "\n";
    echo '<input type="submit" value="' . __('input13') . '"><br><br>';
    echo '<input type="hidden" name="run" value="true">
    </div>
    </form>
    </td>';
    echo '</tr>';
    echo "</table>\n";

    if (isset($_POST['run'])) {
        echo '<table style="width: 100%">';
        echo '<tr><td style="text-align: center"><table class="mail" border="0" cellpadding="1" cellspacing="1"><tr><th>' . __('rule13') . '</th><th>' . __('description13') . "</th></tr>\n";
        $fh = popen(
            "grep -hr '^\s*describe' " . SA_RULES_DIR . ' /usr/share/spamassassin /usr/local/share/spamassassin ' . SA_PREFS . ' /etc/MailScanner/spam.assassin.prefs.conf /opt/MailScanner/etc/spam.assassin.prefs.conf /usr/local/etc/mail/spamassassin /etc/mail/spamassassin /var/lib/spamassassin 2>/dev/null | sed -e \'s/^[ \t]*describe[ \t]*/describe\t/i\' | sort | uniq',
            'r'
        );
        audit_log(__('auditlog13', true));
        while (!feof($fh)) {
            $line = rtrim(fgets($fh, 4096));
            // debug("line: ".$line."\n");
            preg_match("/^(?:\s*)describe\s+(\S+)\s+(.+)$/", $line, $regs);
            if (isset($regs[1], $regs[2])) {
                $rule_name = trim($regs[1]);
                $rule_desc = htmlentities(trim(strip_tags($regs[2])), ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
                echo '<tr><td>' . $rule_name . '</td><td>' . $rule_desc . "</td></tr>\n";
                $rule_name = safe_value($rule_name);
                $rule_desc = safe_value(substr($rule_desc, 0, 512));
                dbquery("REPLACE INTO sa_rules VALUES ('$rule_name','$rule_desc')");
            //debug("\t\tinsert: ".$regs[1].", ".$regs[2]);
            } else {
                debug("$line - did not match regexp, not inserting into database");
            }
        }
        pclose($fh);
        echo "</table><br></td></tr>\n";
        echo '</table>';
    }
}
// Add footer
html_end();
// Close any open db connections
dbclose();
