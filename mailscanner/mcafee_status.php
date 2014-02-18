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

 2005-10-07
 McAfee status by Hubert Nachbaur modified from Sophos status by Steve Freegard
*/

// Include of necessary functions
require_once("./functions.php");

// Authentication checking
session_start();
require('login.function.php');

if ($_SESSION['user_type'] != 'A') {
    header("Location: index.php");
} else {

    html_start("McAfee Status", 0, false, false);

    echo '<table class="boxtable" width="100%">' . "\n";
    echo '<tr>' . "\n";
    echo '<td align="center">' . "\n";
    passthru(get_virus_conf('mcafee') . " --version | awk -f ./mcafee.awk");
    echo '</td>' . "\n";
    echo '</tr>' . "\n";
    echo '</table>' . "\n";

    // Add footer
    html_end();
    // Close any open db connections
    dbclose();
}
