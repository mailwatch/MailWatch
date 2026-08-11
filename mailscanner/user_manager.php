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

html_start(__('usermgnt12'), 0, false, false);

/**
 * @param string $value
 * @param string $type
 *
 * @return string
 */
function getHtmlMessage($value, $type)
{
    return match ($type) {
        'error' => '<h1 class="center error">' . $value . '</h1>',
        'success' => '<h1 class="center success">' . $value . '</h1>',
        default => $value,
    };
}

/**
 * @param string $username
 * @param string $method
 *
 * @return bool|string
 */
function testSameDomainMembership($username, $method)
{
    $parts = explode('@', $username);
    $sql = "SELECT filter FROM user_filters WHERE username = '" . safe_value(stripslashes((string)$_SESSION['myusername'])) . "'";
    $result = dbquery($sql);
    $filter_domain = [];
    for ($i = 0; $i < $result->num_rows; ++$i) {
        $filter = $result->fetch_row();
        $filter_domain[] = $filter[0];
    }
    if ('D' === $_SESSION['user_type'] && 1 === count($parts) && '' !== $_SESSION['domain']) {
        return getHtmlMessage(__('error' . $method . 'nodomainforbidden12'), 'error');
    }

    if ('D' === $_SESSION['user_type'] && 2 === count($parts) && ($parts[1] !== $_SESSION['domain'] && false === in_array(
        $parts[1],
        $filter_domain,
        true
    ))) {
        return getHtmlMessage(sprintf(__('error' . $method . 'domainforbidden12'), $parts[1]), 'error');
    }

    return true;
}

/**
 * @param string $username
 * @param string $userType
 * @param string $oldUserType
 *
 * @return bool|string
 */
function testPermissions($username, $userType, $oldUserType)
{
    if (('A' !== $_SESSION['user_type'] && 'A' === $oldUserType) || ('D' === $_SESSION['user_type'] && stripslashes((string)$_SESSION['myusername']) !== stripslashes($username) && 'U' !== $userType && (!defined('ENABLE_SUPER_DOMAIN_ADMINS') || ENABLE_SUPER_DOMAIN_ADMINS === false))) {
        return getHtmlMessage(__('erroradminforbidden12'), 'error');
    }

    if ('D' === $_SESSION['user_type'] && 'A' === $userType) {
        return getHtmlMessage(__('errortypesetforbidden12'), 'error');
    }

    return true;
}

/**
 * @param string $username
 * @param string $usertype
 * @param string $oldUsername
 *
 * @return bool|string
 */
function testValidUser($username, $usertype, $oldUsername)
{
    if ('A' !== $usertype && false === validateInput($username, 'email') && (!defined('ALLOW_NO_USER_DOMAIN') || ALLOW_NO_USER_DOMAIN === false)) {
        return getHtmlMessage(__('forallusers12'), 'error');
    }

    if (!isset($_POST['password'], $_POST['password1'])) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    if ('' === $oldUsername && '' === $_POST['password']) {
        return getHtmlMessage(__('errorpwdreq12'), 'error');
    }

    if ($_POST['password'] !== $_POST['password1']) {
        return getHtmlMessage(__('errorpass12'), 'error');
    }

    if ('' === $username) {
        return getHtmlMessage(__('erroruserreq12'), 'error');
    }

    return true;
}

function testToken()
{
    if (!isset($_POST['token']) && !isset($_GET['token'])) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    if ((isset($_POST['token']) && (false === checkToken($_POST['token'])))
        || (isset($_GET['token']) && (false === checkToken($_GET['token'])))) {
        header('Location: login.php?error=pagetimeout');
        exit;
    }

    return true;
}

/**
 * @return int|string
 */
