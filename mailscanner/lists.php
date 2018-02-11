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

//require_once __DIR__ . '/functions.php';
//require __DIR__ . '/login.function.php';

\MailWatch\Html::start(\MailWatch\Translation::__('wblists07'), 0, false, false);

if (isset($_GET['type'])) {
    $url_type = \MailWatch\Sanitize::deepSanitizeInput($_GET['type'], 'url');
    if (!\MailWatch\Sanitize::validateInput($url_type, 'urltype')) {
        $url_type = '';
    }
} else {
    $url_type = '';
}

if (isset($_POST['to'])) {
    $url_to = \MailWatch\Sanitize::deepSanitizeInput($_POST['to'], 'string');
    if (!empty($url_to) && !\MailWatch\Sanitize::validateInput($url_to, 'user')) {
        $url_to = '';
    }
} elseif (isset($_GET['to'])) {
    $url_to = \MailWatch\Sanitize::deepSanitizeInput($_GET['to'], 'string');
    if (!\MailWatch\Sanitize::validateInput($url_to, 'user')) {
        $url_to = '';
    }
} else {
    $url_to = '';
}

if (isset($_GET['host'])) {
    $url_host = \MailWatch\Sanitize::deepSanitizeInput($_GET['host'], 'url');
    if (!\MailWatch\Sanitize::validateInput($url_host, 'host')) {
        $url_host = '';
    }
} else {
    $url_host = '';
}

if (isset($_POST['from'])) {
    $url_from = \MailWatch\Sanitize::deepSanitizeInput($_POST['from'], 'string');
    if (!\MailWatch\Sanitize::validateInput($url_from, 'user')) {
        $url_from = '';
    }
} elseif (isset($_GET['from'])) {
    $url_from = \MailWatch\Sanitize::deepSanitizeInput($_GET['from'], 'string');
    if (!\MailWatch\Sanitize::validateInput($url_from, 'user')) {
        $url_from = '';
    }
} else {
    $url_from = '';
}

if (isset($_POST['submit'])) {
    $url_submit = \MailWatch\Sanitize::deepSanitizeInput($_POST['submit'], 'url');
    if (!\MailWatch\Sanitize::validateInput($url_submit, 'listsubmit')) {
        $url_submit = '';
    }
} elseif (isset($_GET['submit'])) {
    $url_submit = \MailWatch\Sanitize::deepSanitizeInput($_GET['submit'], 'url');
    if (!\MailWatch\Sanitize::validateInput($url_submit, 'listsubmit')) {
        $url_submit = '';
    }
} else {
    $url_submit = '';
}

if (isset($_POST['list'])) {
    $url_list = \MailWatch\Sanitize::deepSanitizeInput($_POST['list'], 'url');
    if (!\MailWatch\Sanitize::validateInput($url_list, 'list')) {
        $url_list = '';
    }
} elseif (isset($_GET['list'])) {
    $url_list = \MailWatch\Sanitize::deepSanitizeInput($_GET['list'], 'url');
    if (!\MailWatch\Sanitize::validateInput($url_list, 'list')) {
        $url_list = '';
    }
} else {
    $url_list = '';
}

if (isset($_POST['domain'])) {
    $url_domain = \MailWatch\Sanitize::deepSanitizeInput($_POST['domain'], 'url');
    if (!empty($url_domain) && !\MailWatch\Sanitize::validateInput($url_domain, 'host')) {
        $url_domain = '';
    }
} else {
    $url_domain = '';
}

if (isset($_GET['listid'])) {
    $url_id = \MailWatch\Sanitize::deepSanitizeInput($_GET['listid'], 'num');
    if (!\MailWatch\Sanitize::validateInput($url_id, 'num')) {
        $url_id = '';
    }
} else {
    $url_id = '';
}

// Split user/domain if necessary (from detail.php)
$touser = '';
$to_domain = '';
if (preg_match('/(\S+)@(\S+)/', $url_to, $split)) {
    $touser = $split[1];
    $to_domain = $split[2];
} else {
    $to_domain = $url_to;
}

