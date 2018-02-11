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

//require_once __DIR__ . '/functions.php';

//require __DIR__ . '/login.function.php';

\MailWatch\Html::start(\MailWatch\Translation::__('usermgnt12'), 0, false, false);

/**
 * @param string $value
 * @param string $type
 * @return string
 */
function getHtmlMessage($value, $type)
{
    switch ($type) {
        case 'error':
            return '<h1 class="center error">' . $value . '</h1>';

        case 'success':
            return '<h1 class="center success">' . $value . '</h1>';

        default:
            return $value;
    }
}

/**
 * @param string $username
 * @param string $method
 */
function testSameDomainMembership($username, $method)
{
    $parts = explode('@', $username);
    $sql = "SELECT filter FROM user_filters WHERE username = '" . $_SESSION['myusername'] . "'";
    $result = \MailWatch\Db::query($sql);
    $filter_domain = [];
    for ($i=0;$i<$result->num_rows;$i++) {
        $filter = $result->fetch_row();
        $filter_domain[] = $filter[0];
    }
    if ($_SESSION['user_type'] === 'D' && count($parts) === 1 && $_SESSION['domain'] !== '') {
        return getHtmlMessage(\MailWatch\Translation::__('error'.$method.'nodomainforbidden12'), 'error');
    } elseif ($_SESSION['user_type'] === 'D' && count($parts) === 2 && ($parts[1] !== $_SESSION['domain'] && in_array($parts[1], $filter_domain, true) === false)) {
        return getHtmlMessage(sprintf(\MailWatch\Translation::__('error'.$method.'domainforbidden12'), $parts[1]), 'error');
    }
    return true;
}

/**
 * @param string $username
 * @param string $userType
 * @param string $oldUserType
 */
function testPermissions($username, $userType, $oldUserType)
{
    if (($_SESSION['user_type'] !== 'A' && $oldUserType === 'A')|| $_SESSION['user_type'] === 'D' && $_SESSION['myusername'] !== $username && $userType !== 'U' && (!defined('ENABLE_SUPER_DOMAIN_ADMINS') || ENABLE_SUPER_DOMAIN_ADMINS === false)) {
        return getHtmlMessage(\MailWatch\Translation::__('erroradminforbidden12'), 'error');
    } elseif ($_SESSION['user_type'] === 'D' && $userType === 'A') {
        return getHtmlMessage(\MailWatch\Translation::__('errortypesetforbidden12'), 'error');
    }
    return true;
}

/**
 * @param string $username
 * @param string $usertype
 * @param string $oldUsername
 */
function testValidUser($username, $usertype, $oldUsername)
{
    if ($usertype !== 'A' && \MailWatch\Sanitize::validateInput($username, 'email') === false && (!defined('ALLOW_NO_USER_DOMAIN') || ALLOW_NO_USER_DOMAIN === false)) {
        return getHtmlMessage(\MailWatch\Translation::__('forallusers12'), 'error');
    } elseif (!isset($_POST['password'], $_POST['password1'])) {
        return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
    } elseif ($_POST['password'] === '') {
        return getHtmlMessage(\MailWatch\Translation::__('errorpwdreq12'), 'error');
    } elseif ($_POST['password'] !== $_POST['password1']) {
        return getHtmlMessage(\MailWatch\Translation::__('errorpass12'), 'error');
    } elseif ($username === '') {
        return getHtmlMessage(\MailWatch\Translation::__('erroruserreq12'), 'error');
    } elseif ($oldUsername !== $username && checkForExistingUser($username)) {
        return getHtmlMessage(sprintf(\MailWatch\Translation::__('userexists12'), \MailWatch\Sanitize::sanitizeInput($username)), 'error');
    }
    return true;
}

function testToken()
{
    if (!isset($_POST['token']) && !isset($_GET['token'])) {
        return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
    }
    if ((isset($_POST['token']) && (false === \MailWatch\Security::checkToken($_POST['token'])))
          || (isset($_GET['token']) && (false === \MailWatch\Security::checkToken($_GET['token'])))) {
        return getHtmlMessage(\MailWatch\Translation::__('dietoken99'), 'error');
    }
    return true;
}

function getUserById($additionalFields = false)
{
    if (isset($_POST['id'])) {
        $uid = (int)$_POST['id'];
    } elseif (isset($_GET['id'])) {
        $uid = (int)$_GET['id'];
    } else {
        return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
    }
    if (($uid = \MailWatch\Sanitize::deepSanitizeInput($uid, 'num')) < -1) {
        return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
    }
    $sql = 'SELECT id, username, type' . ($additionalFields ? ', fullname, quarantine_report, quarantine_rcpt, spamscore, highspamscore, noscan, login_timeout, last_login' : '') . " FROM users WHERE id='" . $uid . "'";
    $result = \MailWatch\Db::query($sql);
    if ($result->num_rows === 0) {
        \MailWatch\Security::audit_log(sprintf(\MailWatch\Translation::__('auditlogunknownuser12'), $_SESSION['myusername'], $uid));
        return getHtmlMessage(\MailWatch\Translation::__('accessunknownuser12'), 'error');
    }
    return $result->fetch_object();
}

/**
 * @param string $action 'edit' or 'new'
 * @param int $uid
 * @param string $lastlogin
 * @param string $username
 * @param string $fullname
 * @param array $type array which has 'selected' as value for selected type
 * @param string|float|int $timeout
 * @param string $quarantine_report 'checked' if box shall be ticked
 * @param string $quarantine_rcpt
 * @param string $noscan checkbox default to 'checked'
 * @param string|float|int $spamscore default 0
 * @param string|float|int $highspamscore default 0
 */

