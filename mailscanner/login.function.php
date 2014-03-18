<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// if(!session_is_registered(myusername)) {
if (isset($_SERVER['PHP_AUTH_USER']) && !isset($_SESSION ['myusername'])) {
    include 'checklogin.php';
} elseif (!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SESSION ['myusername']) && isset($_GET['httpbasic'])) {
    header('WWW-Authenticate: Basic realm="MailWatch for MailScanner"');
    header('HTTP/1.0 401 Unauthorized');
    html_start("Not authenticated", 0, false, false);
    echo "<TABLE CLASS=\"boxtable\" WIDTH=100%><TR><TD><H1><FONT COLOR=\"RED\">Authentication Required!</FONT>
		</H1>Your username and/or password are incorrect.</TD></TR>\n";
    html_end();
    exit;
} elseif (!isset($_SESSION['myusername'])) {
    // if not, show an error message and a hyperlink to the login page
    header("Location: login.php");
    exit;
}