function requestedUserId()
{
    if (isset($_POST['id'])) {
        $uid = (int)$_POST['id'];
    } elseif (isset($_GET['id'])) {
        $uid = (int)$_GET['id'];
    } else {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    $uid = deepSanitizeInput($uid, 'num');
    if (!is_numeric($uid) || (int)$uid < 1) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    return (int)$uid;
}

/**
 * @return \MailWatch\Users\Domain\AccountProfile|string
 */
function submittedAccountProfile($username, $type)
{
    if (!isset($_POST['fullname'], $_POST['spamscore'], $_POST['highspamscore'], $_POST['timeout'], $_POST['quarantine_rcpt'])) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    $fullName = deepSanitizeInput($_POST['fullname'], 'string');
    if (!validateInput($fullName, 'general')) {
        $fullName = '';
    }
    if (!validateInput($type, 'type')) {
        $type = 'U';
    }
    $spamScore = deepSanitizeInput($_POST['spamscore'], 'float');
    if (!validateInput($spamScore, 'float')) {
        $spamScore = '0';
    }
    $highSpamScore = deepSanitizeInput($_POST['highspamscore'], 'float');
    if (!validateInput($highSpamScore, 'float')) {
        $highSpamScore = '0';
    }
    $timeout = deepSanitizeInput($_POST['timeout'], 'num');
    if (!validateInput($timeout, 'timeout')) {
        $timeout = '-1';
    }
    $quarantineRecipient = deepSanitizeInput($_POST['quarantine_rcpt'], 'string');
    if (!validateInput($quarantineRecipient, 'user')) {
        $quarantineRecipient = '';
    }

    return new \MailWatch\Users\Domain\AccountProfile(
        stripslashes((string)$username),
        (string)$fullName,
        (string)$type,
        isset($_POST['quarantine_report']),
        (float)$spamScore,
        (float)$highSpamScore,
        isset($_POST['noscan']),
        stripslashes((string)$quarantineRecipient),
        (int)$timeout,
    );
}

/**
 * @return string
 */
function accountAdministrationError(\Throwable $exception, $targetId = 0)
{
    if ($exception instanceof \MailWatch\Users\Application\UnknownLocalAccount) {
        audit_log(sprintf(__('auditlogunknownuser12'), $_SESSION['myusername'], $targetId));

        return getHtmlMessage(__('accessunknownuser12'), 'error');
    }
    if ($exception instanceof \MailWatch\Users\Application\AccountDomainAccessDenied) {
        $suffix = null === $exception->domain ? 'nodomainforbidden12' : 'domainforbidden12';
        $message = __('error' . $exception->operation . $suffix);
        if (null !== $exception->domain) {
            $message = sprintf($message, $exception->domain);
        }

        return getHtmlMessage($message, 'error');
    }
    if ($exception instanceof \MailWatch\Users\Application\AccountRoleAssignmentDenied) {
        return getHtmlMessage(__('errortypesetforbidden12'), 'error');
    }
    if ($exception instanceof \MailWatch\Users\Application\OwnAccountDeletionDenied) {
        return getHtmlMessage(__('errordeleteself12'), 'error');
    }
    if ($exception instanceof \MailWatch\Users\Application\DuplicateLocalAccount) {
        return getHtmlMessage(sprintf(__('userexists12'), sanitizeInput($exception->username)), 'error');
    }

    return getHtmlMessage(__('erroradminforbidden12'), 'error');
}

/**
 * @return array<string, string>
 */
function accountTypeLabels()
{
    return [
        'A' => __('admin12', true),
        'D' => __('domainadmin12', true),
        'U' => __('user12', true),
        'R' => __('user12', true),
        'H' => __('user12', true),
    ];
}

function getUserById($additionalFields = false)
{
    if (isset($_POST['id'])) {
        $uid = (int)$_POST['id'];
    } elseif (isset($_GET['id'])) {
        $uid = (int)$_GET['id'];
    } else {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }
    if (($uid = deepSanitizeInput($uid, 'num')) < -1) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }
    $sql = 'SELECT id, username, type' . ($additionalFields ? ', fullname, quarantine_report, quarantine_rcpt, spamscore, highspamscore, noscan, login_timeout, last_login' : '') . " FROM users WHERE id='" . $uid . "'";
    $result = dbquery($sql);
    if (0 === $result->num_rows) {
        audit_log(sprintf(__('auditlogunknownuser12'), $_SESSION['myusername'], $uid));

        return getHtmlMessage(__('accessunknownuser12'), 'error');
    }

    return $result->fetch_object();
}

/**
 * @param string           $loggedinUserType  Type of logged in User accessing (A, D, U, or R)
 * @param string           $action            'edit' or 'new'
 * @param string|int       $uid
 * @param string           $lastlogin
 * @param string           $username
 * @param string           $fullname
 * @param array            $type              array which has 'selected' as value for selected type
 * @param string|float|int $timeout
 * @param string           $quarantine_report 'checked' if box shall be ticked
 * @param string           $quarantine_rcpt
 * @param string           $noscan            checkbox default to 'checked'
 * @param string|float|int $spamscore         default 0
 * @param string|float|int $highspamscore     default 0
 *
 * @return string
 */
