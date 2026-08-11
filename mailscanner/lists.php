<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2021  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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

html_start(__('wblists07'), 0, false, false);

if (isset($_GET['type'])) {
    $url_type = deepSanitizeInput($_GET['type'], 'url');
    if (!validateInput($url_type, 'urltype')) {
        $url_type = '';
    }
} else {
    $url_type = '';
}

if (isset($_POST['to'])) {
    $url_to = deepSanitizeInput($_POST['to'], 'string');
    if (!empty($url_to) && !validateInput($url_to, 'user')) {
        $url_to = '';
    }
} elseif (isset($_GET['to'])) {
    $url_to = deepSanitizeInput($_GET['to'], 'string');
    if (!validateInput($url_to, 'user')) {
        $url_to = '';
    }
} else {
    $url_to = '';
}

if (isset($_GET['host'])) {
    $url_host = deepSanitizeInput($_GET['host'], 'url');
    if (!validateInput($url_host, 'host')) {
        $url_host = '';
    }
} else {
    $url_host = '';
}

if (isset($_POST['from'])) {
    $url_from = deepSanitizeInput($_POST['from'], 'string');
    if (!validateInput($url_from, 'user')) {
        $url_from = '';
    }
} elseif (isset($_GET['from'])) {
    $url_from = deepSanitizeInput($_GET['from'], 'string');
    if (!validateInput($url_from, 'user')) {
        $url_from = '';
    }
} else {
    $url_from = '';
}

if (isset($_POST['submit'])) {
    $url_submit = deepSanitizeInput($_POST['submit'], 'url');
    if (!validateInput($url_submit, 'listsubmit')) {
        $url_submit = '';
    }
} else {
    $url_submit = '';
}

if (isset($_POST['list'])) {
    $url_list = deepSanitizeInput($_POST['list'], 'url');
    if (!validateInput($url_list, 'list')) {
        $url_list = '';
    }
} elseif (isset($_GET['list'])) {
    $url_list = deepSanitizeInput($_GET['list'], 'url');
    if (!validateInput($url_list, 'list')) {
        $url_list = '';
    }
} else {
    $url_list = '';
}

if (isset($_POST['domain'])) {
    $url_domain = deepSanitizeInput($_POST['domain'], 'url');
    if (!empty($url_domain) && !validateInput($url_domain, 'host')) {
        $url_domain = '';
    }
} else {
    $url_domain = '';
}