// Type
switch ($url_type) {
    case 'h':
        $from = $url_host;
        break;
    case 'f':
        $from = $url_from;
        break;
    default:
        $from = $url_from;
}

$myusername = $_SESSION['myusername'];
// Validate input against the user type
$to_user_filter = [];
$to_domain_filter = [];
$to_address = '';
switch ($_SESSION['user_type']) {
    case 'U': // User
        $sql1 = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
        $result1 = \MailWatch\Db::query($sql1);

        $filter = [];
        while ($row = $result1->fetch_assoc()) {
            $filter[] = $row['filter'];
        }
        $user_filter = [];
        foreach ($filter as $user_filter_check) {
            if (preg_match('/^[^@]{1,64}@[^@]{1,255}$/', $user_filter_check)) {
                $user_filter[] = $user_filter_check;
            }
        }
        $user_filter[] = $myusername;
        foreach ($user_filter as $tempvar) {
            if (strpos($tempvar, '@')) {
                $ar = explode('@', $tempvar);
                $username = $ar[0];
                $domainname = $ar[1];
                $to_user_filter[] = $username;
                $to_domain_filter[] = $domainname;
            }
        }
        $to_user_filter = array_unique($to_user_filter);
        $to_domain_filter = array_unique($to_domain_filter);
        break;
    case 'D': // Domain Admin
        $sql1 = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
        $result1 = \MailWatch\Db::query($sql1);

        while ($row = $result1->fetch_assoc()) {
            $to_domain_filter[] = $row['filter'];
        }
        if (strpos($_SESSION['myusername'], '@')) {
            $ar = explode('@', $_SESSION['myusername']);
            $domainname = $ar[1];
            $to_domain_filter[] = $domainname;
        } else {
            $to_domain_filter[] = $_SESSION['myusername'];
        }
        $to_domain_filter = array_unique($to_domain_filter);
        break;
    case 'A': // Administrator
        $to_address = 'default';
        break;
}
switch (true) {
    case!empty($url_to):
        $to_address = $url_to;
        if (!empty($url_domain)) {
            $to_address .= '@' . $url_domain;
        }
        break;
    case!empty($url_domain):
        $to_address = $url_domain;
        break;
}

// Submitted
if ($url_submit === 'add') {
    if (false === \MailWatch\Security::checkToken($_POST['token'])) {
        die(\MailWatch\Translation::__('dietoken99'));
    }
    if (false === \MailWatch\Security::checkFormToken('/lists.php list token', $_POST['formtoken'])) {
        die(\MailWatch\Translation::__('dietoken99'));
    }

    // Check input is valid
    if (empty($url_list)) {
        $errors[] = \MailWatch\Translation::__('error071');
    }
    if (empty($from)) {
        $errors[] = \MailWatch\Translation::__('error072');
    }

    $to_domain = strtolower($url_domain);
    // Insert the data
    if (!isset($errors)) {
        switch ($url_list) {
            case 'w': // Whitelist
                $list = 'whitelist';
                $listi18 = \MailWatch\Translation::__('wl07');
                break;
            case 'b': // Blacklist
                $list = 'blacklist';
                $listi18 = \MailWatch\Translation::__('bl07');
                break;
        }
        $sql = 'REPLACE INTO ' . $list . ' (to_address, to_domain, from_address) VALUES '
            . "('" .  \MailWatch\Sanitize::safe_value($to_address) . "',"
            . "'" .  \MailWatch\Sanitize::safe_value($to_domain) . "',"
            . "'" .  \MailWatch\Sanitize::safe_value($from) . "')";
        \MailWatch\Db::query($sql);
        \MailWatch\Security::audit_log(sprintf(\MailWatch\Translation::__('auditlogadded07', true), $from, $to_address, $listi18));
    }
    $to_domain = '';
    $touser = '';
    $from = '';
    $url_list = '';
}