function printUserFormular(
    $loggedinUserType,
    $action,
    $uid = '',
    $lastlogin = '',
    $username = '',
    $fullname = '',
    $type = ['A' => '', 'D' => '', 'U' => 'selected', 'R' => ''],
    $timeout = '',
    $quarantine_report = '',
    $quarantine_rcpt = '',
    $noscan = 'checked',
    $spamscore = '0',
    $highspamscore = '0'
) {
    $escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $username = $escape($username);
    $fullname = $escape($fullname);
    $quarantine_rcpt = $escape($quarantine_rcpt);
    $timeout = $escape($timeout);
    $spamscore = $escape($spamscore);
    $highspamscore = $escape($highspamscore);
    $returnString = '<div id="formerror" class="hidden"></div>';
    $returnString .= '<FORM METHOD="POST" ACTION="user_manager.php" ONSUBMIT="return validateForm();" AUTOCOMPLETE="off">' . PHP_EOL;
    $returnString .= '<INPUT TYPE="HIDDEN" NAME="token" VALUE="' . $escape($_SESSION['token']) . '">' . PHP_EOL;
    if ('edit' === $action) {
        $returnString .= '<INPUT TYPE="HIDDEN" NAME="id" VALUE="' . $escape($uid) . '">' . PHP_EOL;
        $formheader = __('edituser12') . ' ' . $username;
    } else {
        $formheader = __('newuser12');
    }
    $returnString .= '<INPUT TYPE="HIDDEN" ID="account-action" NAME="action" VALUE="' . $escape($action) . '">' . PHP_EOL;
    $returnString .= '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . $escape(generateFormToken('/user_manager.php ' . $action . ' token')) . '">' . PHP_EOL;
    $returnString .= '<TABLE CLASS="mail" BORDER="0" CELLPADDING="1" CELLSPACING="1">' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading" COLSPAN="2" ALIGN="CENTER">' . $formheader . '</TD></TR>' . PHP_EOL;
    if (!defined('ALLOW_NO_USER_DOMAIN') || !ALLOW_NO_USER_DOMAIN) {
        $returnString .= ' <TR><TD CLASS="message" COLSPAN="2" ALIGN="CENTER">' . __('forallusers12') . '</TD></TR>' . PHP_EOL;
    }
    if ('edit' === $action) {
        $returnString .= ' <TR><TD CLASS="heading">' . __('lastlogin12') . '</TD><TD>' . $lastlogin . '</TD></TR>' . PHP_EOL;
    }
    $returnString .= ' <TR><TD CLASS="heading">' . __('username0212') . '</TD><TD><INPUT TYPE="TEXT" ID="username" NAME="username" VALUE="' . $username . '"></TD></TR>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('name12') . '</TD><TD><INPUT TYPE="TEXT" NAME="fullname" VALUE="' . $fullname . '"></TD></TR>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('password12') . '</TD><TD><INPUT TYPE="PASSWORD" ID="password" NAME="password" VALUE="" AUTOCOMPLETE="new-password"></TD></TR>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('retypepassword12') . '</TD><TD><INPUT TYPE="PASSWORD" ID="retypepassword" NAME="password1" VALUE="" AUTOCOMPLETE="new-password"></TD></TR>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('usertype12') . '</TD>
<TD>
<SELECT NAME="type">' .
        ('A' === $loggedinUserType ? '<OPTION ' . $type['A'] . ' VALUE="A">' . __('admin12') . '</OPTION>' : '') .
        '<OPTION ' . $type['D'] . ' VALUE="D">' . __('domainadmin12') . '</OPTION>
<OPTION ' . $type['U'] . ' VALUE="U">' . __('user12') . '</OPTION>
' . ('edit' === $action ? '<OPTION ' . $type['R'] . ' VALUE="R">' . __('userregex12') . '</OPTION>' : '') . '
</SELECT>
</TD></TR>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('usertimeout12') . '</TD><TD><INPUT TYPE="TEXT" NAME="timeout" VALUE="' . $timeout . '" size="5"> <span class="font-1em">' . __('empty12') . '=' . __('usedefault12') . '</span></TD></TR>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('quarrep12') . '</TD><TD><INPUT TYPE="CHECKBOX" NAME="quarantine_report" ' . $quarantine_report . '> <span class="font-1em">' . __('senddaily12') . '</span>
' . ('edit' === $action ? '<button type="submit" name="action" value="sendReportNow">' . __('sendReportNow12') . '</button>' : '') . '
 </td></tr>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('quarreprec12') . '</TD><TD><INPUT TYPE="TEXT" NAME="quarantine_rcpt" VALUE="' . $quarantine_rcpt . '"><br><span class="font-1em">' . __('overrec12') . '</span></TD>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('scanforspam12') . '</TD><TD><INPUT TYPE="CHECKBOX" NAME="noscan" ' . $noscan . '> <span class="font-1em">' . __('scanforspam212') . '</span></TD></TR>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('pontspam12') . '</TD><TD><INPUT TYPE="TEXT" NAME="spamscore" VALUE="' . $spamscore . '" size="4"> <span class="font-1em">0=' . __('usedefault12') . '</span></TD></TR>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('hpontspam12') . '</TD><TD><INPUT TYPE="TEXT" NAME="highspamscore" VALUE="' . $highspamscore . '" size="4"> <span class="font-1em">0=' . __('usedefault12') . '</span></TD></TR>' . PHP_EOL;
    $returnString .= ' <TR><TD CLASS="heading">' . __('action_0212') . '</TD><TD><INPUT TYPE="RESET" VALUE="' . __('reset12') . '">&nbsp;&nbsp;<button type="submit" name="submit">' . ('edit' === $action ? __('update12') : __('create12')) . '</button></TD></TR>' . PHP_EOL;
    $returnString .= '</TABLE></FORM><BR>' . PHP_EOL;

    return $returnString;
}

/**
 * @param string $userType
 *
 * @return string
 */
function newUser($userType)
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    }

    if (!isset($_POST['submit'])) {
        return printUserFormular($userType, 'new');
    }

    if (!isset($_POST['formtoken'], $_POST['username'], $_POST['type'])) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    if (false === checkFormToken('/user_manager.php new token', $_POST['formtoken'])) {
        header('Location: login.php?error=pagetimeout');
        exit;
    }

    $username = deepSanitizeInput(html_entity_decode($_POST['username']), 'string');
    $n_type = deepSanitizeInput($_POST['type'], 'url');
    if (false === $username || !validateInput($username, 'user')) {
        $username = '';
    }
    if (false === $n_type) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    if (is_string($validuser = testValidUser($username, $n_type, ''))) {
        return $validuser;
    }

    $profile = submittedAccountProfile($username, $n_type);
    if (is_string($profile)) {
        return $profile;
    }

    try {
        \MailWatch\ApplicationFactory::localAccountAdministration()->create(
            stripslashes((string)$_SESSION['myusername']),
            (string)$_SESSION['user_type'],
            (string)($_SESSION['domain'] ?? ''),
            defined('ENABLE_SUPER_DOMAIN_ADMINS') && true === ENABLE_SUPER_DOMAIN_ADMINS,
            $profile,
            (string)$_POST['password'],
        );
    } catch (\MailWatch\Users\Application\AccountAdministrationException $exception) {
        return accountAdministrationError($exception);
    }

    $types = accountTypeLabels();
    audit_log(
        __('auditlog0112', true) . ' ' . $types[$profile->role] . " '" . $profile->username . "' ("
        . $profile->fullName . ') ' . __('auditlog0212', true)
    );

    return getHtmlMessage(sprintf(__('usercreated12'), $profile->username), 'success');
}

