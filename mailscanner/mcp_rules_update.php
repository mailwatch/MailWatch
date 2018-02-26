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
    html_start(__('mcpruledesc26'), 0, false, false);

    echo '<form method="post" action="mcp_rules_update.php" >' . "\n";
    echo '<input type="hidden" name="run" value="true">' . "\n";
    echo '<table class="boxtable" width="100%">' . "\n";
    echo ' <tr>' . "\n";
    echo '  <td>' . "\n";
    echo '   ' . __('message0126') . '<br>
   <br>' . __('message0226') . '<br>
  </td>
 </tr>
 <tr>' . "\n";
    echo '  <td align="center"><br><input type="submit" value="' . __('input26') . '"><br><br></td>' . "\n";
    echo ' </tr>' . "\n";

    if (isset($_POST['run'])) {
        echo '<tr><td align="CENTER"><table class="mail" border="0" cellpadding="1" cellspacing="1"><tr><th>' . __('rule26') . '</th><th>' . __('description26') . '</th></tr>' . "\n";
        $mcp_prefs_file = get_conf_var('MCPSpamAssassinPrefsFile');
        $mcp_local_rules_dir = get_conf_var('MCPSpamAssassinLocalRulesDir');
        $mcp_default_rules_dir = get_conf_var('MCPSpamAssassinDefaultRulesDir');
        if ($mcp_local_rules_dir !== $mcp_default_rules_dir) {
            $fh = popen(
                "ls $mcp_prefs_file $mcp_local_rules_dir/*.cf $mcp_default_rules_dir/*.cf | xargs grep -h '^describe'",
                'r'
            );
        } else {
            $fh = popen("ls $mcp_prefs_file $mcp_default_rules_dir/*.cf | xargs grep -h '^describe'", 'r');
        }

        audit_log(__('auditlog26', true));
        while (!feof($fh)) {
            $line = rtrim(fgets($fh, 4096));
            debug('line: ' . $line . "\n");
            preg_match("/^describe\s+(\S+)\s+(.+)$/", $line, $regs);
            if (isset($regs[1], $regs[2])) {
                $regs[1] = safe_value(ltrim(rtrim($regs[1])));
                $regs[2] = safe_value(ltrim(rtrim($regs[2])));
                echo '<tr><td>' . htmlentities($regs[1]) . '</td><td>' . htmlentities($regs[2]) . '</td></tr>' . "\n";
                dbquery("REPLACE INTO mcp_rules VALUES ('$regs[1]','$regs[2]')");
            //debug("\t\tinsert: ".$regs[1].", ".$regs[2]);
            } else {
                debug("$line - did not match regexp, not inserting into database");
            }
        }
        pclose($fh);
        echo '</table><br></td></tr>' . "\n";
    }
    echo '</table>' . "\n";
    echo '</form>' . "\n";
}
// Add footer
html_end();
// Close any open db connections
dbclose();