function printUserFormular(
    $action,
    $uid = '',
    $lastlogin = '',
    $username = '',
    $fullname = '',
    $type = ['A'=>'', 'D'=>'', 'U'=>'selected', 'R'=>''],
           $timeout = '',
    $quarantine_report = '',
    $quarantine_rcpt = '',
    $noscan = 'checked',
    $spamscore = '0',
    $highspamscore = '0'
) {
    echo '<div id="formerror" class="hidden"></div>';
    echo '<FORM METHOD="POST" ACTION="user_manager.php" ONSUBMIT="return validateForm();" AUTOCOMPLETE="off">' . "\n";
    echo '<INPUT TYPE="HIDDEN" NAME="token" VALUE="' . $_SESSION['token'] . '">' . "\n";
    if ($action === 'edit') {
        echo '<INPUT TYPE="HIDDEN" NAME="id" VALUE="' . $uid . '">' . "\n";
        $formheader =  \MailWatch\Translation::__('edituser12') . ' ' . $username;
        $password = 'XXXXXXXX';
    } else {
        $formheader = \MailWatch\Translation::__('newuser12');
        $password = '';
    }
    echo '<INPUT TYPE="HIDDEN" NAME="action" VALUE="' . $action . '">' . "\n";
    echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . \MailWatch\Security::generateFormToken('/user_manager.php ' . $action . ' token') . '">' . "\n";
    echo '<TABLE CLASS="mail" BORDER="0" CELLPADDING="1" CELLSPACING="1">' . "\n";
    echo ' <TR><TD CLASS="heading" COLSPAN="2" ALIGN="CENTER">' . $formheader . '</TD></TR>' . "\n";
    if (!defined('ALLOW_NO_USER_DOMAIN') || !ALLOW_NO_USER_DOMAIN) {
        echo ' <TR><TD CLASS="message" COLSPAN="2" ALIGN="CENTER">' . \MailWatch\Translation::__('forallusers12') . '</TD></TR>' . "\n";
    }
    if ($action === 'edit') {
        echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('lastlogin12') . '</TD><TD>' . $lastlogin . '</TD></TR>' . "\n";
    }
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('username0212') . '</TD><TD><INPUT TYPE="TEXT" ID="username" NAME="username" VALUE="' . $username . '"></TD></TR>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('name12') . '</TD><TD><INPUT TYPE="TEXT" NAME="fullname" VALUE="' . $fullname . '"></TD></TR>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('password12') . '</TD><TD><INPUT TYPE="PASSWORD" ID="password" NAME="password" VALUE="' . $password . '"></TD></TR>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('retypepassword12') . '</TD><TD><INPUT TYPE="PASSWORD" ID="retypepassword" NAME="password1" VALUE="' . $password . '"></TD></TR>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('usertype12') . '</TD>
<TD><SELECT NAME="type">
<OPTION ' . $type['A'] . ' VALUE="A">' . \MailWatch\Translation::__('admin12') . '</OPTION>
<OPTION ' . $type['D'] . ' VALUE="D">' . \MailWatch\Translation::__('domainadmin12') . '</OPTION>
<OPTION ' . $type['U'] . ' VALUE="U">' . \MailWatch\Translation::__('user12') . '</OPTION>
' . ($action === 'edit' ? '<OPTION ' . $type['R'] . ' VALUE="R">' . \MailWatch\Translation::__('userregex12') . '</OPTION>' : '') . '
</SELECT></TD></TR>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('usertimeout12') . '</TD><TD><INPUT TYPE="TEXT" NAME="timeout" VALUE="' . $timeout . '" size="5"> <span class="font-1em">' . \MailWatch\Translation::__('empty12') . '=' . \MailWatch\Translation::__('usedefault12') . '</span></TD></TR>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('quarrep12') . '</TD><TD><INPUT TYPE="CHECKBOX" NAME="quarantine_report" ' . $quarantine_report . '> <span class="font-1em">' . \MailWatch\Translation::__('senddaily12') . '</span>
' . ($action === 'edit' ? '<button type="submit" name="action" value="sendReportNow">' . \MailWatch\Translation::__('sendReportNow12') . '</button>' : '') . '
 </td></tr>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('quarreprec12') . '</TD><TD><INPUT TYPE="TEXT" NAME="quarantine_rcpt" VALUE="' . $quarantine_rcpt . '"><br><span class="font-1em">' . \MailWatch\Translation::__('overrec12') . '</span></TD>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('scanforspam12') . '</TD><TD><INPUT TYPE="CHECKBOX" NAME="noscan" ' . $noscan . '> <span class="font-1em">' . \MailWatch\Translation::__('scanforspam212') . '</span></TD></TR>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('pontspam12') . '</TD><TD><INPUT TYPE="TEXT" NAME="spamscore" VALUE="' . $spamscore . '" size="4"> <span class="font-1em">0=' . \MailWatch\Translation::__('usedefault12') . '</span></TD></TR>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('hpontspam12') . '</TD><TD><INPUT TYPE="TEXT" NAME="highspamscore" VALUE="' . $highspamscore . '" size="4"> <span class="font-1em">0=' . \MailWatch\Translation::__('usedefault12') . '</span></TD></TR>' . "\n";
    echo ' <TR><TD CLASS="heading">' . \MailWatch\Translation::__('action_0212') . '</TD><TD><INPUT TYPE="RESET" VALUE="' . \MailWatch\Translation::__('reset12') . '">&nbsp;&nbsp;<button type="submit" name="submit">' . ($action === 'edit' ? \MailWatch\Translation::__('update12') : \MailWatch\Translation::__('create12')) . '</button></TD></TR>' . "\n";
    echo "</TABLE></FORM><BR>\n";
}

