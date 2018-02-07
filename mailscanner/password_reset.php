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

//Check if LDAP is enabled, if so, prevent usage
if (USE_LDAP === true) {
    die(__('pwdresetldap63'));
}

if (PHP_SAPI !== 'cli' && SSL_ONLY && (!empty($_SERVER['PHP_SELF']))) {
    if (!$_SERVER['HTTPS'] === 'on') {
        header('Location: https://' . sanitizeInput($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']));
        exit;
    }
}

// Load in the required PEAR modules
require_once MAILWATCH_HOME . '/lib/pear/Mail.php';
require_once MAILWATCH_HOME . '/lib/pear/Mail/smtp.php';
require_once MAILWATCH_HOME . '/lib/pear/Mail/mime.php';
date_default_timezone_set(TIME_ZONE);

$showpage = false;
$fields = '';
$errors = '';
$message = '';
$link = dbconn();
if (defined('PWD_RESET') && PWD_RESET === true) {
    if (isset($_POST['Submit'])) {
        if (false === checkToken($_POST['token'])) {
            die();
        }
        $_SESSION['token'] = generateToken();

        if ($_POST['Submit'] === 'stage1Submit') {
            //check email add registered user and password reset is allowed
            $email = $link->real_escape_string($_POST['email']);
            if (empty($email)) {
                header('Location: password_reset.php?stage=1');
                die();
            }
            if (!validateInput($email, 'email')) {
                die();
            }
            $sql = "SELECT * FROM users WHERE username = '$email'";
            $result = dbquery($sql);
            if ($result->num_rows !== 1) {
                //user not found
                $errors = '<p class="pwdreseterror">' . __('usernotfound63') . '</p>
                    <div class="pwdresetButton"><a href="login.php" class="loginButton">' . __('login01') . '</a></div>';
                audit_log(sprintf(__('auditlogunf63', true), $email));
                $showpage = true;
            } else {
                //user found, now check type of user
                $row = $result->fetch_assoc();
                if ($row['type'] === 'U') {
                    //user type is user, password reset allowed
                    $rand = get_random_string(16);
                    $resetexpire = time() + 60 * 60 * RESET_LINK_EXPIRE;
                    $sql = "UPDATE users SET resetid = '$rand', resetexpire = '$resetexpire' WHERE username = '$email'";
                    $result = dbquery($sql);
                    if (!$result) {
                        die(__('errordbupdate63'));
                    }
                    $html = '<!DOCTYPE html>
    <html>
    <head>
     <title>' . __('title63') . '</title>
     <style type="text/css">
     <!--
      body, td, tr {
      font-family: sans-serif;
      font-size: 8pt;
     }
     -->
     </style>
    </head>
    <body style="margin: 5px;">
    
    <!-- Outer table -->
    <table width="100%" border="0">
     <tr>
      <td><img src="' . MW_LOGO . '" alt="' . __('mwlogo99') . '"/></td>
     </tr>
     <tr>
      <td align="center" valign="middle">
       <h2>' . __('passwdresetrequest63') . '</h2>
       <p>' . sprintf(__('p1email63'), $email) . '</p>
        <a href="' . MAILWATCH_HOSTURL . '/password_reset.php?stage=2&uid=' . $rand . '"><button>' . __('button63') . '</button></a></p>
      </td>
     </tr>
     </table>
    </body>
    </html>';
                    $text = sprintf(__('01emailplaintxt63'), $email) . MAILWATCH_HOSTURL . '/password_reset.php?stage=2&uid=' . $rand;

                    //Send email
                    $subject = __('passwdresetrequest63');
                    $isSent = send_email($email, $html, $text, $subject, true);
                    if ($isSent !== true) {
                        die('Error Sending email: ' . $isSent);
                    }

                    $message = '<p>' . __('01emailsuccess63') . '</p>
                    <div class="pwdresetButton"><a href="login.php" class="loginButton">' . __('login01') . '</a></div>';
                    audit_log(sprintf(__('auditlogreserreqested63', true), $email));
                    $showpage = true;
                } else {
                    //password reset not allowed
                    audit_log(sprintf(__('auditlogresetdenied63', true), $email));
                    $errors = '<p class="pwdreseterror">' . __('resetnotallowed63') . '</p>';
                    $errors .= '<div class="pwdresetButton"><a href="login.php" class="loginButton">' . __('login01') . '</a></div>';
                    $showpage = true;
                }
            }
        } elseif ($_POST['Submit'] === 'stage2Submit') {
            //check passwords match, update password in database, update password last changed date, increase password reset counter, email user to inform of password reset
            $email = $link->real_escape_string($_POST['email']);
            if (!validateInput($email, 'email')) {
                die();
            }
            $uid = $link->real_escape_string($_POST['uid']);
            //var_dump($_POST, $email, $uid, validateInput($uid, 'resetid'));
            if (!validateInput($uid, 'resetid')) {
                die();
            }
            if ($_POST['pwd1'] === $_POST['pwd2']) {
                //passwords match, now we need to store them
                //first, check form hasn't been modified
                $sql = "SELECT resetid FROM users WHERE username = '$email'";
                $result = dbquery($sql);
                $row = $result->fetch_array();
                if ($row['resetid'] === $uid) {
                    require_once MAILWATCH_HOME . '/lib/password.php';
                    $password = $link->real_escape_string(password_hash($_POST['pwd1'], PASSWORD_DEFAULT));
                    $lastreset = time();
                    $sql = "UPDATE users SET password = '$password', resetid = '', resetexpire = '0', lastreset ='$lastreset' WHERE username ='$email'";
                    $result = dbquery($sql);

                    //now send email telling user password has been updated.
                    $html = '<!DOCTYPE html>
    <html>
    <head>
     <title>' . __('pwdresetsuccess63') . '</title>
     <style type="text/css">
     <!--
      body, td, tr {
      font-family: sans-serif;
      font-size: 8pt;
     }
     -->
     </style>
    </head>
    <body style="margin: 5px;">
    
    <!-- Outer table -->
    <table width="100%%" border="0">
     <tr>
      <td><img src="' . MW_LOGO . '" alt="' . __('mwlogo99') . '"/></td>
     </tr>
     <tr> 
      <td align="center" valign="middle">
       <h2>' . __('pwdresetsuccess63') . '</h2>
       <p>' . sprintf(__('03pwdresetemail63'), $email) . '</p>
      </td>
     </tr>
     </table>
    </body>
    </html>';
                    $text = sprintf(__('04pwdresetemail63'), $email);

                    //Send email
                    $subject = __('pwdresetsuccess63');
                    send_email($email, $html, $text, $subject, true);
                    audit_log(sprintf(__('auditlogresetsuccess63', true), $email));
                    $message = '<p>' . __('pwdresetsuccess63') . '</p>
                        <div class="pwdresetButton"><a href="login.php" class="loginButton">' . __('login01') . '</a></div>';
                    $showpage = true;
                } else {
                    audit_log(sprintf(__('auditlogidmismatch63', true), $email));
                    $errors = '<p class="pwdreseterror">' . __('pwdresetidmismatch63') . '</p>
                        <div class="pwdresetButton"><a href="login.php" class="loginButton">' . __('login01') . '</a></div>';
                    $showpage = true;
                }
            } else {
                $errors = '<p class="pwdreseterror">' . __('pwdmismatch63');
                $fields = 'stage2';
                $showpage = true;
            }
        } else {
            header('Location: login.php?error=baduser');
            die();
        }
    } elseif (isset($_GET['stage'])) {
        if (!isset($_SESSION['token'])) {
            $_SESSION['token'] = generateToken();
        }
        if ($_GET['stage'] === '1') {
            //first stage, need to get email address
            $fields = 'stage1';
            $showpage = true;
        } elseif ($_GET['stage'] === '2') {
            //need to check if reset allowed, and reset password
            if (isset($_GET['uid'])) {
                //check that uid is correct
                $uid = $link->real_escape_string($_GET['uid']);

                $sql = "SELECT * FROM users WHERE resetid = '$uid'";
                $result = dbquery($sql);
                if ($result->num_rows !== 1) {
                    audit_log(sprintf(__('auditlogunf63', true), $uid));
                    $errors = '<p class="pwdreseterror">' . __('usernotfound63') . '
                    <div class="pwdresetButton"><a href="login.php" class="loginButton">' . __('login01') . '</a></div>';
                    $showpage = true;
                } else {
                    $row = $result->fetch_array();
                    $email = $row['username'];
                    if ($row['resetid'] === $uid) {
                        //reset id matches - check if link expired
                        if ($row['resetexpire'] < time()) {
                            audit_log(sprintf(__('auditlogexpired63', true), $row['username']));
                            $errors = '<p class="pwdreseterror">' . __('resetexpired63') . '
                    <div class="pwdresetButton"><a href="login.php" class="loginButton">' . __('login01') . '</a></div>';
                            $showpage = true;
                        } else {
                            $fields = 'stage2';
                            $showpage = true;
                        }
                    } else {
                        audit_log(sprintf(__('auditlogidmismatch63', true), $row['username']));
                        $errors = '<p class="pwdreseterror">' . __('pwdresetidmismatch63') . '
                    <div class="pwdresetButton"><a href="login.php" class="loginButton">' . __('login01') . '</a></div>';
                        $showpage = true;
                    }
                }
            } else {
                //no matches - deny
                audit_log(__('auditloglinkerror63', true));
                $errors = __('brokenlink63') . '<div class="pwdresetButton"><a href="login.php" class="loginButton">' . __('login01') . '</a></div>';
                $showpage = true;
            }
        } else {
            header('Location: login.php?error=baduser');
            die();
        }
    } else {
        header('Location: login.php?error=baduser');
        die();
    }

    if ($showpage) {
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <title><?php echo __('title63'); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="shortcut icon" href="images/favicon.png">
            <link rel="stylesheet" href="style.css" type="text/css">
            <?php if (is_file(__DIR__ . '/skin.css')) {
            echo '<link rel="stylesheet" href="skin.css" type="text/css">';
        } ?>
        </head>
        <body class="pwdreset">
        <div class="pwdreset">
            <div class="mw-logo">
                <img src="<?php echo '.' . IMAGES_DIR . MW_LOGO; ?>" alt="<?php echo __('mwlogo99'); ?>">
            </div>
            <div class="border-rounded">
                <h1><?php echo __('title63'); ?></h1>
                <?php if (file_exists('conf.php')) {
            if ($fields !== '') {
                ?>
                        <form name="pwdresetform" class="pwdresetform" method="post" action="<?php echo sanitizeInput($_SERVER['PHP_SELF']); ?>" autocomplete="off">
                            <fieldset>
                                <?php if (isset($_GET['error']) || $errors !== '') {
                    ?>
                                    <p class="pwdreseterror">
                                        <?php echo $errors; ?>
                                    </p>
                                    <?php
                }

                if ($fields === 'stage1') {
                    ?>
                                    <p><label for="email"><?php echo __('emailaddress63'); ?></label></p>
                                    <p><input name="email" type="text" id="email" autofocus></p>
                                    <p><button type="submit" name="Submit" value="stage1Submit"><?php echo __('requestpwdreset63'); ?></button></p>
                                    <?php
                }
                if ($fields === 'stage2') {
                    ?>
                                    <input type="hidden" name="email" value="<?php echo $email; ?>">
                                    <input type="hidden" name="uid" value="<?php echo $uid; ?>">
                                    <p><label for="pwd1"><?php echo __('01pwd63'); ?></label></p>
                                    <p><input name="pwd1" type="password" id="pwd1" autocomplete="off" autofocus></p>
                                    <p><label for="pwd2"><?php echo __('02pwd63'); ?></label></p>
                                    <p><input name="pwd2" type="password" id="pwd2" autocomplete="off"></p>
                                    <p><button type="submit" name="Submit" value="stage2Submit"><?php echo __('button63'); ?></button></p>
                                    <?php
                } ?>

                            </fieldset>
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token'] ?>">
                        </form>
                        <?php
            } elseif ($message !== '') {
                echo $message;
            } elseif ($errors !== '') {
                echo $errors;
            }
        } else {
            ?>
                    <p class="error">
                        <?php echo __('cannot_read_conf'); ?>
                    </p>
                    <?php
        } ?>
            </div>
        </div>

        </body>
        </html>
        <?php
    } else {
        die();
    }
} else {
    die(__('conferror63'));
}