// Delete
if ($url_submit === 'delete') {
    if (false === \MailWatch\Security::checkToken($_GET['token'])) {
        die(\MailWatch\Translation::__('dietoken99'));
    }
    $id = $url_id;
    switch ($url_list) {
        case 'w':
            $list = 'whitelist';
            $listi18 = \MailWatch\Translation::__('wl07');
            break;
        case 'b':
            $list = 'blacklist';
            $listi18 = \MailWatch\Translation::__('bl07');
            break;
    }

    $sqlfrom = "SELECT from_address FROM $list WHERE id='$id'";
    $result = \MailWatch\Db::query($sqlfrom);
    $row = $result->fetch_array();
    $from_address = $row['from_address'];

    switch ($_SESSION['user_type']) {
        case 'U':
            $sql = "DELETE FROM $list WHERE id='$id' AND to_address='$to_address'";
            \MailWatch\Security::audit_log(sprintf(\MailWatch\Translation::__('auditlogremoved07', true), $from_address, $to_address, $listi18));
            break;
        case 'D':
            $sql = "DELETE FROM $list WHERE id='$id' AND to_domain='$to_domain'";
            \MailWatch\Security::audit_log(sprintf(\MailWatch\Translation::__('auditlogremoved07', true), $from_address, $to_address, $listi18));
            break;
        case 'A':
            $sql = "DELETE FROM $list WHERE id='$id'";
            \MailWatch\Security::audit_log(sprintf(\MailWatch\Translation::__('auditlogremoved07', true), $from_address, $to_address, $listi18));
            break;
    }

    $id =  \MailWatch\Sanitize::safe_value($url_id);
    \MailWatch\Db::query($sql);
    $to_domain = '';
    $touser = '';
    $from = '';
    $url_list = '';
}

/**
 * @param string $sql
 * @param string $list
 * @return array
 */
function build_table($sql, $list)
{
    $sth = \MailWatch\Db::query($sql);
    $table_html = '';
    $entries = $sth->num_rows;
    if ($sth->num_rows > 0) {
        $table_html .= '<table class="blackwhitelist rowhover">' . "\n";
        $table_html .= ' <tr>' . "\n";
        $table_html .= '  <th>' . \MailWatch\Translation::__('from07') . '</th>' . "\n";
        $table_html .= '  <th>' . \MailWatch\Translation::__('to07') . '</th>' . "\n";
        $table_html .= '  <th>' . \MailWatch\Translation::__('action07') . '</th>' . "\n";
        $table_html .= ' </tr>' . "\n";
        while ($row = $sth->fetch_row()) {
            $table_html .= ' <tr>' . "\n";
            $table_html .= '  <td>' . $row[1] . '</td>' . "\n";
            $table_html .= '  <td>' . $row[2] . '</td>' . "\n";
            $table_html .= '  <td><a href="lists.php?token=' . $_SESSION['token'] . '&amp;submit=delete&amp;listid=' . $row[0] . '&amp;to=' . $row[2] . '&amp;list=' . $list . '">' . \MailWatch\Translation::__('delete07') . '</a></td>' . "\n";
            $table_html .= ' </tr>' . "\n";
        }
        $table_html .= '</table>' . "\n";
    } else {
        $table_html = \MailWatch\Translation::__('noentries07') . "\n";
    }

    return ['html' => $table_html, 'entry_number' => $entries];
}

echo '
<form action="lists.php" method="post">
<table cellspacing="1" class="mail">
 <tr>
  <th colspan=2>' . \MailWatch\Translation::__('addwlbl07') . '</th>
 </tr>
 <tr>
  <td class="heading">' . \MailWatch\Translation::__('from07') . '</td>
  <td><input type="text" name="from" size=50 value="' . $from . '"></td>
 </tr>
 <tr>
  <td class="heading">' . \MailWatch\Translation::__('to07') . '</td>';
