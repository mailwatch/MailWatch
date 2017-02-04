<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 Copyright (C) 2014-2017  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)

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

 As a special exception, you have permission to link this program with the JpGraph library and
 distribute executables, as long as you follow the requirements of the GNU GPL in regard to all of the software
 in the executable aside from JpGraph.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/lib/password.php';

session_start();
require __DIR__ . '/login.function.php';

html_start(__('usermgnt12'), 0, false, false);
?>
<script>
   function checkPasswords() {
       var pass0 = document.getElementById("password");
       var pass1 = document.getElementById("retypepassword");
       if(pass0.value != pass1.value) {
           var errorDiv = document.getElementById("formerror");
           errorDiv.innerHTML = "<?php echo __('errorpass12');?><br>";
           errorDiv.classList.remove("hidden");
           return false;
       } else {
           return true;
       }
   }
</script>
<?php
if ($_SESSION['user_type'] === 'A' || $_SESSION['user_type'] === 'D') {
    ?>
    <script type="text/javascript">
        <!--
        function delete_user(id) {
            var yesno = confirm("<?php echo ' ' . __('areusuredel12') . ' '; ?>" + id + "<?php echo __('questionmark12'); ?>");
            if (yesno === true) {
                window.location = "?action=delete&id=" + id;
            } else {
                return false;
            }
        }

        function delete_filter(id, filter) {
            var yesno = confirm("<?php echo __('sure12'); ?>");
            if (yesno === true) {
                window.location = "?action=filters&id=" + id + "&filter=" + filter + "&delete=true";
            } else {
                return false;
            }
        }

        function change_state(id, filter) {
            var yesno = confirm("<?php echo __('sure12'); ?>");
            if (yesno === true) {
                window.location = "?action=filters&id=" + id + "&filter=" + filter + "&change_state=true";
            } else {
                return false;
            }
        }
        -->
    </script>
    <?php
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'new':
                if (!isset($_GET['submit'])) {
                    echo '<div id="formerror" class="hidden"></div>';
                    echo "<FORM METHOD=\"GET\" ACTION=\"user_manager.php\" ONSUBMIT=\"return checkPasswords();\">\n";
                    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"action\" VALUE=\"new\">\n";
                    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"submit\" VALUE=\"true\">\n";
                    echo "<TABLE CLASS=\"mail\" BORDER=\"0\" CELLPADDING=\"1\" CELLSPACING=\"1\">\n";
                    echo " <TR><TD CLASS=\"heading\" COLSPAN=\"2\" ALIGN=\"CENTER\">" . __('newuser12') . '  <br> ' . __('forallusers12') . "</TD></TR>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('username0212') . " <BR></TD><TD><INPUT TYPE=\"TEXT\" NAME=\"username\"></TD></TR>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('name12') . "</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"fullname\"></TD></TR>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('password12') . "</TD><TD><INPUT class=\"password\" TYPE=\"TEXT\" NAME=\"password\"></TD></TR>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('retypepassword12') . "</TD><TD><INPUT class=\"password\" TYPE=\"TEXT\" ID=\"retypepassword\" NAME=\"password1\"></TD></TR>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('usertype12') . "</TD>
    <TD><SELECT NAME=\"type\">
         <OPTION VALUE=\"U\">" . __('user12') . "</OPTION>
         <OPTION VALUE=\"D\">" . __('domainadmin12') . "</OPTION>
         <OPTION VALUE=\"A\">" . __('admin12') . "</OPTION>
        </SELECT></TD></TR>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('quarrep12') . "</TD><TD><INPUT TYPE=\"CHECKBOX\" NAME=\"quarantine_report\"> <font size=-2>" . __('senddaily12') . "</font></TD></TR>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('quarreprec12') . "</td><TD><INPUT TYPE=\"TEXT\" NAME=\"quarantine_rcpt\"><br><font size=\"-2\">" . __('overrec12') . "</font></TD>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('scanforspam12') . "</TD><TD><INPUT TYPE=\"CHECKBOX\" NAME=\"noscan\" CHECKED> <font size=\"-2\">" . __('scanforspam212') . "</font></TD></TR>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('pontspam12') . "</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"spamscore\" VALUE=\"0\" size=\"4\"> <font size=\"-2\">0=" . __('usedefault12') . "</font></TD></TR>\n";
                    echo " <TR><TD CLASS=\"heading\">" . __('hpontspam12') . "</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"highspamscore\" VALUE=\"0\" size=\"4\"> <font size=\"-2\">0=" . __('usedefault12') . "</font></TD></TR>\n";
                    echo "<TR><TD CLASS=\"heading\">" . __('action_0212') . "</TD><TD><INPUT TYPE=\"RESET\" VALUE=\"" . __('reset12') . "\">&nbsp;&nbsp;<INPUT TYPE=\"SUBMIT\" VALUE=\"". __('create12') . "\"></TD></TR>\n";
                    echo "</TABLE></FORM><BR>\n";
                } else {
                    $ar = explode('@', $_GET['username']);
                    if ($_SESSION['user_type'] === 'D' && count($ar) == 1 && $_SESSION['domain'] != "") {
                        echo __('errorcreatenodomainforbidden12') . '<br>';
                    } elseif ($_SESSION['user_type'] === 'D' && count($ar) == 2 && $ar[1] != $_SESSION['domain']) {
                        echo sprintf(__('errorcreatedomainforbidden12'), $ar[1]). '<br>';
                    } elseif ($_GET['password'] !== $_GET['password1']) {
                        echo __('errorpass12') . '<br>';
                    } elseif (checkForExistingUser($_GET['username'])) {
                        echo sprintf(__('userexists12'), $_GET['username']) . '<br>';
                    } else {
                        $n_username = safe_value($_GET['username']);
                        $n_fullname = safe_value($_GET['fullname']);
                        $n_password = safe_value(password_hash($_GET['password'], PASSWORD_DEFAULT));
                        $n_type = safe_value($_GET['type']);
                        $spamscore = safe_value($_GET['spamscore']);
                        $highspamscore = safe_value($_GET['highspamscore']);
                        if (!isset($_GET['quarantine_report'])) {
                            $quarantine_report = '0';
                        } else {
                            $quarantine_report = '1';
                        }
                        $noscan = '0';
                        if (!isset($_GET['noscan'])) {
                            $noscan = '1';
                        }

                        $quarantine_rcpt = safe_value($_GET['quarantine_rcpt']);
                        $sql = 'INSERT INTO users (username, fullname, password, type, quarantine_report, ';
                        if ($spamscore !== '0') {
                            $sql .= 'spamscore, ';
                        }
                        if ($highspamscore !== '0') {
                            $sql .= 'highspamscore, ';
                        }
                        $sql .= "noscan, quarantine_rcpt) VALUES ('$n_username','$n_fullname','$n_password','$n_type','$quarantine_report',";
                        if ($spamscore !== '0') {
                            $sql .= "'$spamscore',";
                        }
                        if ($highspamscore !== '0') {
                            $sql .= "'$highspamscore',";
                        }
                        $sql .= "'$noscan','$quarantine_rcpt')";
                        dbquery($sql);
                        switch ($n_type) {
                            case 'A':
                                $n_typedesc = 'administrator';
                                break;
                            case 'D':
                                $n_typedesc = 'domain administrator';
                                break;
                            default:
                                $n_typedesc = 'user';
                                break;
                        }
                        audit_log(__('auditlog0112') . ' ' . $n_typedesc . " '" . $n_username . "' (" . $n_fullname . ') ' . __('auditlog0212'));
                    }
                }
                break;
            case 'edit':
                // if editing user is domain admin check if he tries to edit a user from the same domain. if we do the update we also have to check the new username
                $ar = explode('@', $_GET['key']);
                if ($_SESSION['user_type'] === 'D' && count($ar) == 1 && $_SESSION['domain'] != "") {
                    echo __('erroreditnodomainforbidden12') . '<br>';
                } elseif ($_SESSION['user_type'] === 'D' && $_SESSION['user_type'] === 'D' && count($ar) == 2 && $ar[1] != $_SESSION['domain']) {
                    echo sprintf(__('erroreditdomainforbidden12'), $ar[1]) . '<br>';
                } else {
                    if (!isset($_GET['submit'])) {
                        $sql = "SELECT username, fullname, type, quarantine_report, quarantine_rcpt, spamscore, highspamscore, noscan FROM users WHERE username='" . safe_value(sanitizeInput($_GET['key'])) . "'";
                        $result = dbquery($sql);
                        $row = $result->fetch_object();
                        $quarantine_report = '';
                        if ((int)$row->quarantine_report === 1) {
                            $quarantine_report = 'CHECKED';
                        }
                        $noscan = '';
                        if ((int)$row->noscan === 0) {
                            $noscan = 'checked="checked"';
                        }

                        $s['A'] = '';
                        $s['D'] = '';
                        $s['U'] = '';
                        $s['R'] = '';

                        $s[$row->type] = 'SELECTED';
                        echo '<div id="formerror" class="hidden"></div>';
                        echo "<FORM METHOD=\"GET\" ACTION=\"user_manager.php\" ONSUBMIT=\"return checkPasswords();\">\n";
                        echo "<INPUT TYPE=\"HIDDEN\" NAME=\"action\" VALUE=\"edit\">\n";
                        echo "<INPUT TYPE=\"HIDDEN\" NAME=\"key\" VALUE=\"" . $row->username . "\">\n";
                        echo "<INPUT TYPE=\"HIDDEN\" NAME=\"submit\" VALUE=\"true\">\n";
                        echo "<TABLE CLASS=\"mail\" BORDER=0 CELLPADDING=1 CELLSPACING=1>\n";
                        echo " <TR><TD CLASS=\"heading\" COLSPAN=2 ALIGN=\"CENTER\">" . __('edituser12') . ' ' . $row->username . "</TD></TR>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('username0212') . "</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"username\" VALUE=\"" . $row->username . "\"></TD></TR>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('name12') . "</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"fullname\" VALUE=\"" . $row->fullname . "\"></TD></TR>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('password12') . "</TD><TD><INPUT TYPE=\"PASSWORD\" ID=\"password\" NAME=\"password\" VALUE=\"XXXXXXXX\"></TD></TR>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('retypepassword12') . "</TD><TD><INPUT TYPE=\"PASSWORD\" ID=\"retypepassword\" NAME=\"password1\" VALUE=\"XXXXXXXX\"></TD></TR>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('usertype12') . "</TD>
		<TD><SELECT NAME=\"type\">
			 <OPTION " . $s['A'] . " VALUE=\"A\">" . __('admin12') . '</OPTION>
			 <OPTION ' . $s['D'] . " VALUE=\"D\">" . __('domainadmin12') . '</OPTION>
			 <OPTION ' . $s['U'] . " VALUE=\"U\">" . __('user12') . '</OPTION>
			 <OPTION ' . $s['R'] . " VALUE=\"R\">" . __('userregex12') . "</OPTION>
			</SELECT></TD></TR>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('quarrep12') . "</TD><TD><INPUT TYPE=\"CHECKBOX\" NAME=\"quarantine_report\" $quarantine_report> <font size=-2>" . __('senddaily12') . "</font></TD></TR>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('quarreprec12') . "</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"quarantine_rcpt\" VALUE=\"" . $row->quarantine_rcpt . "\"><br><font size=\"-2\">" . __('overrec12') . "</font></TD>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('scanforspam12') . "</TD><TD><INPUT TYPE=\"CHECKBOX\" NAME=\"noscan\" $noscan> <font size=\"-2\">" . __('scanforspam212') . "</font></TD></TR>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('pontspam12') . "</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"spamscore\" VALUE=\"" . $row->spamscore . "\" size=\"4\"> <font size=\"-2\">0=" . __('usedefault12') . "</font></TD></TR>\n";
                        echo " <TR><TD CLASS=\"heading\">" . __('hpontspam12') . "</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"highspamscore\" VALUE=\"" . $row->highspamscore . "\" size=\"4\"> <font size=\"-2\">0=" . __('usedefault12') . "</font></TD></TR>\n";
                        echo "<TR><TD CLASS=\"heading\">" . __('action_0212') . "</TD><TD><INPUT TYPE=\"RESET\" VALUE=\"" . __('reset12') . "\">&nbsp;&nbsp;<INPUT TYPE=\"SUBMIT\" VALUE=\"" . __('update12') . "\"></TD></TR>\n";
                        echo "</TABLE></FORM><BR>\n";
                        $sql = "SELECT filter, active FROM user_filters WHERE username='" . $row->username . "'";
                        $result = dbquery($sql);
                    } else {
                        // Do update
                        $ar = explode('@', $_GET['username']);
                        if ($_SESSION['user_type'] === 'D' && count($ar) == 1 && $_SESSION['domain'] != "") {
                            echo __('errortonodomainforbidden12') . '<br>';
                        } elseif ($_SESSION['user_type'] === 'D' && count($ar) == 2 && $ar[1] != $_SESSION['domain']) {
                            echo sprintf(__('errortodomainforbidden12'), $ar[1]) . '<br>';
                        } elseif ($_SESSION['user_type'] === 'D' && $_GET['type'] == 'A') {
                            echo __('errortypesetforbidden12') . '<br>';
                        } elseif ($_GET['password'] !== $_GET['password1']) {
                            echo __('errorpass12') . '<br>';
                        } elseif ($_GET['key'] != $_GET['username'] && checkForExistingUser($_GET['username'])) {
                            echo sprintf(__('userexists12'), $_GET['username']) . '<br>';
                        } else {
                            $do_pwd = false;
                            $key = safe_value($_GET['key']);
                            $n_username = safe_value($_GET['username']);
                            $n_fullname = safe_value($_GET['fullname']);
                            $n_password = safe_value(password_hash($_GET['password'], PASSWORD_DEFAULT));
                            $n_type = safe_value($_GET['type']);
                            $spamscore = safe_value($_GET['spamscore']);
                            $highspamscore = safe_value($_GET['highspamscore']);
                            $n_quarantine_report = '1';
                            if (!isset($_GET['quarantine_report'])) {
                                $n_quarantine_report = '0';
                            }
                            $noscan = '0';
                            if (!isset($_GET['noscan'])) {
                                $noscan = '1';
                            }
                            $quarantine_rcpt = safe_value($_GET['quarantine_rcpt']);

                            // Record old user type to audit user type promotion/demotion
                            $o_type = database::mysqli_result(dbquery("SELECT type FROM users WHERE username='$key'"), 0);
                            if ($_GET['password'] !== 'XXXXXXXX') {
                                // Password reset required
                                $sql = "UPDATE users SET username='$n_username', fullname='$n_fullname', password='$n_password', type='$n_type', quarantine_report='$n_quarantine_report', spamscore='$spamscore', highspamscore='$highspamscore', noscan='$noscan', quarantine_rcpt='$quarantine_rcpt' WHERE username='$key'";
                                dbquery($sql);
                            } else {
                                $sql = "UPDATE users SET username='$n_username', fullname='$n_fullname', type='$n_type', quarantine_report='$n_quarantine_report', spamscore='$spamscore', highspamscore='$highspamscore', noscan='$noscan', quarantine_rcpt='$quarantine_rcpt' WHERE username='$key'";
                                dbquery($sql);
                            }

                            //Audit
                            $type['A'] = 'administrator';
                            $type['D'] = 'domain administrator';
                            $type['U'] = 'user';
                            $type['R'] = 'user';
                            if ($o_type !== $n_type) {
                                audit_log(
                                    __('auditlog0312') . " '" . $n_username . "' (" . $n_fullname . ') ' . __('auditlogfrom12') . ' ' . $type[$o_type] . ' ' . __('auditlogto12') . ' ' . $type[$n_type]
                                );
                            }
                        }
                    }
                }
                break;
            case 'delete':
                $ar = explode('@', $_GET['id']);
                if ($_SESSION['user_type'] === 'D' && count($ar) == 1 && $_SESSION['domain'] != "") {
                    echo __('errordeletenodomainforbidden12') . '<br>';
                } elseif ($_SESSION['user_type'] === 'D' && count($ar) == 2 && $ar[1] != $_SESSION['domain']) {
                    echo sprintf(__('errordeletedomainforbidden12'), $ar[1]) . '<br>';
                } elseif (isset($_GET['id'])) {
                    $id = sanitizeInput($_GET['id']);
                    $sql = "DELETE FROM users WHERE username='" . safe_value($id) . "'";
                    dbquery($sql);
                    audit_log(sprintf(__('auditlog0412'), $_GET['id']));
                }
                break;
            case 'filters':
                $id = sanitizeInput($_GET['id']);
                if (isset($_GET['filter'])) {
                    $getFilter = sanitizeInput($_GET['filter']);
                }

                if (isset($_GET['new'])) {
                    $getActive = sanitizeInput($_GET['active']);
                    $sql = "INSERT INTO user_filters (username, filter, active) VALUES ('" . safe_value($id) . "','" . safe_value($getFilter) . "','" . safe_value($getActive) . "')";
                    dbquery($sql);
                    if (DEBUG === true) {
                        echo $sql;
                    }
                }
                if (isset($_GET['delete'])) {
                    $sql = "DELETE FROM user_filters WHERE username='" . safe_value($id) . "' AND filter='" . safe_value($getFilter) . "'";
                    dbquery($sql);
                    if (DEBUG === true) {
                        echo $sql;
                    }
                }
                if (isset($_GET['change_state'])) {
                    $sql = "SELECT active FROM user_filters WHERE username='" . safe_value($id) . "' AND filter='" . safe_value($getFilter) . "'";
                    $result = dbquery($sql);
                    $active = $result->fetch_row();
                    $active = $active[0];
                    if ($active === 'Y') {
                        $sql = "UPDATE user_filters SET active='N' WHERE username='" . safe_value($id) . "' AND filter='" . safe_value($getFilter) . "'";
                        dbquery($sql);
                    } else {
                        $sql = "UPDATE user_filters SET active='Y' WHERE username='" . safe_value($id) . "' AND filter='" . safe_value($getFilter) . "'";
                        dbquery($sql);
                    }
                }
                $sql = "SELECT filter, CASE WHEN active='Y' THEN '" . __('yes12') . "' ELSE '" . __('no12') . "' END AS active, CONCAT('<a href=\"javascript:delete_filter\(\'" . safe_value($id) . "\',\'',filter,'\'\)\">" . __('delete12') . "</a>&nbsp;&nbsp;<a href=\"javascript:change_state(\'" . safe_value($id) . "\',\'',filter,'\')\">" . __('toggle12') . "</a>') AS actions FROM user_filters WHERE username='" . safe_value($id) . "'";
                $result = dbquery($sql);
                echo "<FORM METHOD=\"GET\" ACTION=\"user_manager.php\">\n";
                echo "<INPUT TYPE=\"HIDDEN\" NAME=\"id\" VALUE=\"" . $id . "\">\n";
                echo "<INPUT TYPE=\"HIDDEN\" NAME=\"action\" VALUE=\"filters\">\n";
                echo "<INPUT TYPE=\"hidden\" NAME=\"new\" VALUE=\"true\">\n";
                echo "<TABLE CLASS=\"mail\" BORDER=\"0\" CELLPADDING=\"1\" CELLSPACING=\"1\">\n";
                echo ' <TR><TH COLSPAN=3>' . __('userfilter12') . ' ' . $id . "</TH></TR>\n";
                echo ' <TR><TH>' . __('filter12') . '</TH><TH>' . __('active12') . '</TH><TH>' . __('action12') . "</TH></TR>\n";
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_object()) {
                        echo ' <TR><TD>' . $row->filter . '</TD><TD>' . $row->active . '</TD><TD>' . $row->actions . "</TD></TR>\n";
                    }
                }
                echo " <TR><TD><INPUT TYPE=\"text\" NAME=\"filter\"></TD><TD><SELECT NAME=\"active\"><OPTION VALUE=\"Y\">" . __('yes12') . "<OPTION VALUE=\"N\">" . __('no12') . "</SELECT></TD><TD><INPUT TYPE=\"submit\" VALUE=\"" . __('add12') . "\"></TD></TR>\n";
                echo "</TABLE><BR>\n";
                echo "</FORM>\n";
                break;
        }
    }

    echo '<a href="?action=new">' . __('newuser12') . '</a>'."\n";
    echo '<br><br>'."\n";

    $domainAdminUserDomainFilter = "";
    if ($_SESSION['user_type'] === 'D') {
        if ($_SESSION['domain'] == '') { //if the domain admin has no domain set we assume he should see only users that has no domain set (no mail as username)
            $domainAdminUserDomainFilter = 'WHERE username NOT LIKE "%@%"';
        } else {
            $domainAdminUserDomainFilter = 'WHERE username LIKE "%@' . $_SESSION['domain'] . '"';
        }
    }

    $sql = "
        SELECT
          username AS '" . safe_value(__('username12')) . "',
          fullname AS '" . safe_value(__('fullname12')) . "',
        CASE
          WHEN type = 'A' THEN '" . __('admin12') . "'
          WHEN type = 'D' THEN '" . __('domainadmin12') . "'
          WHEN type = 'U' THEN '" . __('user12') . "'
          WHEN type = 'R' THEN '" . __('userregex12') . "'
        ELSE
          '" . __('unknowtype12') . "'
        END AS '" . safe_value(__('type12')) . "',
        CASE
          WHEN noscan = 1 THEN '" . __('noshort12') . "'
          WHEN noscan = 0 THEN '" . __('yesshort12') . "'
        ELSE
          '" . __('yesshort12') . "'
        END AS '" . safe_value(__('spamcheck12')) . "',
          spamscore AS '" . safe_value(__('spamscore12')) . "',
          highspamscore AS '" . safe_value(__('spamhscore12')) . "',
        CONCAT('<a href=\"?action=edit&amp;key=',username,'\">" . safe_value(__('edit12')) . "</a>&nbsp;&nbsp;<a href=\"javascript:delete_user(\'',username,'\')\">" . safe_value(__('delete12')) . "</a>&nbsp;&nbsp;<a href=\"?action=filters&amp;id=',username,'\">" . safe_value(__('filters12')) . "</a>') AS '" . safe_value(__('action12')) . "'
        FROM
          users " . $domainAdminUserDomainFilter . " 
        ORDER BY
          username";
    dbtable($sql, __('usermgnt12'));
} else {
    if (!isset($_POST['submit'])) {
        $sql = "SELECT username, fullname, type, quarantine_report, spamscore, highspamscore, noscan, quarantine_rcpt FROM users WHERE username='" . safe_value($_SESSION['myusername']) . "'";
        $result = dbquery($sql);
        $row = $result->fetch_object();
        $quarantine_report = '';
        if ((int)$row->quarantine_report === 1) {
            $quarantine_report = 'checked="checked"';
        }

        $noscan='';
        if ((int)$row->noscan === 0) {
            $noscan = 'checked="checked"';
        }
        $s[$row->type] = 'selected';
        echo '<div id="formerror" class="hidden"></div>';
        echo '<form method="post" action="user_manager.php" onsubmit="return checkPasswords();">'."\n";
        echo '<input type="hidden" name="action" value="edit">'."\n";
        echo '<input type="hidden" name="key" value="' . $row->username . '">'."\n";
        echo '<input type="hidden" name="submit" value="true">'."\n";
        echo '<table class="mail" border="0" cellpadding="1" cellspacing="1">'."\n";
        echo ' <tr><td class="heading" colspan=2 align="center">' . __('edituser12') . ' ' . $row->username . '</td></tr>'."\n";
        echo ' <tr><td class="heading">' . __('username0212') . '</td><td>' . $_SESSION['myusername'] . '</td></tr>'."\n";
        echo ' <tr><td class="heading">' . __('name12') . '</td><td>' . $_SESSION['fullname'] . '</td></tr>'."\n";
        if ($_SESSION['user_ldap'] !== true) {
            echo ' <tr><td class="heading">' . __('password12') . '</td><td><input class=\"password\" TYPE=\"TEXT\" id="password" name="password" value="xxxxxxxx"></td></tr>'."\n";
            echo ' <tr><td class="heading">' . __('retypepassword12') . '</td><td><input class=\"password\" TYPE=\"TEXT\" id="retypepassword" name="password1" value="xxxxxxxx"></td></tr>'."\n";
        }
        echo ' <tr><td class="heading">' . __('quarrep12') . '</td><td><input type="checkbox" name="quarantine_report" value="on" '.$quarantine_report.'> <span style="font-size:90%">' . __('senddaily12') . '</span></td></tr>'."\n";
        echo ' <tr><td class="heading">' . __('quarreprec12') . '</td><td><input type="text" name="quarantine_rcpt" value="' . $row->quarantine_rcpt . '"><br><span style="font-size:90%">' . __('overrec12') . '</span></td>'."\n";
        echo ' <tr><td class="heading">' . __('scanforspam12') . '</td><td><input type="checkbox" name="noscan" value="on" '.$noscan.'> <span style="font-size:90%">' . __('scanforspam212') . '</span></td></tr>'."\n";
        echo ' <tr><td class="heading">' . __('pontspam12') . '</td><td><input type="text" name="spamscore" value="' . $row->spamscore . '" size="4"> <span style="font-size:90%">0=' . __('usedefault12') . '</span></td></tr>'."\n";
        echo ' <tr><td class="heading">' . __('hpontspam12') . '</td><td><input type="text" name="highspamscore" value="' . $row->highspamscore . '" size="4"> <span style="font-size:90%">0=' . __('usedefault12') . '</span></td></tr>'."\n";
        echo '<tr><td class="heading">' . __('action_0212') . '</td><td><input type="reset" value="' . __('reset12') . '">&nbsp;&nbsp;<input type="submit" value="' . __('update12') . '"></td></tr>'."\n";
        echo '</table></form><br>'."\n";
        $sql = "SELECT filter, active FROM user_filters WHERE username='" . $row->username . "'";
        $result = dbquery($sql);
    } else {
        // Do update
        if (isset($_POST['password'], $_POST['password1']) && ($_POST['password'] !== $_POST['password1'])) {
            echo __('errorpass12')  . '<br>';
        } else {
            $do_pwd = false;
            $username = safe_value($_SESSION['myusername']);
            if (isset($_POST['password'])) {
                $n_password = safe_value($_POST['password']);
            }
            $spamscore = safe_value($_POST['spamscore']);
            $highspamscore = safe_value($_POST['highspamscore']);
            $n_quarantine_report = '1';
            if (!isset($_POST['quarantine_report'])) {
                $n_quarantine_report = '0';
            }
            $noscan = '0';
            if (!isset($_POST['noscan'])) {
                $noscan = '1';
            }
            $quarantine_rcpt = safe_value($_POST['quarantine_rcpt']);

            if (isset($_POST['password']) && $_POST['password'] !== 'XXXXXXXX') {
                // Password reset required
                $password = password_hash($n_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password='" . $password . "', quarantine_report='$n_quarantine_report', spamscore='$spamscore', highspamscore='$highspamscore', noscan='$noscan', quarantine_rcpt='$quarantine_rcpt' WHERE username='$username'";
                dbquery($sql);
            } else {
                $sql = "UPDATE users SET quarantine_report='$n_quarantine_report', spamscore='$spamscore', highspamscore='$highspamscore', noscan='$noscan', quarantine_rcpt='$quarantine_rcpt' WHERE username='$username'";
                dbquery($sql);
            }

            // Audit
            audit_log(sprintf(__('auditlog0512'), $username));
            echo '<h1 style="text-align: center; color: green;">Update Completed</h1>';
            echo '<META HTTP-EQUIV="refresh" CONTENT="3;user_manager.php">';
        }
    }
}
// Add footer
html_end();
// Close any open db connections
dbclose();
