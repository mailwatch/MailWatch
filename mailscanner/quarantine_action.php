<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 Copyright (C) 2014-2016  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)

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

require_once(__DIR__ . '/functions.php');

session_start();
require(__DIR__ . '/login.function.php');

function simple_html_start()
{
    echo '<html>
<head>
<title>MailWatch for Mailscanner</title>
<link rel="shortcut icon" href="images/favicon.png">
<style type="text/css">

</style>
<body>';
}

function simple_html_end()
{
    echo '
</body>
</html>';
}

function simple_html_result($status)
{
    ?>
    <table class="box" width="100%" height="100%">
        <tr>
            <td valign="middle" align="center">
                <table border=0>
                    <tr>
                        <th>Result</th>
                    </tr>
                    <tr>
                        <td><?php echo $status; ?></td>
                    </tr>
                    <tr>
                        <td align="center"><b><a href="javascript:window.close()">Close Window</a></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
<?php

}

if (!isset($_GET['id'])) {
    die("Error: No Message ID");
}
if (!isset($_GET['action'])) {
    die("Error: No action");
}

$list = quarantine_list_items(sanitizeInput($_GET['id']));
if (count($list) == 0) {
    die("Error: Message not found in quarantine");
}

switch ($_GET['action']) {
    case 'release':
        $result = '';
        if (count($list) == 1) {
            $to = $list[0]['to'];
            $result = quarantine_release($list, array(0), $to);
        } else {
            for ($i = 0; $i < count($list); $i++) {
                if (preg_match('/message\/rfc822/', $list[$i]['type'])) {
                    $result = quarantine_release($list, array($i), $list[$i]['to']);
                }
            }
        }

        if (isset($_GET['html'])) {
            // Display success
            simple_html_start();
            simple_html_result($result);
            simple_html_end();
        }
        break;

    case 'delete':
        $status = array();
        if (isset($_GET['html'])) {
            if (!isset($_GET['confirm'])) {
                // Dislay an 'Are you sure' dialog
                simple_html_start(); ?>
                <table width="100%" height="100%">
                    <tr>
                        <td align="center" valign="middle">
                            <table>
                                <tr>
                                    <th>Delete: Are you sure?</th>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <a href="quarantine_action.php?id=<?php echo sanitizeInput($_GET['id']); ?>&amp;action=delete&amp;html=true&amp;confirm=true">Yes</a>
                                        &nbsp;&nbsp;
                                        <a href="javascript:void(0)" onClick="javascript:window.close()">No</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <?php
                simple_html_end();
            } else {
                simple_html_start();
                for ($i = 0; $i < count($list); $i++) {
                    $status[] = quarantine_delete($list, array($i));
                }
                $status = join('<br/>', $status);
                simple_html_result($status);
                simple_html_end();
            }
        } else {
            // Delete
            for ($i = 0; $i < count($list); $i++) {
                $status[] = quarantine_delete($list, array($i));
            }
        }
        break;

    case 'learn':
        break;

    default:
        die("Unknown action: " . sanitizeInput($_GET['action']));
}

dbclose();
?>
</body>
</html>