function storeUser($n_username, $n_type, $uid, $oldUsername = '', $oldType = '')
{
    if (!isset($_POST['fullname'], $_POST['spamscore'], $_POST['highspamscore'], $_POST['timeout'], $_POST['quarantine_rcpt'])) {
        return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
    }
    $n_fullname = \MailWatch\Sanitize::deepSanitizeInput($_POST['fullname'], 'string');
    if (!\MailWatch\Sanitize::validateInput($n_fullname, 'general')) {
        $n_fullname = '';
    }
    $n_password =  \MailWatch\Sanitize::safe_value(password_hash($_POST['password'], PASSWORD_DEFAULT));

    if (!\MailWatch\Sanitize::validateInput($n_type, 'type')) {
        $n_type = 'U';
    }
    $spamscore = \MailWatch\Sanitize::deepSanitizeInput($_POST['spamscore'], 'float');
    if (!\MailWatch\Sanitize::validateInput($spamscore, 'float')) {
        $spamscore = '0';
    }
    $highspamscore = \MailWatch\Sanitize::deepSanitizeInput($_POST['highspamscore'], 'float');
    if (!\MailWatch\Sanitize::validateInput($highspamscore, 'float')) {
        $highspamscore = '0';
    }
    $timeout = \MailWatch\Sanitize::deepSanitizeInput($_POST['timeout'], 'num');
    if (!\MailWatch\Sanitize::validateInput($timeout, 'timeout')) {
        $timeout = '-1';
    }
    $n_quarantine_report = '1';
    if (!isset($_POST['quarantine_report'])) {
        $n_quarantine_report = '0';
    }
    $noscan = '0';
    if (!isset($_POST['noscan'])) {
        $noscan = '1';
    }
    $quarantine_rcpt = \MailWatch\Sanitize::deepSanitizeInput($_POST['quarantine_rcpt'], 'string');
    if (!\MailWatch\Sanitize::validateInput($quarantine_rcpt, 'user')) {
        $quarantine_rcpt = '';
    }

    $type = [];
    $type['A'] = \MailWatch\Translation::__('admin12', true);
    $type['D'] = \MailWatch\Translation::__('domainadmin12', true);
    $type['U'] = \MailWatch\Translation::__('user12', true);
    $type['R'] = \MailWatch\Translation::__('user12', true);
    if ($uid === -1) {//new user
        $sql = "INSERT INTO users (username, fullname, password, type, quarantine_report, login_timeout, spamscore, highspamscore, noscan, quarantine_rcpt)
                        VALUES ('$n_username','$n_fullname','$n_password','$n_type','$n_quarantine_report','$timeout','$spamscore','$highspamscore','$noscan','$quarantine_rcpt')";
        \MailWatch\Db::query($sql);
        \MailWatch\Security::audit_log(\MailWatch\Translation::__('auditlog0112', true) . ' ' . $type[$n_type] . " '" . $n_username . "' (" . $n_fullname . ') ' . \MailWatch\Translation::__('auditlog0212', true));
        return getHtmlMessage(sprintf(\MailWatch\Translation::__('usercreated12'), $n_username), 'success');
    } else {
        if ($_POST['password'] !== 'XXXXXXXX') {// Password reset required
            $sql = "UPDATE users SET username='$n_username', fullname='$n_fullname', password='$n_password', type='$n_type', quarantine_report='$n_quarantine_report', spamscore='$spamscore', highspamscore='$highspamscore', noscan='$noscan', quarantine_rcpt='$quarantine_rcpt', login_timeout='$timeout' WHERE id='$uid'";
        } else {
            $sql = "UPDATE users SET username='$n_username', fullname='$n_fullname', type='$n_type', quarantine_report='$n_quarantine_report', spamscore='$spamscore', highspamscore='$highspamscore', noscan='$noscan', quarantine_rcpt='$quarantine_rcpt', login_timeout='$timeout' WHERE id='$uid'";
        }
        \MailWatch\Db::query($sql);
        // Update user_filters if username was changed
        if ($oldUsername !== $n_username) {
            $sql = "UPDATE user_filters SET username='$n_username' WHERE username = '$oldUsername'";
            \MailWatch\Db::query($sql);
        }
        if ($oldType !== $n_type) {
            \MailWatch\Security::audit_log(
                \MailWatch\Translation::__('auditlog0312', true) . " '" . $n_username . "' (" . $n_fullname . ') ' . \MailWatch\Translation::__('auditlogfrom12', true) . ' ' . $type[$oldType] . ' ' . \MailWatch\Translation::__('auditlogto12', true) . ' ' . $type[$n_type]
            );
        }
        return getHtmlMessage(sprintf(\MailWatch\Translation::__('useredited12'), $oldUsername), 'success');
    }
}

function newUser()
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    } elseif (!isset($_POST['submit'])) {
        return printUserFormular('new');
    } elseif (!isset($_POST['formtoken'], $_POST['username'], $_POST['type'])) {
        return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
    } elseif (false === \MailWatch\Security::checkFormToken('/user_manager.php new token', $_POST['formtoken'])) {
        return getHtmlMessage(\MailWatch\Translation::__('dietoken99'), 'error');
    }
    $username = html_entity_decode(\MailWatch\Sanitize::deepSanitizeInput($_POST['username'], 'string'));
    $n_type = \MailWatch\Sanitize::deepSanitizeInput($_POST['type'], 'url');
    if ($username === false || !\MailWatch\Sanitize::validateInput($username, 'user')) {
        $username = '';
    }
    if (false === $n_type) {
        return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
    } elseif (is_string($membertest = testSameDomainMembership($username, 'create'))) {
        return $membertest;
    } elseif (is_string($permissiontest = testPermissions($username, $n_type, ''))) {
        return $permissiontest;
    } elseif (is_string($validuser = testValidUser($username, $n_type, ''))) {
        return $validuser;
    }
    $n_username =  \MailWatch\Sanitize::safe_value($username);
    return storeUser($n_username, $n_type, -1, '', '');
}

