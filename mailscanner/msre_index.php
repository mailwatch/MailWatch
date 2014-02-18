<?php
// $Id: msre_index.php,v 1.4 2005/06/07 20:19:09 jofcore Exp $
/*
msre = MailScanner Ruleset Editor
(c) 2004 Kevin Hanser
Released under the GNU GPL: http://www.gnu.org/copyleft/gpl.html#TOC1

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

// Include of necessary functions
require_once("./functions.php");

include("msre_table_functions.php");

// Authentication checking
session_start();
require('login.function.php');
// Check to see if the user is an administrator
if ($_SESSION['user_type'] != 'A') {
    // If the user isn't an administrator send them back to the index page.
    header("Location: index.php");
    audit_log('Non-admin user attempted to view MailScanner Rule Editor Page');
} else {
    html_start('Ruleset Editor', "0", false, false);

    // ############################
    // ### Includes and whatnot ###
    // ############################


    // ############
    // ### Main ###
    // ############

    echo "<center>\n";
    // start a table
    echo "<table border=\"0\" class=\"mailwatch\">\n";
    TRH(array("Choose a ruleset to edit:"));

    $ruleset_file = array();
    // open directory and read its contents
    if (is_dir(MSRE_RULESET_DIR)) {
        if ($dh = opendir(MSRE_RULESET_DIR)) {
            while (($file = readdir($dh))) {
                // if it's a ruleset (*.rules), add it to the array
                if (preg_match("/.+\.rules$/", $file)) {
                    array_push($ruleset_file, $file);
                }
            }
            closedir($dh);
        }
    }

    if (empty($ruleset_file)) {
        TR(array('No rules found'));
    } else {
        // display it in a sorted table with links
        asort($ruleset_file);
        foreach ($ruleset_file as $this_ruleset_file) {
            TR(array("<a href=\"msre_edit.php?file=$this_ruleset_file\">$this_ruleset_file</a>"));
        }
        // put a blank header line on the bottom... it just looks nicer that way to me
        TRH(array(""));
    }


    html_end();
}
