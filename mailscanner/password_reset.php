<?php
/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2017  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once __DIR__ . '/functions.php';
if (!QUARANTINE_USE_SENDMAIL) {
    // Load in the required PEAR modules
    require_once MAILWATCH_HOME . '/lib/pear/Mail.php';
    require_once MAILWATCH_HOME . '/lib/pear/Mail/smtp.php';
    require_once MAILWATCH_HOME . '/lib/pear/Mail/mime.php';
    date_default_timezone_set(TIME_ZONE);
}
$showpage = false;
$fields = '';
$errors='';
$message = '';
$link = dbconn();

/**
 * @param $count
 * @return string
 */
function get_random_string($count)
{
    $bytes = openssl_random_pseudo_bytes($count);
    return bin2hex($bytes);
}

/**
 * @param $email
 * @param $html
 * @param $text
 * @param $subject
 */
function send_email($email,$html,$text, $subject){
    $mime = new Mail_mime("\n");
    if (defined('PWD_RESET_FROM_NAME') && defined('PWD_RESET_FROM_ADDRESS') && PWD_RESET_FROM_NAME !== '' && PWD_RESET_FROM_ADDRESS !== '') {
        $sender = PWD_RESET_FROM_NAME . '<' . PWD_RESET_FROM_ADDRESS . '>';
    } else {
        $sender = QUARANTINE_REPORT_FROM_NAME . ' <' . QUARANTINE_FROM_ADDR . '>';
    }
    $hdrs = array(
        'From' => $sender,
        'To' => $email,
        'Subject' => $subject,
        'Date' => date("r")
    );
    $mime_params = array(
        'text_encoding' => '7bit',
        'text_charset' => 'UTF-8',
        'html_charset' => 'UTF-8',
        'head_charset' => 'UTF-8'
    );
    $mime->addHTMLImage(MAILWATCH_HOME . IMAGES_DIR . MW_LOGO, 'image/png', MW_LOGO, true);
    $mime->setTXTBody($text);
    $mime->setHTMLBody($html);
    $body = $mime->get($mime_params);
    $hdrs = $mime->headers($hdrs);
    $mail_param = array('host' => QUARANTINE_MAIL_HOST, 'port' => QUARANTINE_MAIL_PORT);
    $mail =new Mail_smtp($mail_param);
    $mail->send($email, $hdrs, $body);
}