function editUser()
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    }
    // if editing user is domain admin check if he tries to edit a user from the same domain. if we do the update we also have to check the new username
    // Validate id
    if (is_string($user = getUserById(true))) {
        return $user;
    } elseif (is_string($membertest = testSameDomainMembership($user->username, 'edit'))) {
        return $membertest;
    } elseif (!isset($_POST['submit'])) {
        $quarantine_report = '';
        if ((int)$user->quarantine_report === 1) {
            $quarantine_report = 'checked="checked"';
        }
        $noscan = '';
        if ((int)$user->noscan === 0) {
            $noscan = 'checked="checked"';
        }
        $timeout = '';
        if ($user->login_timeout !== '-1') {
            $timeout = $user->login_timeout;
        }

        $types = [];
        $types['A'] = '';
        $types['D'] = '';
        $types['U'] = '';
        $types['R'] = '';

        $timestamp = (int)$user->last_login;
        $lastlogin = \MailWatch\Translation::__('never12');
        if ($timestamp >= 0) {
            if (defined('DATE_FORMAT')) {
                $dateformat = preg_replace('/%/', '', DATE_FORMAT);
            } else {
                $dateformat = 'm/d/y';
            }
            if (defined('TIME_FORMAT')) {
                $timeformat = preg_replace('/%/', '', TIME_FORMAT);
            } else {
                $timeformat = 'H:i:s';
            }
            $lastlogin = date($dateformat . ' ' . $timeformat, $timestamp);
        }
        $types[$user->type] = 'SELECTED';

        return printUserFormular('edit', $user->id, $lastlogin, $user->username, $user->fullname, $types, $timeout, $quarantine_report, $user->quarantine_rcpt, $noscan, $user->spamscore, $user->highspamscore);
    } elseif (!isset($_POST['formtoken'], $_POST['username'], $_POST['type'])) {
        return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
    } elseif (false === \MailWatch\Security::checkFormToken('/user_manager.php edit token', $_POST['formtoken'])) {
        return getHtmlMessage(\MailWatch\Translation::__('dietoken99'), 'error');
    }
    // Do update
    $username = html_entity_decode(\MailWatch\Sanitize::deepSanitizeInput($_POST['username'], 'string'));
    if (!\MailWatch\Sanitize::validateInput($username, 'user')) {
        $username = '';
    }
    $n_type = \MailWatch\Sanitize::deepSanitizeInput($_POST['type'], 'url');
    if (false === $n_type) {
        return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
    } elseif (is_string($membertest = testSameDomainMembership($username, 'to'))) {
        return $membertest;
    } elseif (is_string($permissiontest = testPermissions($username, $n_type, $user->type))) {
        return $permissiontest;
    } elseif (is_string($validusertest = testValidUser($username, $n_type, $user->username))) {
        return $validusertest;
    } else {
        return storeUser($username, $n_type, $user->id, $user->username, $user->type);
    }
}

function deleteUser()
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    } elseif (is_string($user = getUserById())) {
        return $user;
    } elseif (is_string($membertest = testSameDomainMembership($user->username, 'delete'))) {
        return $membertest;
    } elseif ($_SESSION['user_type'] === 'D' && $user->type !== 'U') {
        return getHtmlMessage(\MailWatch\Translation::__('erroradminforbidden12'), 'error');
    } elseif ($_SESSION['myusername'] === $user->username) {
        return getHtmlMessage(\MailWatch\Translation::__('errordeleteself12'), 'error');
    }
    $sql = "DELETE u,f FROM users u LEFT JOIN user_filters f ON u.username = f.username WHERE u.username='" .  \MailWatch\Sanitize::safe_value($user->username) . "'";
    \MailWatch\Db::query($sql);
    \MailWatch\Security::audit_log(sprintf(\MailWatch\Translation::__('auditlog0412', true), $user->username));
    return getHtmlMessage(sprintf(\MailWatch\Translation::__('userdeleted12'), $user->username), 'success');
}

