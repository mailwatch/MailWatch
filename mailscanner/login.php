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
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo __('mwloginpage01')?></title>
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

        .login {
            margin: 50px auto;
            width: 350px;
        }

        .login h1 {
            background-color: #f7ce4a;
            -webkit-border-radius: 15px 15px 0 0;
            -moz-border-radius: 15px 15px 0 0;
            border-radius: 15px 15px 0 0;
            color: #222;
            font-size: 28px;
            padding: 15px 15px;
            margin: 0;
            text-align: center;
            border: 2px solid #000000;
            border-bottom: 0;
        }

        .login form {
            border: 2px solid #000000;
            border-top: 0;
            background-color: #fff;

            -webkit-border-radius: 0 0 15px 15px;
            -moz-border-radius: 0 0 15px 15px;
            border-radius: 0 0 15px 15px;

        }

        .login fieldset {
            border: 0;
            margin: 0;
            padding: 20px 20px;
        }

        .login fieldset p {
            color: #222;
            margin: 0 0 8px;
        }

        .login fieldset p:last-child {
            margin-bottom: 0;
        }

        .login p.loginerror {
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

        .login fieldset input[type="text"], .login fieldset input[type="password"] {
            background-color: #e9e9e9;
            color: #222;
            padding: 4px;
            width: 298px;
            margin-bottom: 16px;
        }

        .login fieldset input[type="submit"] {
            background-color: #f7ce4a;
            color: #222;
            display: block;
            margin: 0 auto;
            padding: 4px 0;
            width: 200px;
            -webkit-border-radius: 3px;
            -moz-border-radius: 3px;
            border-radius: 3px;
            border: 0;
        }

        .login fieldset input[type="submit"]:hover {
            background-color: #deb531;
        }

        .pwdresetButton {
            text-align: center;
            margin-bottom:10px;
        }
        .pwdresetButton button {
            padding: 10px;
            background-color:#f7ce4a;

        }

        .pwdresetButton button:hover {
            background-color: #deb531;
        }
        .pwdresetButton a{
            color:#000;
            font-size:14px;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="login">
    <div style="text-align: center"><img src="<?php echo IMAGES_DIR . MW_LOGO; ?>" alt="<?php echo __('mwlogo99'); ?>"></div>
    <h1><?php echo __('mwlogin01'); ?></h1>
    <?php if (file_exists('conf.php')) {
    ?>
        <form name="loginform" class="loginform" method="post" action="checklogin.php" autocomplete="off">
            <fieldset>
                <?php if (isset($_GET['error'])) {
        ?>
                    <p class="loginerror">
                        <?php
                        switch ($_GET['error']) {
                            case 'baduser':
                                echo __('badup01');
                                break;
                            case 'emptypassword':
                                echo __('emptypassword01');
                                break;
                            default:
                                echo __('errorund01');
                        } ?>
                    </p>
                <?php

    } ?>

                <p><label for="myusername"><?php echo __('username'); ?></label></p>

                <p><input name="myusername" type="text" id="myusername" autofocus></p>

                <p><label for="mypassword"><?php echo __('password'); ?></label></p>

                <p><input name="mypassword" type="password" id="mypassword"></p>

                <p><input type="submit" name="Submit" value="<?php echo __('login01'); ?>"></p>
            </fieldset>
            <?php
            if(defined('PWD_RESET') && PWD_RESET === true)
            {
                ?>
                <div class="pwdresetButton">
                    <button class="pwdresetButton">
                        <a href="password_reset.php?stage=1">Forgotten Password?</a>
                    </button>
                </div>
            <?php
            }
            ?>

        </form>
    <?php

} else {
    ?>
        <p class="error">
            <?php echo __('cannot_read_conf'); ?>
        </p>
    <?php

}
    ?>
</div>

</body>
</html>