/**
 * @param string $userType
 *
 * @return string
 */
function editUser($userType)
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    }

    $uid = requestedUserId();
    if (is_string($uid)) {
        return $uid;
    }

    $administration = \MailWatch\ApplicationFactory::localAccountAdministration();
    $actorUsername = stripslashes((string)$_SESSION['myusername']);
    $actorRole = (string)$_SESSION['user_type'];
    $actorDomain = (string)($_SESSION['domain'] ?? '');
    $superDomainAdministrators = defined('ENABLE_SUPER_DOMAIN_ADMINS')
        && true === ENABLE_SUPER_DOMAIN_ADMINS;

    try {
        $user = $administration->account(
            $actorUsername,
            $actorRole,
            $actorDomain,
            $superDomainAdministrators,
            $uid,
        );
    } catch (\MailWatch\Users\Application\UnknownLocalAccount $exception) {
        return accountAdministrationError($exception, $uid);
    } catch (\MailWatch\Users\Application\AccountAdministrationException $exception) {
        return accountAdministrationError($exception, $uid);
    }

    if (!isset($_POST['submit'])) {
        $quarantine_report = $user->quarantineReport ? 'checked="checked"' : '';
        $noscan = $user->scanForSpam ? 'checked="checked"' : '';
        $timeout = -1 === $user->loginTimeout ? '' : $user->loginTimeout;

        $types = [];
        if ('A' === $userType) {
            $types['A'] = '';
        }
        $types['D'] = '';
        $types['U'] = '';
        $types['R'] = '';

        $timestamp = $user->lastLogin;
        $lastlogin = __('never12');
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
        $types[$user->role] = 'SELECTED';

        return printUserFormular(
            $userType,
            'edit',
            $user->id,
            $lastlogin,
            $user->username,
            $user->fullName,
            $types,
            $timeout,
            $quarantine_report,
            $user->quarantineRecipient,
            $noscan,
            $user->spamScore,
            $user->highSpamScore
        );
    }

    if (!isset($_POST['formtoken'], $_POST['username'], $_POST['type'])) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    if (false === checkFormToken('/user_manager.php edit token', $_POST['formtoken'])) {
        header('Location: login.php?error=pagetimeout');
        exit;
    }

    // Do update
    $username = html_entity_decode(deepSanitizeInput($_POST['username'], 'string'));
    if (!validateInput($username, 'user')) {
        $username = '';
    }
    $n_type = deepSanitizeInput($_POST['type'], 'url');
    if (false === $n_type) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    if (is_string($validusertest = testValidUser($username, $n_type, $user->username))) {
        return $validusertest;
    }

    $profile = submittedAccountProfile($username, $n_type);
    if (is_string($profile)) {
        return $profile;
    }

    try {
        $previous = $administration->update(
            $actorUsername,
            $actorRole,
            $actorDomain,
            $superDomainAdministrators,
            $uid,
            $profile,
            isset($_POST['password']) ? (string)$_POST['password'] : null,
        );
    } catch (\MailWatch\Users\Application\UnknownLocalAccount $exception) {
        return accountAdministrationError($exception, $uid);
    } catch (\MailWatch\Users\Application\AccountAdministrationException $exception) {
        return accountAdministrationError($exception, $uid);
    }

    if ($previous->role !== $profile->role) {
        $types = accountTypeLabels();
        audit_log(
            __('auditlog0312', true) . " '" . $profile->username . "' (" . $profile->fullName . ') '
            . __('auditlogfrom12', true) . ' ' . $types[$previous->role] . ' '
            . __('auditlogto12', true) . ' ' . $types[$profile->role]
        );
    }

    return getHtmlMessage(sprintf(__('useredited12'), $previous->username), 'success');
}

/**
 * @return string
 */
function deleteUser()
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    }

    if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')
        || false === checkFormToken('/user_manager.php delete token', $_POST['formtoken'] ?? '')) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    $uid = requestedUserId();
    if (is_string($uid)) {
        return $uid;
    }

    try {
        $user = \MailWatch\ApplicationFactory::localAccountAdministration()->delete(
            stripslashes((string)$_SESSION['myusername']),
            (string)$_SESSION['user_type'],
            (string)($_SESSION['domain'] ?? ''),
            defined('ENABLE_SUPER_DOMAIN_ADMINS') && true === ENABLE_SUPER_DOMAIN_ADMINS,
            $uid,
        );
    } catch (\MailWatch\Users\Application\UnknownLocalAccount $exception) {
        return accountAdministrationError($exception, $uid);
    } catch (\MailWatch\Users\Application\AccountAdministrationException $exception) {
        return accountAdministrationError($exception, $uid);
    }

    audit_log(sprintf(__('auditlog0412', true), $user->username));

    return getHtmlMessage(sprintf(__('userdeleted12'), $user->username), 'success');
}

/**
 * @return string
 */