if (defined('PWD_RESET') && PWD_RESET === true) {
    if (isset($_POST['Submit']) && $_POST['Submit'] === __('requestpwdreset63')) {
        //check email add registered user and password reset is allowed
        $email = $link->real_escape_string($_POST['email']);
        $sql = "SELECT * FROM users WHERE username = '$email'";
        $result = dbquery($sql);
        if ($result->num_rows !== 1) {
            //user not found
            $errors = '<p class="pwdreseterror">'.__('notfound63').'</p>';
            $showpage = true;
        } else {
            //user found, now check type of user
            $row = $result->fetch_assoc();
            if ($row['type'] === 'U') {
                //user type is user, password reset allowed
                $rand = get_random_string(10);
                $resetexpire = time() + 60*60*RESET_LINK_EXPIRE;
                $sql = "UPDATE users SET resetid = '$rand', resetexpire = '$resetexpire' WHERE username = '$email'";
                $result = dbquery($sql);
                if (!$result) {
                    die(__('errordbupdate63'));
                }
                $html = '<!DOCTYPE html>
<html>
<head>
 <title>'.__('title63').'</title>
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
  <td><img src="'. IMAGES_DIR . MW_LOGO .'" alt="'.__('mwlogo99').'"/></td>
  <td align="center" valign="middle">
   <h2>'.__('h2email63').'</h2>
   <p>'. sprintf(__('p1email63'), $email) . '</p>
    <a href="' . QUARANTINE_REPORT_HOSTURL . '/password_reset.php?stage=2&user=' . $email . '&uid=' . $rand . '"><button>'.__('button63').'</button></a></p>
  </td>
 </tr>
 </table>
</body>
</html>';
                $text = sprintf(__('01emailplaintxt63'), $email). QUARANTINE_REPORT_HOSTURL . '/password_reset.php?stage=2&user=' . $email . '&uid=' . $rand;

                //Send email
                $subject = __('01emailsubject63');
                send_email($email,$html,$text,$subject);
                $message = '<p>'.__('01emailsuccess63').'</p>';
                $showpage = true;
            } else {
                //password reset not allowed
                die(__('resetnotallowed63'));
            }
        }
    } elseif (isset($_POST['Submit']) && $_POST['Submit'] === __('button63')) {
        //check passwords match, update password in database, update password last changed date, increase password reset counter, email user to inform of password reset
        $email = $link->real_escape_string($_POST['email']);
        $uid = $link->real_escape_string($_POST['uid']);
        if ($_POST['pwd1'] === $_POST['pwd2']) {
            //passwords match, now we need to store them
            //first, check form hasn't been modified
            $sql = "SELECT resetid FROM users WHERE username = '$email'";
            $result = dbquery($sql);
            if (!$result) {
                die("Error: ".$link->error);
            }
            $row = $result->fetch_array();
            if ($row['resetid'] === $_POST['uid']) {
                require_once(MAILWATCH_HOME . '/lib/password.php');
                $password = $link->real_escape_string(password_hash($_POST['pwd1'], PASSWORD_DEFAULT));
                $lastreset = time();
                $sql = "UPDATE users SET password = '$password', resetid = '', resetexpire = '0', lastreset ='$lastreset' WHERE username ='$email'";
                $result = dbquery($sql);
                if (!$result) {
                    die(__('errorpwdchange63'));
                }

                //now send email telling user password has been updated.
                $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
 <title>'.__('01pwdresetemail63').'</title>
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
  <td><img src="'. IMAGES_DIR . MW_LOGO .'" alt="'.__('mwlogo99').'"/></td>
  <td align="center" valign="middle">
   <h2>'.__('02pwdresetemail63').'</h2>
   <p>'.sprintf(__('03pwdresetemail63'), $email) . '</p>
  </td>
 </tr>
 </table>
</body>
</html>';
                $text = sprintf(__('04pwdresetemail63'), $email);

                //Send email
                $subject = __('02emailsubject63');
                send_email($email,$html,$text,$subject);
                $message = '<p>' . __('pwdresetsuccess63') . '<br/>
<a href="login.php"><button>' . __('login01') . '</button></a></p>';
                $showpage = true;
            } else {
                die(__('pwdresetidmismatch'));
            }
        } else {
            $errors = '<p class="pwdreseterror">' . __('pwdmismatch');
            $fields = "stage2";
            $showpage = true;
        }
    } elseif (isset($_GET['stage']) && $_GET['stage'] === 1) {
        //first stage, need to get email address
        $fields = 'stage1';
        $showpage = true;
    } elseif (isset($_GET['stage']) && $_GET['stage']=== 2) {
        //need to check if reset allowed, and reset password
        if (isset($_GET['user']) && isset($_GET['uid'])) {
            //check that uid is correct
            //dbconn();
            $email = $link->real_escape_string($_GET['user']);
            $uid = $link->real_escape_string($_GET['uid']);
            $sql = "SELECT * FROM users WHERE username = '$email'";
            $result = dbquery($sql);
            if (!$result) {
                die(__('errordb63') . $link->error);
            }
            if ($result->num_rows !== '1') {
                echo __('usernotfound63');
            } else {
                $row = $result->fetch_array();
                if ($row['resetid'] === $uid) {
                    //reset id matches - check if link expired
                    if ($row['resetexpire'] < time()) {
                        echo __('resetexpired63') . '<a href="password_reset.php?stage=1">'.__('button63') . '</a>';
                    } else {
                        $fields = "stage2";
                        $showpage = true;
                    }
                } else {
                    echo __('pwdresetidmismatch');
                }
            }
        } else {
            //no matches - deny
            die(__('brokenlink63'));
        }
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
            <style type="text/css">
                body {
                    background-color: #ffffff;
                    color: #000;
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 16px;
                    line-height: 1.5em;
                }

                .pwdreset {
                    margin: 50px auto;
                    width: 308px;
                }

                .pwdreset h1 {
                    background-color: #f7ce4a;
                    color: #222;
                    font-size: 28px;
                    margin: 0;
                    text-align: center;
                }

                .pwdreset form {
                    border: 2px solid #000000;
                    border-top: 0;
                    background-color: #fff;

                    -webkit-border-radius: 0 0 15px 15px;
                    -moz-border-radius: 0 0 15px 15px;
                    border-radius: 0 0 15px 15px;

                }

                .pwdreset fieldset {
                    border: 0;
                    margin: 0;
                    padding: 20px 20px;
                }

                .pwdreset fieldset p {
                    color: #222;
                    margin: 0;
                    margin-bottom: 8px;
                }

                .pwdreset fieldset p:last-child {
                    margin-bottom: 0;
                }

                .pwdreset p.pwdreseterror {
                    background-color: #F2DEDE;
                    border-color: #EBCCD1;
                    color: #A94442;
                    padding: 10px;
                    text-align: center;

                    -webkit-border-radius: 3px;
                    -moz-border-radius: 3px;
                    border-radius: 3px;
                }

                input {
                    border: 0;
                    border-bottom: 1px solid #222;
                    font-family: inherit;
                    font-size: inherit;
                    font-weight: inherit;
                    line-height: inherit;
                    -webkit-appearance: none;
                }

                .pwdreset fieldset input[type="text"], .pwdreset fieldset input[type="password"] {
                    background-color: #e9e9e9;
                    color: #222;
                    padding: 4px;
                    width: 256px;
                    margin-bottom: 16px;
                }

                .pwdreset fieldset input[type="submit"] {
                    background-color: #f7ce4a;
                    color: #222;
                    display: block;
                    margin: 0 auto;
                    padding: 4px;
                    -webkit-border-radius: 3px;
                    -moz-border-radius: 3px;
                    border-radius: 3px;
                    border: 0;
                }

                .pwdreset fieldset input[type="submit"]:hover {
                    background-color: #deb531;
                }

                .pwdreset .border-rounded {
                    border:solid 2px #000;
                    -webkit-border-radius:15px;
                    -moz-border-radius:15px;
                    border-radius:15px;
                    padding:15px;
                }
            </style>
        </head>
        <body>
        <div class="pwdreset">
            <img src="<?php echo IMAGES_DIR . MW_LOGO; ?>" alt="<?php echo __('mwlogo99'); ?>">
            <div class="border-rounded">
            <h1><?php echo __('title63'); ?></h1>
            <?php if (file_exists('conf.php')) {
            if ($fields !== '') {
                ?>
                    <form name="pwdresetform" class="pwdresetform" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <fieldset>
                            <?php if (isset($_GET['error']) || $errors !== '') {
                    ?>
                                <p class="pwdreseterror">
                                    <?php echo $errors; ?>
                                </p>
                                <?php

                } ?>
                            <?php
                            if ($fields === 'stage1') {
                                ?>
                                <p><label><?php echo __('emailaddress63'); ?></label></p>
                                <p><input name="email" type="text" id="email" autofocus></p>
                                <p><input type="submit" name="Submit" value="<?php echo __('requestpwdreset63'); ?>"></p>
                                <?php

                            }
                if ($fields === 'stage2') {
                    ?>
                                <input type="hidden" name="email" value="<?php echo $email; ?>">
                                <input type="hidden" name="uid" value="<?php echo $uid; ?>">
                                <p><label><?php echo __('01pwd63'); ?></label></p>
                                <p><input name="pwd1" type="password" id="pwd1" autofocus></p>
                                <p><label><?php echo __('02pwd63'); ?></label></p>
                                <p><input name="pwd2" type="password" id="pwd2"></p>
                                <p><input type="submit" name="Submit" value="<?php echo __('button63'); ?>"></p>
                                <?php

                } ?>

                        </fieldset>
                    </form>
                    <?php

            }
            elseif ($message!=='') {
                echo $message;
            }
            elseif ($errors !== ''){
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

    }
} else {
    die(__('conferror63'));
}