if (isset($_POST['listid'])) {
    $url_id = deepSanitizeInput($_POST['listid'], 'num');
    if (!validateInput($url_id, 'num')) {
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
$from = match ($url_type) {
    'h' => $url_host,
    'f' => $url_from,
    default => $url_from,
};

$myusername = stripslashes((string)$_SESSION['myusername']);
$userType = (string)($_SESSION['user_type'] ?? '');
$listAdministration = \MailWatch\ApplicationFactory::listAdministration();
$to_address = '';
if ('A' === $userType) {
    $to_address = 'default';
}
switch (true) {
    case !empty($url_to):
        $to_address = $url_to;
        if (!empty($url_domain)) {
            $to_address .= '@' . $url_domain;
        }
        break;
    case !empty($url_domain):
        $to_address = $url_domain;
        break;
}

// Submitted
if ('add' === $url_submit) {
    if (false === checkToken($_POST['token'] ?? '')) {
        header('Location: login.php?error=pagetimeout');
        exit;
    }
    if (false === checkFormToken('/lists.php list token', $_POST['formtoken'] ?? '')) {
        header('Location: login.php?error=pagetimeout');
        exit;
    }

    // Check input is valid
    $kind = \MailWatch\Lists\Domain\ListKind::fromLegacyCode($url_list);
    if (null === $kind) {
        $errors[] = __('error071');
    }
    if (empty($from)) {
        $errors[] = __('error072');
    }

    $to_domain = strtolower($url_domain);
    if ('' === $to_domain && str_contains($to_address, '@')) {
        $to_domain = strtolower(substr($to_address, (int)strrpos($to_address, '@') + 1));
    }
    if (!isset($errors) && null !== $kind) {
        if (!$listAdministration->add(
            $myusername,
            $userType,
            $kind,
            stripslashes($from),
            stripslashes($to_address),
            $to_domain,
        )) {
            $errors[] = __('dievalidate99');
        } else {
            $listi18 = \MailWatch\Lists\Domain\ListKind::Allowlist === $kind ? __('wl07') : __('bl07');
            audit_log(sprintf(__('auditlogadded07', true), $from, $to_address, $listi18));
        }
    }
    $to_domain = '';
    $touser = '';
    $from = '';
    $url_list = '';
}

// Delete
if ('delete' === $url_submit) {
    if (false === checkToken($_POST['token'] ?? '')) {
        header('Location: login.php?error=pagetimeout');
        exit;
    }
    if (false === checkFormToken('/lists.php list token', $_POST['formtoken'] ?? '')) {
        header('Location: login.php?error=pagetimeout');
        exit;
    }

    $kind = \MailWatch\Lists\Domain\ListKind::fromLegacyCode($url_list);
    if (null === $kind || '' === $url_id) {
        $errors[] = __('dievalidate99');
    } else {
        $removed = $listAdministration->delete($myusername, $userType, $kind, (int)$url_id);
        if (null === $removed) {
            $errors[] = __('dievalidate99');
        } else {
            $listi18 = \MailWatch\Lists\Domain\ListKind::Allowlist === $kind ? __('wl07') : __('bl07');
            audit_log(sprintf(
                __('auditlogremoved07', true),
                $removed->fromAddress,
                $removed->toAddress,
                $listi18,
            ));
        }
    }

    $to_domain = '';
    $touser = '';
    $from = '';
    $url_list = '';
}

$overview = $listAdministration->overview($myusername, $userType);
$to_user_filter = $overview->access->selectableAddresses();
$to_domain_filter = $overview->access->domains();

/**
 * @param list<\MailWatch\Lists\Domain\ListEntry> $entries
 */
function build_table(array $entries, string $list, string $token, string $formToken): array
{
    $table_html = '';
    $entryNumber = count($entries);
    if ($entryNumber > 0) {
        $table_html .= '<table class="allowblocklist rowhover">' . "\n";
        $table_html .= ' <tr>' . "\n";
        $table_html .= '  <th>' . __('from07') . '</th>' . "\n";
        $table_html .= '  <th>' . __('to07') . '</th>' . "\n";
        $table_html .= '  <th>' . __('action07') . '</th>' . "\n";
        $table_html .= ' </tr>' . "\n";
        foreach ($entries as $entry) {
            $fromAddress = htmlspecialchars($entry->fromAddress, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $toAddress = htmlspecialchars($entry->toAddress, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $table_html .= ' <tr>' . "\n";
            $table_html .= '  <td>' . $fromAddress . '</td>' . "\n";
            $table_html .= '  <td>' . $toAddress . '</td>' . "\n";
            $table_html .= '  <td><form action="lists.php" method="post">'
                . '<input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="formtoken" value="' . htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="submit" value="delete">'
                . '<input type="hidden" name="listid" value="' . $entry->id . '">'
                . '<input type="hidden" name="list" value="' . $list . '">'
                . '<button type="submit">' . __('delete07') . '</button></form></td>' . "\n";
            $table_html .= ' </tr>' . "\n";
        }
        $table_html .= '</table>' . "\n";
    } else {
        $table_html = __('noentries07') . "\n";
    }

    return ['html' => $table_html, 'entry_number' => $entryNumber];
}

echo '
<form action="lists.php" method="post">
<table cellspacing="1" class="mail">
 <tr>
  <th colspan=2>' . __('addwlbl07') . '</th>
 </tr>
 <tr>
  <td class="heading">' . __('from07') . '</td>
  <td><input type="text" name="from" size=50 value="' . htmlspecialchars($from, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></td>
 </tr>
 <tr>
  <td class="heading">' . __('to07') . '</td>';
echo '<INPUT TYPE="HIDDEN" NAME="token" VALUE="' . $_SESSION['token'] . '">' . "\n";
echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . generateFormToken('/lists.php list token') . '">' . "\n";
switch ($_SESSION['user_type']) {
    case 'A':
        echo '<td><input type="text" name="to" size=22 value="' . htmlspecialchars(stripslashes($touser), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">@<input type="text" name="domain" size=25 value="' . htmlspecialchars($to_domain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></td>';
        break;
    case 'U':
        echo '<td> <select name="to">';
        foreach ($to_user_filter as $to_user_selection) {
            $escapedSelection = htmlspecialchars(stripslashes($to_user_selection), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($url_to === $to_user_selection) {
                echo '<option selected>' . $escapedSelection . '</option>';
            } else {
                echo '<option>' . $escapedSelection . '</option>';
            }
        }
        echo '</select></td>';
        break;
    case 'D':
        echo '<td><input type="text" name="to" size=22 value="' . htmlspecialchars(stripslashes($touser), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">@<select name="domain">';
        foreach ($to_domain_filter as $to_domain_selection) {
            $escapedSelection = htmlspecialchars($to_domain_selection, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($to_domain === $to_domain_selection) {
                echo '<option selected>' . $escapedSelection . '</option>';
            } else {
                echo '<option>' . $escapedSelection . '</option>';
            }
        }
        echo '</select></td>';
        break;
    default:
        echo '<td></td>';
        break;
}

echo '
 </tr>
 <tr>
  <td class="heading">' . __('list07') . '</td>
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
echo '   <input type="radio" value="w" name="list" ' . $w . '>' . __('wl07') . '&nbsp;&nbsp;' . "\n";
echo '   <input type="radio" value="b" name="list" ' . $b . '>' . __('bl07') . '' . "\n";

echo '  </td>
 </tr>
 <tr>
  <td class="heading">' . __('action07') . '</td>
  <td><button type="reset" value="reset">' . __('reset07') . '</button>&nbsp;&nbsp;<button type="submit" name="submit" value="add">' . __('add07') . '</button></td>
 </tr>';
if (isset($errors)) {
    echo '<tr>
  <td class="heading">' . __('errors07') . '</td>
  <td>' . implode('<br>', $errors) . '</td>
 </tr>';
}

$formToken = generateFormToken('/lists.php list token');
$whitelist = build_table($overview->allowlist, 'w', (string)$_SESSION['token'], $formToken);
$blacklist = build_table($overview->blocklist, 'b', (string)$_SESSION['token'], $formToken);
echo '</table>
   </form>
   <br>
<table cellspacing="1" width="100%" class="mail">
<tr>
  <th class="whitelist">' . sprintf(__('wlentries07'), $whitelist['entry_number']) . '</th>
  <th class="blacklist">' . sprintf(__('blentries07'), $blacklist['entry_number']) . '</th>
</tr>
<tr>
  <td class="allowblocklist">
    <!-- Allowlist -->';

echo $whitelist['html'];
echo '</td>';
echo '<td class="allowblocklist">
<!-- Blocklist -->';
echo $blacklist['html'];
echo '</td>
</tr>
</table>';

// Add the footer
html_end();
// close the connection to the Database
dbclose();
