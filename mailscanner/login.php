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
    <title><?php echo __('mwloginpage01') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="images/favicon.png">
    <link rel="stylesheet" href="style.css" type="text/css">
</head>
<body class="loginbody">
<div class="login">
    <div style="text-align: center"><img src="<?php echo IMAGES_DIR . MW_LOGO; ?>" alt="<?php echo __('mwlogo99'); ?>">
    </div>
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