function userFilter()
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    } elseif (is_string($user = getUserById())) {
        return $user;
    } elseif (is_string($membertest = testSameDomainMembership($user->username, 'filter'))) {
        return $membertest;
    } elseif (is_string($permissiontest = testPermissions($user->username, $user->type, ''))) {
        return $permissiontest;
    }

    $getFilter = '';
    if (isset($_POST['filter'])) {
        if (false === \MailWatch\Security::checkFormToken('/user_manager.php filter token', $_POST['formtoken'])) {
            return getHtmlMessage(\MailWatch\Translation::__('dietoken99'), 'error');
        }
        $getFilter = \MailWatch\Sanitize::deepSanitizeInput($_POST['filter'], 'url');
        if (!\MailWatch\Sanitize::validateInput($getFilter, 'email') && !\MailWatch\Sanitize::validateInput($getFilter, 'host')) {
            $getFilter = '';
        }
    }

    if (isset($_POST['new']) && $getFilter !== '') {
        $getActive = \MailWatch\Sanitize::deepSanitizeInput($_POST['active'], 'url');
        if (!\MailWatch\Sanitize::validateInput($getActive, 'yn')) {
            return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
        }
        $sql = "INSERT INTO user_filters (username, filter, active) VALUES ('" .  \MailWatch\Sanitize::safe_value($user->username) . "','" .  \MailWatch\Sanitize::safe_value($getFilter) . "','" .  \MailWatch\Sanitize::safe_value($getActive) . "')";
        \MailWatch\Db::query($sql);
        if (DEBUG === true) {
            echo $sql;
        }
    }

    if (isset($_GET['delete'], $_GET['filter'])) {
        $getFilter = \MailWatch\Sanitize::deepSanitizeInput($_GET['filter'], 'url');
        if (!\MailWatch\Sanitize::validateInput($getFilter, 'email') && !\MailWatch\Sanitize::validateInput($getFilter, 'host')) {
            return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
        }
        $sql = "DELETE FROM user_filters WHERE username='" .  \MailWatch\Sanitize::safe_value($user->username) . "' AND filter='" .  \MailWatch\Sanitize::safe_value($getFilter) . "'";
        \MailWatch\Db::query($sql);
        if (DEBUG === true) {
            echo $sql;
        }
    }
    if (isset($_GET['change_state'], $_GET['filter'])) {
        $getFilter = \MailWatch\Sanitize::deepSanitizeInput($_GET['filter'], 'url');
        if (!\MailWatch\Sanitize::validateInput($getFilter, 'email') && !\MailWatch\Sanitize::validateInput($getFilter, 'host')) {
            return getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error');
        }
        $sql = "SELECT active FROM user_filters WHERE username='" .  \MailWatch\Sanitize::safe_value($user->username) . "' AND filter='" .  \MailWatch\Sanitize::safe_value($getFilter) . "'";
        $result = \MailWatch\Db::query($sql);
        $row = $result->fetch_row();
        $active = 'Y';
        if ($row[0] === 'Y') {
            $active = 'N';
        }
        $sql = "UPDATE user_filters SET active='" . $active . "' WHERE username='" .  \MailWatch\Sanitize::safe_value($user->username) . "' AND filter='" .  \MailWatch\Sanitize::safe_value($getFilter) . "'";
        \MailWatch\Db::query($sql);
    }
    $sql = "SELECT filter, CASE WHEN active='Y' THEN '" . \MailWatch\Translation::__('yes12') . "' ELSE '" . \MailWatch\Translation::__('no12') . "' END AS active, CONCAT('<a href=\"javascript:delete_filter\(\'" .  \MailWatch\Sanitize::safe_value($user->id) . "\',\'',filter,'\'\)\">" . \MailWatch\Translation::__('delete12') . "</a>&nbsp;&nbsp;<a href=\"javascript:change_state(\'" .  \MailWatch\Sanitize::safe_value($user->id) . "\',\'',filter,'\')\">" . \MailWatch\Translation::__('toggle12') . "</a>') AS actions FROM user_filters WHERE username='" .  \MailWatch\Sanitize::safe_value($user->username) . "'";
    $result = \MailWatch\Db::query($sql);
    echo '<FORM METHOD="POST" ACTION="user_manager.php">' . "\n";
    echo '<INPUT TYPE="HIDDEN" NAME="action" VALUE="filters">' . "\n";
    echo '<INPUT TYPE="HIDDEN" NAME="token" VALUE="' . $_SESSION['token'] . '">' . "\n";
    echo '<INPUT TYPE="HIDDEN" NAME="id" VALUE="' . $user->id . '">' . "\n";
    echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . \MailWatch\Security::generateFormToken('/user_manager.php filter token') . '">' . "\n";

    echo '<INPUT TYPE="hidden" NAME="new" VALUE="true">' . "\n";
    echo '<TABLE CLASS="mail" BORDER="0" CELLPADDING="1" CELLSPACING="1">' . "\n";
    echo ' <TR><TH COLSPAN=3>' . \MailWatch\Translation::__('userfilter12') . ' ' . $user->username . '</TH></TR>' . "\n";
    echo ' <TR><TH>' . \MailWatch\Translation::__('filter12') . '</TH><TH>' . \MailWatch\Translation::__('active12') . '</TH><TH>' . \MailWatch\Translation::__('action12') . '</TH></TR>' . "\n";
    while ($row = $result->fetch_object()) {
        echo ' <TR><TD>' . $row->filter . '</TD><TD>' . $row->active . '</TD> ';
        if ($_SESSION['user_type'] === 'D' && $user->username === $_SESSION['myusername']) {
            echo '<TD>' . \MailWatch\Translation::__('nofilteraction12') . '</TD></TR>' . "\n";
        } else {
            echo '<TD>' . $row->actions . '</TD></TR>' . "\n";
        }
    }
    // Prevent domain admins from altering their own filters
    if ($_SESSION['user_type'] === 'A' || ($_SESSION['user_type'] === 'D' && $user->username !== $_SESSION['myusername'])) {
        echo ' <TR><TD><INPUT TYPE="text" NAME="filter"></TD><TD><SELECT NAME="active"><OPTION VALUE="Y">' . \MailWatch\Translation::__('yes12') . '<OPTION VALUE="N">' . \MailWatch\Translation::__('no12') . '</SELECT></TD><TD><INPUT TYPE="submit" VALUE="' . \MailWatch\Translation::__('add12') . '"></TD></TR>' . "\n";
    }
    echo '</TABLE><BR>' . "\n";
    echo '</FORM>' . "\n";
}

function sendReport()
{
    include_once __DIR__ . '/quarantine_report.inc.php';
    $requirementsCheck = Quarantine_Report::check_quarantine_report_requirements();
    if ($requirementsCheck !== true) {
        error_log('Requirements for sending quarantine reports not met: ' . $requirementsCheck);
        return getHtmlMessage(\MailWatch\Translation::__('checkReportRequirementsFailed12'), 'error');
    } elseif (is_string($user = getUserById())) {
        return $user;
    } elseif (is_string($membertest = testSameDomainMembership($user->username, 'report'))) {
        return $membertest;
    }

    $quarantine_report = new Quarantine_Report();
    $reportResult = $quarantine_report->send_quarantine_reports([$user->username]);
    if ($reportResult['succ'] >= 0) {
        return getHtmlMessage(\MailWatch\Translation::__('quarantineReportSend12'), 'success');
    } else {
        return getHtmlMessage(\MailWatch\Translation::__('quarantineReportFailed12'), 'success');
    }
}