function userFilter()
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    }

    $targetId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($targetId < 1) {
        return getHtmlMessage(__('dievalidate99'), 'error');
    }

    $administration = \MailWatch\ApplicationFactory::savedFilterAdministration();
    $actorUsername = stripslashes((string)$_SESSION['myusername']);
    $actorRole = (string)$_SESSION['user_type'];
    $actorDomain = (string)($_SESSION['domain'] ?? '');
    $superDomainAdministrators = defined('ENABLE_SUPER_DOMAIN_ADMINS')
        && true === ENABLE_SUPER_DOMAIN_ADMINS;

    $operation = (string)($_POST['filter_operation'] ?? '');
    if ('' !== $operation) {
        if (false === checkFormToken('/user_manager.php filter token', $_POST['formtoken'] ?? '')) {
            header('Location: login.php?error=pagetimeout');
            exit;
        }

        $filter = deepSanitizeInput($_POST['filter'] ?? '', 'url');
        if (!validateInput($filter, 'email') && !validateInput($filter, 'host')) {
            return getHtmlMessage(__('dievalidate99'), 'error');
        }

        try {
            if ('add' === $operation) {
                $active = deepSanitizeInput($_POST['active'] ?? '', 'url');
                if (!validateInput($active, 'yn')) {
                    return getHtmlMessage(__('dievalidate99'), 'error');
                }
                $administration->add(
                    $actorUsername,
                    $actorRole,
                    $actorDomain,
                    $targetId,
                    $superDomainAdministrators,
                    new \MailWatch\Users\Domain\SavedFilter(stripslashes($filter), 'Y' === $active),
                );
            } elseif ('delete' === $operation) {
                $administration->delete(
                    $actorUsername,
                    $actorRole,
                    $actorDomain,
                    $targetId,
                    $superDomainAdministrators,
                    stripslashes($filter),
                );
            } elseif ('toggle' === $operation) {
                $administration->toggle(
                    $actorUsername,
                    $actorRole,
                    $actorDomain,
                    $targetId,
                    $superDomainAdministrators,
                    stripslashes($filter),
                );
            } else {
                return getHtmlMessage(__('dievalidate99'), 'error');
            }
        } catch (\MailWatch\Users\Application\UnknownLocalAccount) {
            audit_log(sprintf(__('auditlogunknownuser12'), $actorUsername, $targetId));

            return getHtmlMessage(__('accessunknownuser12'), 'error');
        } catch (\MailWatch\Users\Application\SavedFilterAccessDenied) {
            return getHtmlMessage(__('erroradminforbidden12'), 'error');
        }
    }

    try {
        $overview = $administration->overview(
            $actorUsername,
            $actorRole,
            $actorDomain,
            $targetId,
            $superDomainAdministrators,
        );
    } catch (\MailWatch\Users\Application\UnknownLocalAccount) {
        audit_log(sprintf(__('auditlogunknownuser12'), $actorUsername, $targetId));

        return getHtmlMessage(__('accessunknownuser12'), 'error');
    } catch (\MailWatch\Users\Application\SavedFilterAccessDenied) {
        return getHtmlMessage(__('erroradminforbidden12'), 'error');
    }

    $token = htmlspecialchars((string)$_SESSION['token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $formToken = htmlspecialchars(generateFormToken('/user_manager.php filter token'), ENT_QUOTES, 'UTF-8');
    $confirmation = htmlspecialchars(
        'return confirm(' . json_encode(__('sure12'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ');',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8',
    );
    $hiddenFields = static fn(int $id, string $filter = ''): string => '<INPUT TYPE="HIDDEN" NAME="action" VALUE="filters">'
        . '<INPUT TYPE="HIDDEN" NAME="token" VALUE="' . $token . '">'
        . '<INPUT TYPE="HIDDEN" NAME="id" VALUE="' . $id . '">'
        . ('' === $filter ? '' : '<INPUT TYPE="HIDDEN" NAME="filter" VALUE="' . $filter . '">')
        . '<INPUT TYPE="HIDDEN" NAME="formtoken" VALUE="' . $formToken . '">';

    $returnString = '<TABLE CLASS="mail" BORDER="0" CELLPADDING="1" CELLSPACING="1">' . PHP_EOL;
    $returnString .= ' <TR><TH COLSPAN=3>' . __('userfilter12') . ' '
        . htmlspecialchars($overview->account->username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</TH></TR>' . PHP_EOL;
    $returnString .= ' <TR><TH>' . __('filter12') . '</TH><TH>' . __('active12') . '</TH><TH>' . __('action12') . '</TH></TR>' . PHP_EOL;
    foreach ($overview->filters as $savedFilter) {
        $filter = htmlspecialchars($savedFilter->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $returnString .= ' <TR><TD>' . $filter . '</TD><TD>'
            . ($savedFilter->active ? __('yes12') : __('no12')) . '</TD><TD>';
        if (!$overview->canMutate) {
            $returnString .= __('nofilteraction12');
        } else {
            $returnString .= '<FORM METHOD="POST" ACTION="user_manager.php">'
                . $hiddenFields($overview->account->id, $filter)
                . '<BUTTON TYPE="SUBMIT" NAME="filter_operation" VALUE="delete" ONCLICK="' . $confirmation . '">' . __('delete12') . '</BUTTON>&nbsp;&nbsp;'
                . '<BUTTON TYPE="SUBMIT" NAME="filter_operation" VALUE="toggle" ONCLICK="' . $confirmation . '">' . __('toggle12') . '</BUTTON>'
                . '</FORM>';
        }
        $returnString .= '</TD></TR>' . PHP_EOL;
    }
    $returnString .= '</TABLE><BR>' . PHP_EOL;

    if ($overview->canMutate) {
        $returnString .= '<FORM METHOD="POST" ACTION="user_manager.php">' . PHP_EOL;
        $returnString .= $hiddenFields($overview->account->id) . PHP_EOL;
        $returnString .= '<TABLE CLASS="mail" BORDER="0" CELLPADDING="1" CELLSPACING="1">' . PHP_EOL;
        $returnString .= ' <TR><TD><INPUT TYPE="text" NAME="filter"></TD><TD><SELECT NAME="active"><OPTION VALUE="Y">' . __('yes12') . '<OPTION VALUE="N">' . __('no12') . '</SELECT></TD><TD><BUTTON TYPE="submit" NAME="filter_operation" VALUE="add">' . __('add12') . '</BUTTON></TD></TR>' . PHP_EOL;
        $returnString .= '</TABLE><BR>' . PHP_EOL;
        $returnString .= '</FORM>' . PHP_EOL;
    }

    return $returnString;
}

function sendReport()
{
    include_once __DIR__ . '/quarantine_report.inc.php';
    $requirementsCheck = Quarantine_Report::check_quarantine_report_requirements();
    if (true !== $requirementsCheck) {
        error_log('Requirements for sending quarantine reports not met: ' . $requirementsCheck);

        return getHtmlMessage(__('checkReportRequirementsFailed12'), 'error');
    }

    if (is_string($user = getUserById())) {
        return $user;
    }

    if (is_string($membertest = testSameDomainMembership($user->username, 'report'))) {
        return $membertest;
    }

    $quarantine_report = new Quarantine_Report();
    $reportResult = $quarantine_report->send_quarantine_reports([$user->username], true);
    if (-2 === $reportResult) {
        return getHtmlMessage(__('noReportsEnabled12'), 'error');
    }

    if ($reportResult['succ'] > 0) {
        return getHtmlMessage(__('quarantineReportSend12'), 'success');
    }

    return getHtmlMessage(__('quarantineReportFailed12'), 'error');
}

function logoutUser()
{
    if (is_string($tokentest = testToken())) {
        return $tokentest;
    }

    if (is_string($user = getUserById())) {
        return $user;
    }

    if (is_string($membertest = testSameDomainMembership($user->username, 'logout'))) {
        return $membertest;
    }

    if (is_string($permissiontest = testPermissions($user->username, $user->type, ''))) {
        return $permissiontest;
    }

    $sql = "UPDATE users SET login_expiry='-1' WHERE id='$user->id'";
    dbquery($sql);
    if (DEBUG === true) {
        echo $sql;
    }

    return getHtmlMessage(sprintf(__('userloggedout12'), stripslashes((string)$user->username)), 'success');
}

?>
    <script>
        function checkPasswords() {
            var pass0 = document.getElementById("password");
            var pass1 = document.getElementById("retypepassword");
            if (pass0 === null || pass1 === null) {
                return true;
            }
            pass0.classList.remove("inputerror");
            pass1.classList.remove("inputerror");
            if (pass0.value !== pass1.value) {
                var errorDiv = document.getElementById("formerror");
                var errormsg = errorDiv.innerHTML;
                errorDiv.innerHTML = errormsg + "<?php echo __('errorpass12'); ?><br>";
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
            var action = document.getElementById("account-action");
            username.classList.remove("inputerror");
            pass0.classList.remove("inputerror");
            if (username.value === "") {
                error = error + "<?php echo __('erroruserreq12'); ?><br>";
                username.classList.add("inputerror");
                valid = false;
            }
            if (action !== null && action.value === "new" && pass0.value === "") {
                error = error + "<?php echo __('errorpwdreq12'); ?><br>";
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
if ('A' === $_SESSION['user_type'] || 'D' === $_SESSION['user_type']) {
    ?>
    <script type="text/javascript">
        <!--
        function delete_user(id, name) {
            var yesno = confirm("<?php echo ' ' . __('areusuredel12') . ' '; ?>" + name + "<?php echo __('questionmark12'); ?>");
            if (yesno === true) {
                var form = document.createElement("form");
                form.method = "post";
                form.action = "user_manager.php";
                var values = {
                    token: <?php echo json_encode((string)$_SESSION['token']); ?>,
                    formtoken: <?php echo json_encode(generateFormToken('/user_manager.php delete token')); ?>,
                    action: "delete",
                    id: id
                };
                Object.keys(values).forEach(function (key) {
                    var input = document.createElement("input");
                    input.type = "hidden";
                    input.name = key;
                    input.value = values[key];
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            }
        }

        function logout_user(id, name) {
            var yesno = confirm("<?php echo ' ' . __('logout12') . ' '; ?>" + name + "<?php echo __('questionmark12'); ?>");
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
        $action = deepSanitizeInput($_POST['action'], 'url');
    } elseif (isset($_GET['action'])) {
        $action = deepSanitizeInput($_GET['action'], 'url');
    }
    if (isset($action)) {
        if ('sendReportNow' !== $action && !validateInput($action, 'action')) {
            exit(getHtmlMessage(__('dievalidate99'), 'error'));
        }
        switch ($action) {
            case 'new':
                echo newUser($_SESSION['user_type']);
                break;
            case 'edit':
                echo editUser($_SESSION['user_type']);
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

    echo '<a href="?token=' . $_SESSION['token'] . '&amp;action=new">' . __('newuser12') . '</a>' . PHP_EOL;
    echo '<br><br>' . PHP_EOL;

    $domainAdminUserDomainFilter = '';
    if ('D' === $_SESSION['user_type']) {
        if ('' === $_SESSION['domain']) {
            // if the domain admin has no domain set we assume he should see only users that has no domain set (no mail as username)
            $domainAdminUserDomainFilter = 'WHERE username NOT LIKE "%@%" AND type <> "A"';
        } else {
            $sql = "SELECT filter FROM user_filters WHERE username = '" . safe_value(stripslashes((string)$_SESSION['myusername'])) . "'";
            $result = dbquery($sql);
            $domainAdminUserDomainFilter = 'WHERE (username LIKE "%@' . $_SESSION['domain'] . '" AND type <> "A")';
            for ($i = 0; $i < $result->num_rows; ++$i) {
                $filter = $result->fetch_row();
                $domainAdminUserDomainFilter .= ' OR (username LIKE "%@' . safe_value(stripslashes((string)$filter[0])) . '" AND type = "U")';
            }
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
        CASE
          WHEN login_expiry > " . time() . " OR login_expiry = 0 THEN '" . safe_value(__('yes12')) . "'
        ELSE 
          '" . safe_value(__('no12')) . "'
        END AS '" . safe_value(__('loggedin12')) . "',
        CASE
WHEN login_expiry > " . time() . " OR login_expiry = 0 THEN CONCAT('<a href=\"?token=" . $_SESSION['token'] . "&amp;action=edit&amp;id=',id,'\">" . safe_value(__('edit12')) . "</a>&nbsp;&nbsp;<a href=\"javascript:delete_user(\'',id,'\',',QUOTE(username),')\">" . safe_value(__('delete12')) . '</a>&nbsp;&nbsp;<a href="?token=' . $_SESSION['token'] . "&amp;action=filters&amp;id=',id,'\">" . safe_value(__('filters12')) . "</a>&nbsp;&nbsp;<a href=\"javascript:logout_user(\'',id,'\',',QUOTE(username),')\">" . safe_value(__('logout12')) . "</a>')
        ELSE
          CONCAT('<a href=\"?token=" . $_SESSION['token'] . "&amp;action=edit&amp;id=',id,'\">" . safe_value(__('edit12')) . "</a>&nbsp;&nbsp;<a href=\"javascript:delete_user(\'',id,'\',',QUOTE(username),')\">" . safe_value(__('delete12')) . '</a>&nbsp;&nbsp;<a href="?token=' . $_SESSION['token'] . "&amp;action=filters&amp;id=',id,'\">" . safe_value(__('filters12')) . "</a>')
        END AS '" . safe_value(__('action12')) . "'
        FROM
          users " . $domainAdminUserDomainFilter . ' 
        ORDER BY
          username';
    dbtable($sql, __('usermgnt12'));
} elseif (!isset($_POST['submit'])) {
    $authenticationSource = \MailWatch\Users\Domain\AuthenticationSource::fromSession(
        true === ($_SESSION['user_ldap'] ?? false),
        true === ($_SESSION['user_imap'] ?? false),
    );

    try {
        $overview = \MailWatch\ApplicationFactory::ownProfileAdministration()->overview(
            stripslashes((string)$_SESSION['myusername']),
            $authenticationSource,
        );
    } catch (\MailWatch\Users\Application\UnknownLocalAccount) {
        echo getHtmlMessage(__('accessunknownuser12'), 'error');
        $overview = null;
    }

    if (null !== $overview) {
        $profile = $overview->profile;
        $quarantineReport = $profile->quarantineReport ? 'checked="checked"' : '';
        $scanForSpam = $profile->scanForSpam ? 'checked="checked"' : '';
        $token = htmlspecialchars((string)$_SESSION['token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $formToken = htmlspecialchars(generateFormToken('/user_manager.php user token'), ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars($profile->username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fullName = htmlspecialchars($profile->fullName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $quarantineRecipient = htmlspecialchars($profile->quarantineRecipient, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<div id="formerror" class="hidden"></div>';
        echo '<form method="post" action="user_manager.php" onsubmit="return checkPasswords();">' . PHP_EOL;
        echo '<input type="hidden" name="token" value="' . $token . '">' . PHP_EOL;
        echo '<input type="hidden" name="action" value="edit">' . PHP_EOL;
        echo '<input type="hidden" name="id" value="' . $profile->id . '">' . PHP_EOL;
        echo '<input type="hidden" name="submit" value="true">' . PHP_EOL;
        echo '<input type="hidden" name="formtoken" value="' . $formToken . '">' . PHP_EOL;
        echo '<table class="mail useredit" border="0" cellpadding="1" cellspacing="1">' . PHP_EOL;
        echo ' <tr><td class="heading" colspan=2 align="center">' . __('edituser12') . ' ' . $username . '</td></tr>' . PHP_EOL;
        echo ' <tr><td class="heading">' . __('username0212') . '</td><td>' . $username . '</td></tr>' . PHP_EOL;
        echo ' <tr><td class="heading">' . __('name12') . '</td><td>' . $fullName . '</td></tr>' . PHP_EOL;
        if ($overview->authenticationSource->canChangePassword()) {
            echo ' <tr><td class="heading">' . __('password12') . '</td><td><input type="password" id="password" name="password" value="" autocomplete="new-password"></td></tr>' . PHP_EOL;
            echo ' <tr><td class="heading">' . __('retypepassword12') . '</td><td><input type="password" id="retypepassword" name="password1" value="" autocomplete="new-password"></td></tr>' . PHP_EOL;
        }
        echo ' <tr><td class="heading">' . __('quarrep12') . '</td><td><input type="checkbox" name="quarantine_report" value="on" ' . $quarantineReport . '> <span class="font-1em">' . __('senddaily12') . '</span> <button type="submit" name="action" value="sendReportNow">' . __('sendReportNow12') . '</button></td></tr>' . PHP_EOL;
        echo ' <tr><td class="heading">' . __('quarreprec12') . '</td><td><input type="text" name="quarantine_rcpt" value="' . $quarantineRecipient . '"><br><span class="font-1em">' . __('overrec12') . '</span></td>' . PHP_EOL;
        echo ' <tr><td class="heading">' . __('scanforspam12') . '</td><td><input type="checkbox" name="noscan" value="on" ' . $scanForSpam . '> <span class="font-1em">' . __('scanforspam212') . '</span></td></tr>' . PHP_EOL;
        echo ' <tr><td class="heading">' . __('pontspam12') . '</td><td><input type="text" name="spamscore" value="' . $profile->spamScore . '" size="4"> <span class="font-1em">0=' . __('usedefault12') . '</span></td></tr>' . PHP_EOL;
        echo ' <tr><td class="heading">' . __('hpontspam12') . '</td><td><input type="text" name="highspamscore" value="' . $profile->highSpamScore . '" size="4"> <span class="font-1em">0=' . __('usedefault12') . '</span></td></tr>' . PHP_EOL;
        echo '<tr><td class="heading">' . __('action_0212') . '</td><td><input type="reset" value="' . __('reset12') . '">&nbsp;&nbsp;<input type="submit" name="action" value="' . __('update12') . '"></td></tr>' . PHP_EOL;
        echo '</table></form><br>' . PHP_EOL;
    }
} else {
    if (false === checkToken($_POST['token'])
        || false === checkFormToken('/user_manager.php user token', $_POST['formtoken'])) {
        header('Location: login.php?error=pagetimeout');
        exit;
    }
    if (!isset($_POST['action'])) {
        echo getHtmlMessage(__('formerror12'), 'error');
    } elseif ('sendReportNow' === $_POST['action']) {
        include_once __DIR__ . '/quarantine_report.inc.php';
        $requirementsCheck = Quarantine_Report::check_quarantine_report_requirements();
        if (true !== $requirementsCheck) {
            echo getHtmlMessage(__('checkReportRequirementsFailed12'), 'error');
            error_log('Requirements for sending quarantine reports not met: ' . $requirementsCheck);
        } elseif (!isset($_POST['quarantine_report']) || 'on' !== $_POST['quarantine_report']) {
            echo getHtmlMessage(__('noReportsEnabled12'), 'error');
        } else {
            $quarantine_report = new Quarantine_Report();
            $reportResult = $quarantine_report->send_quarantine_reports([$_SESSION['myusername']]);
            if (1 === $reportResult['succ']) {
                echo getHtmlMessage(__('quarantineReportSend12'), 'error');
            } else {
                echo getHtmlMessage(__('quarantineReportFailed12'), 'error');
            }
        }
    } elseif (isset($_POST['password'], $_POST['password1']) && ($_POST['password'] !== $_POST['password1'])) {
        echo getHtmlMessage(__('errorpass12'), 'error');
    } else {
        $username = stripslashes((string)$_SESSION['myusername']);
        $spamscore = deepSanitizeInput($_POST['spamscore'], 'float');
        if (!validateInput($spamscore, 'float')) {
            $spamscore = '0';
        }
        $highspamscore = deepSanitizeInput($_POST['highspamscore'], 'float');
        if (!validateInput($highspamscore, 'float')) {
            $highspamscore = '0';
        }
        $quarantine_rcpt = deepSanitizeInput($_POST['quarantine_rcpt'], 'string');
        if ('' !== $quarantine_rcpt && !validateInput($quarantine_rcpt, 'user')) {
            exit(getHtmlMessage(__('dievalidate99'), 'error'));
        }

        $authenticationSource = \MailWatch\Users\Domain\AuthenticationSource::fromSession(
            true === ($_SESSION['user_ldap'] ?? false),
            true === ($_SESSION['user_imap'] ?? false),
        );

        try {
            \MailWatch\ApplicationFactory::ownProfileAdministration()->update(
                $username,
                $authenticationSource,
                new \MailWatch\Users\Domain\ProfilePreferences(
                    isset($_POST['quarantine_report']),
                    (float)$spamscore,
                    (float)$highspamscore,
                    isset($_POST['noscan']),
                    stripslashes($quarantine_rcpt),
                ),
                isset($_POST['password']) ? (string)$_POST['password'] : null,
            );

            // Audit
            audit_log(sprintf(__('auditlog0512', true), $username));
            echo getHtmlMessage(__('savedsettings12'), 'success');
        } catch (\MailWatch\Users\Application\UnknownLocalAccount) {
            echo getHtmlMessage(__('accessunknownuser12'), 'error');
        } catch (\MailWatch\Users\Application\ProfilePasswordChangeDenied) {
            echo getHtmlMessage(__('erroradminforbidden12'), 'error');
        }
    }
}
// Add footer
html_end();
// Close any open db connections
dbclose();
