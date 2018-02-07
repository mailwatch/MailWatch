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

if (isset($_SERVER['PHP_AUTH_USER']) && !isset($_SESSION['myusername'])) {
    include __DIR__ . '/checklogin.php';
} elseif (!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SESSION['myusername']) && isset($_GET['httpbasic'])) {
    header('WWW-Authenticate: Basic realm="MailWatch for MailScanner"');
    header('HTTP/1.0 401 Unauthorized');
    header('Location: login.php?error=baduser');
    exit;
} elseif (!isset($_SESSION['myusername'])) {
    if (isset($_SERVER['REQUEST_URI'])) {
        $_SESSION['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
    }
    header('Location: login.php');
    exit;
}