function logoutUser()
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    } elseif (is_string($user = getUserById())) {
        return $user;
    } elseif (is_string($membertest = testSameDomainMembership($user->username, 'logout'))) {
        return $membertest;
    } elseif (is_string($permissiontest = testPermissions($user->username, $user->type, ''))) {
        return $permissiontest;
    } elseif (is_string($validuser = testValidUser($user->username, $user->type, ''))) {
        return $validuser;
    }

    $sql = "UPDATE users SET login_expiry='-1' WHERE id='$user->id'";
    \MailWatch\Db::query($sql);
    if (DEBUG === true) {
        echo $sql;
    }

    return getHtmlMessage(sprintf(\MailWatch\Translation::__('userloggedout12'), $user->username), 'success');
}
?>
<script>
   function checkPasswords() {
       var pass0 = document.getElementById("password");
       var pass1 = document.getElementById("retypepassword");
       pass0.classList.remove("inputerror");
       pass1.classList.remove("inputerror");
       if(pass0.value !== pass1.value) {
           var errorDiv = document.getElementById("formerror");
           var errormsg = errorDiv.innerHTML;
           errorDiv.innerHTML = errormsg+"<?php echo \MailWatch\Translation::__('errorpass12');?><br>";
           errorDiv.classList.remove("hidden");
           pass0.classList.add("inputerror");
           pass1.classList.add("inputerror");
           return false;
       } else {
           return true;
       }
   }

   function requiredFields() {
       var valid = true;
       var error = "";
       var username = document.getElementById("username");
       var pass0 = document.getElementById("password");
       username.classList.remove("inputerror");
       pass0.classList.remove("inputerror");
       if(username.value === "") {
           error = error+"<?php echo \MailWatch\Translation::__('erroruserreq12');?><br>";
           username.classList.add("inputerror");
           valid = false;
       }
       if (pass0.value === "") {
           error = error+"<?php echo \MailWatch\Translation::__('errorpwdreq12');?><br>";
           pass0.classList.add("inputerror");
           valid = false;
       }
       if (valid === false) {
           var errorDiv = document.getElementById("formerror");
           var errormsg = errorDiv.innerHTML;
           errorDiv.innerHTML = errormsg + error;
           errorDiv.classList.remove("hidden");
       }
       return valid;
   }


   function validateForm() {
       var errorDiv = document.getElementById("formerror");
       errorDiv.innerHTML = "";
       errorDiv.classList.add("hidden");
       var required = requiredFields();
       var checkpwd = checkPasswords();
       return !(checkpwd === false || required === false);
   }

