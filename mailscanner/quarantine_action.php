<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 Copyright (C) 2014-2018  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)

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

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

require_once __DIR__ . '/functions.php';

require __DIR__ . '/login.function.php';

function simple_html_start()
{
    echo '<html>
<head>
<title>' . __('mailwatchtitle57') . '</title>
<link rel="shortcut icon" href="images/favicon.png">
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
                        <th><?php echo __('result57') ?></th>
                    </tr>
                    <tr>
                        <td><?php echo $status; ?></td>
                    </tr>
                    <tr>
                        <td align="center"><b><a href="javascript:window.close()"><?php echo __('closewindow57') ?></a></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
<?php
}

if (!isset($_GET['id'])) {
    die(__('dienoid57'));
}
if (!isset($_GET['action'])) {
    die(__('dienoaction57'));
}

$id = deepSanitizeInput($_GET['id'], 'url');
if ($id === false || !validateInput($id, 'msgid')) {
    die();
}

$list = quarantine_list_items($id);
if (count($list) === 0) {
    die(__('diemnf57'));
}

switch ($_GET['action']) {
    case 'release':
        if (false === checkToken($_GET['token'])) {
            header('Location: login.php?error=pagetimeout');
            die();
        }
        $result = '';
        if (count($list) === 1) {
            $to = $list[0]['to'];
            $result = quarantine_release($list, [0], $to);
        } else {
            for ($i = 0, $countList = count($list); $i < $countList; $i++) {
                if (preg_match('/message\/rfc822/', $list[$i]['type'])) {
                    $result = quarantine_release($list, [$i], $list[$i]['to']);
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
        if (false === checkToken($_GET['token'])) {
            header('Location: login.php?error=pagetimeout');
            die();
        }
        $status = [];
        if (isset($_GET['html'])) {
            if (!isset($_GET['confirm'])) {
                // Dislay an 'Are you sure' dialog
                simple_html_start(); ?>
                <table width="100%" height="100%">
                    <tr>
                        <td align="center" valign="middle">
                            <table>
                                <tr>
                                    <th><?php echo __('delete57') ?></th>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <a href="quarantine_action.php?token=<?php echo $_SESSION['token']; ?>&amp;id=<?php echo $id; ?>&amp;action=delete&amp;html=true&amp;confirm=true"><?php echo __('yes57') ?></a>
                                        &nbsp;&nbsp;
                                        <a href="javascript:void(0)" onClick="javascript:window.close()"><?php echo __('no57') ?></a>
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
                for ($i = 0, $countList = count($list); $i < $countList; $i++) {
                    $status[] = quarantine_delete($list, [$i]);
                }
                $status = implode('<br/>', $status);
                simple_html_result($status);
                simple_html_end();
            }
        } else {
            if (false === checkToken($_GET['token'])) {
                header('Location: login.php?error=pagetimeout');
                die();
            }
            // Delete
            for ($i = 0, $countList = count($list); $i < $countList; $i++) {
                $status[] = quarantine_delete($list, [$i]);
            }
        }
        break;

    case 'learn':
        break;

    default:
        die(__('dieuaction57') . ' ' . sanitizeInput($_GET['action']));
}

dbclose();
?>
</body>
</html>
