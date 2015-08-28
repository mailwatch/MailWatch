<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2015  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
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

require_once('../functions.php');
require_once('./luser_functions.php');
require_once('Pager.php');
require_once('../filter.inc');
session_start();
//authenticate();

/* Known reqtypes:
   newform: Print a form for creating a new account.
   newsubmit: Submit data for creating a new account.
   logout: Log a user out.
   login: Log user in if valid credentials supplied, otherwise print
      a login form.
*/

$logged_in = false;
if (isset($_REQUEST['reqtype'])) {
    $reqtype = sanitizeInput($_REQUEST['reqtype']);
} else {
    $reqtype = 'login';
}

switch ($reqtype) {
    case 'login':
        // Check for valid credentials in session - if found, provide a link to
        //    the message listing, as well as a logout link.
        // If no valid credentials, print a login form with reqtype set to 'login'
        //    again.
        if (isset($_SESSION['luser']) && isset($_SESSION['pass'])) {
            // Session credentials found - but are they valid?
            $user = sanitizeInput($_SESSION['luser']);
            $pass = sanitizeInput($_SESSION['pass']);
            if (luser_auth($user, $pass)) {
                // Yes, they're valid...
                debug("Login successful as user $user using session credentials.\n<br>\n");
                $logged_in = true;
            } else {
                // Invalid credentials in the session!
                debug("Invalid credentials in the session - login failed.\n<br>\n");
            }
        } else {
            // Non-existant or incomplete session credentials - check for credentials
            //    in form data.  If found, check those.  If not found or found to be
            //    invalid, then dump a login form.
            if (isset($_REQUEST['luser']) && isset($_REQUEST['pass'])) {
                $user = sanitizeInput($_REQUEST['luser']);
                $pass = sanitizeInput($_REQUEST['pass']);
                if (luser_auth($user, $pass)) {
                    // Valid creds in form, add to session.
                    debug("Login successful as user $user based on form data..\n<br>\n");
                    debug("Adding credentials to session data.\n<br>\n");
                    $_SESSION['luser'] = $user;
                    $_SESSION['pass'] = $pass;
                    $logged_in = true;
                } else {
                    // Invalid creds in the form data!
                    echo "Invalid creds in form data - login failed.\n<br>\n";
                    luser_loginfailed();
                    exit;
                }
            } else {
                // Missing or incomplete form data.  Print a login form.
                debug("Missing or incomplete form data.\n<br>\n");
                luser_loginform();
                exit;
            }
        }
        break;
    case 'logout':
        luser_logout();
        // echo "Logout complete.\n";
        debug("Logout complete.\n");
        $logged_in = false;
        break;
    case 'newform':
        // Someone clicked "Create a new account", so ask them for an email address.
        // Reqtype in the form is set to 'newsubmit'.
        luser_newform();
        exit;
        break;
    case 'newsubmit':
        // $reqtype == 'newsubmit'...
        // We got an email address to create an account for - create the account
        //    and tell the user to check his email.
        $user = sanitizeInput($_REQUEST['luser']);
        if (!luser_create($user, genpassword())) {
            echo "Error: Unable to create user account.\n";
            exit;
        }
        luser_checkyourmail();
        exit;
        break;
    default:
        // Unrecognized reqtype.
        echo "Error: Unrecognized request type (" . $reqtype . ")\n<br>\n";
        luser_loginfailed();
        exit;
}

// echo "Reqtype: $reqtype\n<br>\n";
// echo "Luser: $user\n<br>\n";
// echo "Pass: $pass\n<br>\n";
debug("Reqtype: $reqtype\n<br>\n");
debug("Luser: $user\n<br>\n");
debug("Pass: $pass\n<br>\n");

if (luser_exists($user)) {
    debug("User exists: $user\n<br>\n");
}

if (luser_auth($user, $pass)) {
    debug("Password valid: $pass\n<br>\n");
}

if ($logged_in) {
    print_successpage();
    exit;
} else {
    luser_loginform();
}

function print_successpage()
{
    $refresh = luser_loginstart("Login");
    echo "<div align=\"center\">\n";
    echo "<TABLE width=\"400\" CLASS=\"mail\" BORDER=0 WIDTH=100% CELLSPACING=2 CELLPADDING=2>\n";
    echo " <THEAD>\n";
    echo "  <TH COLSPAN=2>Thank you - you are now logged in.</TH>\n";
    echo " </THEAD>\n";
    echo " <TR>\n";
    echo "  <TD ALIGN=\"center\" colspan=\"2\">You may now either\n";
    echo "   <a href=\"luser_rep_message_listing.php\">view your messages</a> or\n";
    echo "   <a href=\"" . sanitizeInput($_SERVER['PHP_SELF']) . "?reqtype=logout\">log out</a>.\n";
    echo "  </TD>\n";
    echo " </TR>\n";
    echo "</TABLE></FORM>\n";
    echo "</div>\n";
}