</script>
<?php
if ($_SESSION['user_type'] === 'A' || $_SESSION['user_type'] === 'D') {
    ?>
    <script type="text/javascript">
        <!--
        function delete_user(id, name) {
            var yesno = confirm("<?php echo ' ' . \MailWatch\Translation::__('areusuredel12') . ' '; ?>" + name + "<?php echo \MailWatch\Translation::__('questionmark12'); ?>");
            if (yesno === true) {
                window.location = "?token=" + "<?php echo $_SESSION['token']; ?>" + "&action=delete&id=" + id;
            } else {
                window.location = "?token=" + "<?php echo $_SESSION['token']; ?>";
            }
        }

        function delete_filter(id, filter) {
            var yesno = confirm("<?php echo \MailWatch\Translation::__('sure12'); ?>");
            if (yesno === true) {
                window.location = "?token=" + "<?php echo $_SESSION['token']; ?>" + "&action=filters&id=" + id + "&filter=" + filter + "&delete=true";
            } else {
                window.location = "?token=" + "<?php echo $_SESSION['token']; ?>" + "&action=filters&id=" + id;
            }
        }

        function change_state(id, filter) {
            var yesno = confirm("<?php echo \MailWatch\Translation::__('sure12'); ?>");
            if (yesno === true) {
                window.location = "?token=" + "<?php echo $_SESSION['token']; ?>" + "&action=filters&id=" + id + "&filter=" + filter + "&change_state=true";
            } else {
                window.location = "?token=" + "<?php echo $_SESSION['token']; ?>" + "&action=filters&id=" + id;
            }
        }

        function logout_user(id, name) {
            var yesno = confirm("<?php echo ' ' . \MailWatch\Translation::__('logout12') . ' '; ?>" + name + "<?php echo \MailWatch\Translation::__('questionmark12'); ?>");
            if (yesno === true) {
                window.location = "?token=" + "<?php echo $_SESSION['token']; ?>" + "&action=logout&id=" + id;
            } else {
                window.location = "?token=" + "<?php echo $_SESSION['token']; ?>";
            }
        }
        -->
    </script>
    <?php
    if (isset($_POST['action'])) {
        $action = \MailWatch\Sanitize::deepSanitizeInput($_POST['action'], 'url');
    } elseif (isset($_GET['action'])) {
        $action = \MailWatch\Sanitize::deepSanitizeInput($_GET['action'], 'url');
    }
    if (isset($action)) {
        if ($action !== 'sendReportNow' && !\MailWatch\Sanitize::validateInput($action, 'action')) {
            die(getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error'));
        }
        switch ($action) {
            case 'new':
                echo newUser();
                break;
            case 'edit':
                echo editUser();
                break;
            case 'delete':
                echo deleteUser();
                break;
            case 'filters':
                echo userFilter();
                break;
            case 'sendReportNow':
                echo sendReport();
                break;
            case 'logout':
                echo logoutUser();
                break;
        }
    }

    echo '<a href="?token=' . $_SESSION['token'] . '&amp;action=new">' . \MailWatch\Translation::__('newuser12') . '</a>'."\n";
    echo '<br><br>'."\n";

    $domainAdminUserDomainFilter = '';
    if ($_SESSION['user_type'] === 'D') {
        if ($_SESSION['domain'] === '') {
            //if the domain admin has no domain set we assume he should see only users that has no domain set (no mail as username)
            $domainAdminUserDomainFilter = 'WHERE username NOT LIKE "%@%" AND type <> "A"';
        } else {
            $sql = "SELECT filter FROM user_filters WHERE username = '" . $_SESSION['myusername'] . "'";
            $result = \MailWatch\Db::query($sql);
            $domainAdminUserDomainFilter = 'WHERE (username LIKE "%@' . $_SESSION['domain'] . '" AND type <> "A")';
            for ($i=0;$i<$result->num_rows;$i++) {
                $filter = $result->fetch_row();
                $domainAdminUserDomainFilter .= ' OR (username LIKE "%@' . $filter[0] . '" AND type = "U")';
            }
        }
    }

    $sql = "
        SELECT
          username AS '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('username12')) . "',
          fullname AS '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('fullname12')) . "',
        CASE
          WHEN type = 'A' THEN '" . \MailWatch\Translation::__('admin12') . "'
          WHEN type = 'D' THEN '" . \MailWatch\Translation::__('domainadmin12') . "'
          WHEN type = 'U' THEN '" . \MailWatch\Translation::__('user12') . "'
          WHEN type = 'R' THEN '" . \MailWatch\Translation::__('userregex12') . "'
        ELSE
          '" . \MailWatch\Translation::__('unknowtype12') . "'
        END AS '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('type12')) . "',
        CASE
          WHEN noscan = 1 THEN '" . \MailWatch\Translation::__('noshort12') . "'
          WHEN noscan = 0 THEN '" . \MailWatch\Translation::__('yesshort12') . "'
        ELSE
          '" . \MailWatch\Translation::__('yesshort12') . "'
        END AS '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('spamcheck12')) . "',
          spamscore AS '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('spamscore12')) . "',
          highspamscore AS '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('spamhscore12')) . "',
        CASE
          WHEN login_expiry > " . time() . " OR login_expiry = 0 THEN '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('yes12')) . "'
        ELSE 
          '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('no12')) . "'
        END AS '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('loggedin12')) . "',
        CASE
WHEN login_expiry > " . time() . " OR login_expiry = 0 THEN CONCAT('<a href=\"?token=" . $_SESSION['token'] . "&amp;action=edit&amp;id=',id,'\">" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('edit12')) . "</a>&nbsp;&nbsp;<a href=\"javascript:delete_user(\'',id,'\',\'',username,'\')\">" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('delete12')) . '</a>&nbsp;&nbsp;<a href="?token=' . $_SESSION['token'] . "&amp;action=filters&amp;id=',id,'\">" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('filters12')) . "</a>&nbsp;&nbsp;<a href=\"javascript:logout_user(\'',id,'\',\'',username,'\')\">" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('logout12')) . "</a>')
        ELSE
          CONCAT('<a href=\"?token=" . $_SESSION['token'] . "&amp;action=edit&amp;id=',id,'\">" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('edit12')) . "</a>&nbsp;&nbsp;<a href=\"javascript:delete_user(\'',id,'\',\'',username,'\')\">" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('delete12')) . '</a>&nbsp;&nbsp;<a href="?token=' . $_SESSION['token'] . "&amp;action=filters&amp;id=',id,'\">" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('filters12')) . "</a>')
        END AS '" .  \MailWatch\Sanitize::safe_value(\MailWatch\Translation::__('action12')) . "'
        FROM
          users " . $domainAdminUserDomainFilter . ' 
        ORDER BY
          username';
    dbtable($sql, \MailWatch\Translation::__('usermgnt12'));
} else {
    if (!isset($_POST['submit'])) {
        $sql = "SELECT id, username, fullname, type, quarantine_report, spamscore, highspamscore, noscan, quarantine_rcpt FROM users WHERE username='" .  \MailWatch\Sanitize::safe_value($_SESSION['myusername']) . "'";
        $result = \MailWatch\Db::query($sql);
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
        echo '<form method="post" action="user_manager.php" onsubmit="return checkPasswords();">' . "\n";
        echo '<INPUT TYPE="HIDDEN" NAME="token" VALUE="' . $_SESSION['token'] . '">' . "\n";
        echo '<input type="hidden" name="action" value="edit">' . "\n";
        echo '<input type="hidden" name="id" value="' . $row->id . '">' . "\n";
        echo '<input type="hidden" name="submit" value="true">' . "\n";
        echo '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . \MailWatch\Security::generateFormToken('/user_manager.php user token') . '">' . "\n";
        echo '<table class="mail useredit" border="0" cellpadding="1" cellspacing="1">' . "\n";
        echo ' <tr><td class="heading" colspan=2 align="center">' . \MailWatch\Translation::__('edituser12') . ' ' . $row->username . '</td></tr>' . "\n";
        echo ' <tr><td class="heading">' . \MailWatch\Translation::__('username0212') . '</td><td>' . $_SESSION['myusername'] . '</td></tr>' . "\n";
        echo ' <tr><td class="heading">' . \MailWatch\Translation::__('name12') . '</td><td>' . $_SESSION['fullname'] . '</td></tr>' . "\n";
        if ($_SESSION['user_ldap'] !== true && $_SESSION['user_imap'] !== true) {
            echo ' <tr><td class="heading">' . \MailWatch\Translation::__('password12') . '</td><td><input type="password" id="password" name="password" value="xxxxxxxx" AUTOCOMPLETE="off"></td></tr>' . "\n";
            echo ' <tr><td class="heading">' . \MailWatch\Translation::__('retypepassword12') . '</td><td><input type="password" id="retypepassword" name="password1" value="xxxxxxxx" AUTOCOMPLETE="off"></td></tr>' . "\n";
        }
        echo ' <tr><td class="heading">' . \MailWatch\Translation::__('quarrep12') . '</td><td><input type="checkbox" name="quarantine_report" value="on" ' . $quarantine_report . '> <span class="font-1em">' . \MailWatch\Translation::__('senddaily12') . '</span> <button type="submit" name="action" value="sendReportNow">' . \MailWatch\Translation::__('sendReportNow12') . '</button></td></tr>' . "\n";
        echo ' <tr><td class="heading">' . \MailWatch\Translation::__('quarreprec12') . '</td><td><input type="text" name="quarantine_rcpt" value="' . $row->quarantine_rcpt . '"><br><span class="font-1em">' . \MailWatch\Translation::__('overrec12') . '</span></td>' . "\n";
        echo ' <tr><td class="heading">' . \MailWatch\Translation::__('scanforspam12') . '</td><td><input type="checkbox" name="noscan" value="on" ' . $noscan . '> <span class="font-1em">' . \MailWatch\Translation::__('scanforspam212') . '</span></td></tr>' . "\n";
        echo ' <tr><td class="heading">' . \MailWatch\Translation::__('pontspam12') . '</td><td><input type="text" name="spamscore" value="' . $row->spamscore . '" size="4"> <span class="font-1em">0=' . \MailWatch\Translation::__('usedefault12') . '</span></td></tr>' . "\n";
        echo ' <tr><td class="heading">' . \MailWatch\Translation::__('hpontspam12') . '</td><td><input type="text" name="highspamscore" value="' . $row->highspamscore . '" size="4"> <span class="font-1em">0=' . \MailWatch\Translation::__('usedefault12') . '</span></td></tr>' . "\n";
        echo '<tr><td class="heading">' . \MailWatch\Translation::__('action_0212') . '</td><td><input type="reset" value="' . \MailWatch\Translation::__('reset12') . '">&nbsp;&nbsp;<input type="submit" name="action" value="' . \MailWatch\Translation::__('update12') . '"></td></tr>' . "\n";
        echo '</table></form><br>' . "\n";
        $sql = "SELECT filter, active FROM user_filters WHERE username='" . $row->username . "'";
        $result = \MailWatch\Db::query($sql);
    } else {
        if (false === \MailWatch\Security::checkToken($_POST['token'])
              || false === \MailWatch\Security::checkFormToken('/user_manager.php user token', $_POST['formtoken'])) {
            die(getHtmlMessage(\MailWatch\Translation::__('dietoken99'), 'error'));
        }
        if (!isset($_POST['action'])) {
            echo getHtmlMessage(\MailWatch\Translation::__('formerror12'), 'error');
        } elseif ($_POST['action'] === 'sendReportNow') {
            include_once __DIR__ . '/quarantine_report.inc.php';
            $requirementsCheck = Quarantine_Report::check_quarantine_report_requirements();
            if ($requirementsCheck !== true) {
                echo getHtmlMessage(\MailWatch\Translation::__('checkReportRequirementsFailed12'), 'error');
                error_log('Requirements for sending quarantine reports not met: ' . $requirementsCheck);
            } elseif (!isset($_POST['quarantine_report'])) {
                echo getHtmlMessage(\MailWatch\Translation::__('noReportsEnabled12'), 'error');
            } else {
                $quarantine_report = new Quarantine_Report();
                $reportResult = $quarantine_report->send_quarantine_reports([$_SESSION['myusername']]);
                if ($reportResult['succ'] === 1) {
                    echo getHtmlMessage(\MailWatch\Translation::__('quarantineReportSend12'), 'error');
                } else {
                    echo getHtmlMessage(\MailWatch\Translation::__('quarantineReportFailed12'), 'error');
                }
            }
        } elseif (isset($_POST['password'], $_POST['password1']) && ($_POST['password'] !== $_POST['password1'])) {
            echo getHtmlMessage(\MailWatch\Translation::__('errorpass12'), 'error');
        } else {
            $username =  \MailWatch\Sanitize::safe_value($_SESSION['myusername']);
            if (isset($_POST['password'])) {
                $n_password =  \MailWatch\Sanitize::safe_value($_POST['password']);
            }
            $spamscore = \MailWatch\Sanitize::deepSanitizeInput($_POST['spamscore'], 'float');
            if (!\MailWatch\Sanitize::validateInput($spamscore, 'float')) {
                $spamscore = '0';
            }
            $highspamscore = \MailWatch\Sanitize::deepSanitizeInput($_POST['highspamscore'], 'float');
            if (!\MailWatch\Sanitize::validateInput($highspamscore, 'float')) {
                $highspamscore = '0';
            }
            $n_quarantine_report = '1';
            if (!isset($_POST['quarantine_report'])) {
                $n_quarantine_report = '0';
            }
            $noscan = '0';
            if (!isset($_POST['noscan'])) {
                $noscan = '1';
            }
            $quarantine_rcpt = \MailWatch\Sanitize::deepSanitizeInput($_POST['quarantine_rcpt'], 'string');
            if ($quarantine_rcpt !== '' && !\MailWatch\Sanitize::validateInput($quarantine_rcpt, 'user')) {
                die(getHtmlMessage(\MailWatch\Translation::__('dievalidate99'), 'error'));
            }

            if (isset($_POST['password']) && $_POST['password'] !== 'XXXXXXXX') {
                // Password reset required
                $password = password_hash($n_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password='" . $password . "', quarantine_report='$n_quarantine_report', spamscore='$spamscore', highspamscore='$highspamscore', noscan='$noscan', quarantine_rcpt='$quarantine_rcpt' WHERE username='$username'";
                \MailWatch\Db::query($sql);
            } else {
                $sql = "UPDATE users SET quarantine_report='$n_quarantine_report', spamscore='$spamscore', highspamscore='$highspamscore', noscan='$noscan', quarantine_rcpt='$quarantine_rcpt' WHERE username='$username'";
                \MailWatch\Db::query($sql);
            }

            // Audit
            \MailWatch\Security::audit_log(sprintf(\MailWatch\Translation::__('auditlog0512', true), $username));
            echo getHtmlMessage(\MailWatch\Translation::__('savedsettings12'), 'success');
        }
    }
}
// Add footer
\MailWatch\Html::end();
// Close any open db connections
\MailWatch\Db::close();
