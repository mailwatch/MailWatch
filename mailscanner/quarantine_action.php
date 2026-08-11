<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 Copyright (C) 2014-2021  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)

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
                        <th><?php echo __('result57'); ?></th>
                    </tr>
                    <tr>
                        <td><?php echo $status; ?></td>
                    </tr>
                    <tr>
                        <td align="center"><b><a href="javascript:window.close()"><?php echo __('closewindow57'); ?></a></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
<?php
}

if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
    exit(__('dievalidate99'));
}
if (!isset($_POST['id'])) {
    exit(__('dienoid57'));
}
if (!isset($_POST['action'])) {
    exit(__('dienoaction57'));
}
if (false === checkToken($_POST['token'] ?? '')
    || false === checkFormToken('/quarantine_action.php form token', $_POST['formtoken'] ?? '')) {
    header('Location: login.php?error=pagetimeout');
    exit;
}

$id = deepSanitizeInput($_POST['id'], 'url');
if (false === $id || !validateInput($id, 'msgid')) {
    exit;
}
$action = deepSanitizeInput($_POST['action'], 'url');
if (!in_array($action, ['release', 'delete', 'learn'], true)) {
    exit(__('dieuaction57') . ' ' . sanitizeInput($action));
}

$list = quarantine_list_items($id, false, $_SESSION['global_filter']);
if (!is_array($list)) {
    exit((string)$list);
}
if (0 === count($list)) {
    exit(__('diemnf57'));
}
$quarantineAccess = \MailWatch\ApplicationFactory::quarantineAccess((string)$_SESSION['user_type']);
$dangerous = [] !== array_filter(
    $list,
    static fn(mixed $item): bool => is_array($item) && 'Y' === ($item['dangerous'] ?? 'N'),
);

switch ($action) {
    case 'release':
        if (!$quarantineAccess->canRelease($dangerous)) {
            exit(__('permdenied60'));
        }
        $result = '';
        if (1 === count($list)) {
            $to = $list[0]['to'];
            $result = quarantine_release($list, [0], $to, false, $_SESSION['global_filter']);
        } else {
            for ($i = 0, $countList = count($list); $i < $countList; ++$i) {
                if (preg_match('/message\/rfc822/', (string)$list[$i]['type'])) {
                    $result = quarantine_release($list, [$i], $list[$i]['to'], false, $_SESSION['global_filter']);
                }
            }
        }

        if (isset($_POST['html'])) {
            // Display success
            simple_html_start();
            simple_html_result($result);
            simple_html_end();
        }
        break;

    case 'delete':
        $status = [];
        for ($i = 0, $countList = count($list); $i < $countList; ++$i) {
            $status[] = quarantine_delete($list, [$i], false, $_SESSION['global_filter']);
        }
        if (isset($_POST['html'])) {
            simple_html_start();
            simple_html_result(implode('<br/>', $status));
            simple_html_end();
        }
        break;

    case 'learn':
        break;
}

dbclose();
?>
</body>
</html>