echo '<INPUT TYPE="HIDDEN" NAME="token" VALUE="' . $_SESSION['token'] . '">' . "\n";
echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . \MailWatch\Security::generateFormToken('/lists.php list token') . '">' . "\n";
switch ($_SESSION['user_type']) {
    case 'A':
        echo '<td><input type="text" name="to" size=22 value="' . $touser . '">@<input type="text" name="domain" size=25 value="' . $to_domain . '"></td>';
        break;
    case 'U':
        echo '<td> <select name="to">';
        foreach ($to_user_filter as $to_user_selection) {
            if ($touser === $to_user_selection) {
                echo '<option selected>' . $to_user_selection . '</option>';
            } else {
                echo '<option>' . $to_user_selection . '</option>';
            }
        }
        echo '</select>@<select name="domain">';
        foreach ($to_domain_filter as $to_domain_selection) {
            if ($to_domain === $to_domain_selection) {
                echo '<option selected>' . $to_domain_selection . '</option>';
            } else {
                echo '<option>' . $to_domain_selection . '</option>';
            }
        }
        echo '</td>';
        break;
    case 'D':
        echo '<td><input type="text" name="to" size=22 value="' . $touser . '">@<select name="domain">';
        foreach ($to_domain_filter as $to_domain_selection) {
            if ($to_domain === $to_domain_selection) {
                echo '<option selected>' . $to_domain_selection . '</option>';
            } else {
                echo '<option>' . $to_domain_selection . '</option>';
            }
        }
        break;
}

echo '
 </tr>
 <tr>
  <td class="heading">' . \MailWatch\Translation::__('list07') . '</td>
  <td>';

$w = '';
$b = '';
switch ($url_list) {
    case 'w':
        $w = 'CHECKED';
        break;
    case 'b':
        $b = 'CHECKED';
        break;
}
echo '   <input type="radio" value="w" name="list" ' . $w . '>' . \MailWatch\Translation::__('wl07') . '&nbsp;&nbsp;' . "\n";
echo '   <input type="radio" value="b" name="list" ' . $b . '>' . \MailWatch\Translation::__('bl07') . '' . "\n";

echo '  </td>
 </tr>
 <tr>
  <td class="heading">' . \MailWatch\Translation::__('action07') . '</td>
  <td><button type="reset" value="reset">' . \MailWatch\Translation::__('reset07') . '</button>&nbsp;&nbsp;<button type="submit" name="submit" value="add">' . \MailWatch\Translation::__('add07') . '</button></td>
 </tr>';
if (isset($errors)) {
    echo '<tr>
  <td class="heading">' . \MailWatch\Translation::__('errors07') . '</td>
  <td>' . implode('<br>', $errors) . '</td>
 </tr>';
}

$whitelist = build_table(
    'SELECT id, from_address, to_address FROM whitelist WHERE ' . $_SESSION['global_list'] . ' ORDER BY from_address',
    'w'
);
$blacklist = build_table(
    'SELECT id, from_address, to_address FROM blacklist WHERE ' . $_SESSION['global_list'] . ' ORDER BY from_address',
    'b'
);
echo '</table>
   </form>
   <br>
<table cellspacing="1" width="100%" class="mail">
<tr>
  <th class="whitelist">' . sprintf(\MailWatch\Translation::__('wlentries07'), $whitelist['entry_number']) . '</th>
  <th class="blacklist">' . sprintf(\MailWatch\Translation::__('blentries07'), $blacklist['entry_number']) . '</th>
</tr>
<tr>
  <td class="blackwhitelist">
    <!-- Whitelist -->';

echo $whitelist['html'];
echo '</td>';
echo '<td class="blackwhitelist">
<!-- Blacklist -->';
echo $blacklist['html'];
echo '</td>
</tr>
</table>';

// Add the footer
\MailWatch\Html::end();
// close the connection to the Database
\MailWatch\Db::close();