function luser_checkyourmail()
{
    $refresh = luser_loginstart("Password Sent");
    echo "<div align=\"center\">\n";
    echo "<TABLE width=\"400\" CLASS=\"mail\" BORDER=0 WIDTH=100% CELLSPACING=2 CELLPADDING=2>\n";
    echo " <THEAD>\n";
    echo "  <TH COLSPAN=2>Account updated - check your email.</TH>\n";
    echo " </THEAD>\n";
    echo " <TR>\n";
    echo "  <TD ALIGN=\"left\" colspan=\"2\">An email message containing your new login credentials has been sent\n";
    echo "   to the address you entered. <p>\nPlease check your email.\n";
    echo "   When you receive your password, you may\n";
    echo "   <a href=\"" . sanitizeInput($_SERVER['PHP_SELF']) . "?reqtype=login\">click here to log in</a>.\n";
    echo "  </TD>\n";
    echo " </TR>\n";
    echo "</TABLE></FORM>\n";
    echo "</div>\n";
}


function luser_loginfailed()
{
    // Login credentials failed - tell the user to start over.
    luser_logout();
    echo "Error: Login failed - please back up and try your login again.\n<p>\n";
    echo "If you are experiencing repeated login failures, you may want to ";
    printf('<a href="%s?newform">reset your password</a>', sanitizeInput($_SERVER['PHP_SELF']));
    echo "\n\n<p>\n\n";
    echo "If you are still having problems even after resetting your password,\n";
    echo "please contact the system administrator.\n<br>\n";
    return true;
}

function luser_logout()
{
    // We were asked to log out a user.
    // This code fails sometimes?
    // See: http://www.php.net/manual/en/function.session-destroy.php
    session_unset();
    session_destroy();
    return true;
}

function luser_loginform()
{
    $refresh = luser_loginstart("Login");
    // Display table headings
    echo "<div align=\"center\">\n";
    printf('<FORM name="loginform" method="post" action="%s">%s', sanitizeInput($_SERVER['PHP_SELF']), "\n");
    printf('<INPUT type="hidden" name="reqtype" value="login">%s', "\n");
    echo "<TABLE width=\"400\" CLASS=\"mail\" BORDER=0 WIDTH=100% CELLSPACING=2 CELLPADDING=2>\n";
    echo " <THEAD>\n";
    echo "  <TH COLSPAN=2>Please Log In</TH>\n";
    echo " </THEAD>\n";
    echo " <TR>\n";
    echo "  <TD ALIGN=\"LEFT\">Email Address:</TD>\n";
    echo "  <TD><input name=\"luser\" size=\"30\" maxlength=\"1024\"></TD>\n";
    echo " </TR><TR>\n";
    echo "  <TD ALIGN=\"LEFT\">Password:<br>\n";
    echo "  </TD>\n";
    echo "  <TD><input name=\"pass\" type=\"password\" size=\"30\"></TD>\n";
    echo " </TR>\n";
    echo " <TR>\n";
    echo "  <TD colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"Log In\"></TD>\n";
    echo " </TR>\n";
    echo " <TR>\n";
    printf(
        '  <TD colspan="2">Don\'t have an account yet? <A HREF="%s?reqtype=newform">Click here to create one or to reset your password.</A>%s',
        sanitizeInput($_SERVER['PHP_SELF']),
        "\n"
    );
    echo "   (Hint: If this is your first time logging in, you <font color=red>MUST</font>\n";
    echo "   create an account FIRST.)\n";
    echo "  </TD>";
    echo " </TR>\n";
    echo "</TABLE>\n</FORM>";
    echo "</div>\n";

    html_end();
}

function luser_newform()
{
    $refresh = luser_loginstart("Enter email address");
    // Display table headings
    echo "<div align=\"center\">\n";
    printf('<FORM name="newform" method="post" action="%s">%s', sanitizeInput($_SERVER['PHP_SELF']), "\n");
    printf('<INPUT type="hidden" name="reqtype" value="newsubmit">%s', "\n");
    echo "<TABLE width=\"400\" CLASS=\"mail\" BORDER=0 WIDTH=100% CELLSPACING=2 CELLPADDING=2>\n";
    echo " <THEAD>\n";
    echo "  <TH COLSPAN=2>Please enter your email address.<br>A new password will be emailed to you.</TH>\n";
    echo " </THEAD>\n";
    echo " <TR>\n";
    echo "  <TD ALIGN=\"LEFT\">Email Address:</TD>\n";
    echo "  <TD><input name=\"luser\" size=\"30\" maxlength=\"1024\"></TD>\n";
    echo " </TR>\n";
    echo " <TR>\n";
    printf('  <TD colspan="2" align="center"><INPUT type="submit" name="submit" value="Create Account"></TD>%s', "\n");
    echo " </TR>\n";
    echo "</TABLE>\n</FORM>";
    echo "</div>\n";
    html_end();
}
