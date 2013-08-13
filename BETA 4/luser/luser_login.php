<?

require_once('../functions.php');
require_once('./luser_functions.php');
require_once('DB.php');
require_once('DB/Pager.php');
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

$logged_in=false;
if (isset($_REQUEST['reqtype'])) {
 $reqtype=$_REQUEST['reqtype'];
} else {
 $reqtype='login';
}

if ($reqtype == 'login') {
 // Check for valid credentials in session - if found, provide a link to
 //    the message listing, as well as a logout link.
 // If no valid credentials, print a login form with reqtype set to 'login'
 //    again.
 if (isset($_SESSION['luser']) && isset($_SESSION['pass'])) {
  // Session credentials found - but are they valid?
  $user=$_SESSION['luser'];
  $pass=$_SESSION['pass'];
  if (luser_auth($user, $pass)) {
   // Yes, they're valid...
   debug("Login successful as user $user using session credentials.\n<br>\n");
   $logged_in=true;
  } else {
   // Invalid credentials in the session!
   debug("Invalid credentials in the session - login failed.\n<br>\n");
  }
 } else {
  // Non-existant or incomplete session credentials - check for credentials
  //    in form data.  If found, check those.  If not found or found to be
  //    invalid, then dump a login form.
  if (isset($_REQUEST['luser']) && isset($_REQUEST['pass'])) {
   $user=$_REQUEST['luser'];
   $pass=$_REQUEST['pass'];
   if (luser_auth($user, $pass)) {
    // Valid creds in form, add to session.
    debug("Login successful as user $user based on form data..\n<br>\n");
    debug("Adding credentials to session data.\n<br>\n");
    $_SESSION['luser'] = $user;
    $_SESSION['pass'] = $pass;
    $logged_in=true;
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
} elseif ($reqtype == 'logout') {
 luser_logout();
 // echo "Logout complete.\n";
 debug("Logout complete.\n");
 $logged_in=false;
} elseif ($reqtype == 'newform') {
 // Someone clicked "Create a new account", so ask them for an email address.
 // Reqtype in the form is set to 'newsubmit'.
 luser_newform();
 exit;
} elseif ($reqtype == 'newsubmit') {
 // $reqtype == 'newsubmit'...
 // We got an email address to create an account for - create the account
 //    and tell the user to check his email.
 $user=$_REQUEST['luser'];
 if (! luser_create($user, genpassword())) {
  echo "Error: Unable to create user account.\n";
  exit;
 }
 luser_checkyourmail();
 exit;
} elseif (isset($_REQUEST['reqtype'])) {
 // Unrecognized reqtype.
 echo "Error: Unrecognized request type (".$_REQUEST['reqtype'].")\n<br>\n";
 luser_loginfailed();
 exit;
} else {
 // No reqtype whatsoever.  Ought to never get here.
 echo "No request type specified.\n<br>\n";
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

if (luser_auth($user,$pass)) {
 debug("Password valid: $pass\n<br>\n");
}

if ($logged_in) {
 print_successpage();
 exit;
} else {
 luser_loginform();
}

function print_successpage() {
 $refresh = luser_loginstart("Login");
 echo "<div align=\"center\">\n";
 echo "<TABLE width=\"400\" CLASS=\"mail\" BORDER=0 WIDTH=100% CELLSPACING=2 CELLPADDING=2>\n";
 echo " <THEAD>\n";
 echo "  <TH COLSPAN=2>Thank you - you are now logged in.</TH>\n";
 echo " </THEAD>\n";
 echo " <TR>\n";
 echo "  <TD ALIGN=\"center\" colspan=\"2\">You may now either\n";
 echo "   <a href=\"luser_rep_message_listing.php\">view your messages</a> or\n";
 echo "   <a href=\"".$_SERVER['PHP_SELF']."?reqtype=logout\">log out</a>.\n";
 echo "  </TD>\n";
 echo " </TR>\n";
 echo "</TABLE></FORM>\n";
 echo "</div>\n";
}

function luser_checkyourmail() {
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
 echo "   <a href=\"".$_SERVER['PHP_SELF']."?reqtype=login\">click here to log in</a>.\n";
 echo "  </TD>\n";
 echo " </TR>\n";
 echo "</TABLE></FORM>\n";
 echo "</div>\n";
}


function luser_loginfailed() {
 // Login credentials failed - tell the user to start over.
 luser_logout;
 echo "Error: Login failed - please back up and try your login again.\n<p>\n";
 echo "If you are experiencing repeated login failures, you may want to ";
 printf('<a href="%s?newform">reset your password</a>', $_SERVER['PHP_SELF']);
 echo "\n\n<p>\n\n";
 echo "If you are still having problems even after resetting your password,\n";
 echo "please contact the system administrator.\n<br>\n";
 return true;
}

function luser_logout() {
 // We were asked to log out a user.
 // This code fails sometimes?
 // See: http://www.php.net/manual/en/function.session-destroy.php
 session_unset();
 session_destroy();
 return true;
}
 
function luser_loginform() {
 $refresh = luser_loginstart("Login");
 // Display table headings
 echo "<div align=\"center\">\n";
 printf('<FORM name="loginform" method="post" action="%s">%s', $_SERVER['PHP_SELF'], "\n");
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
 printf ('  <TD colspan="2">Don\'t have an account yet? <A HREF="%s?reqtype=newform">Click here to create one or to reset your password.</A>%s', $_SERVER['PHP_SELF'], "\n");
 echo "   (Hint: If this is your first time logging in, you <font color=red>MUST</font>\n";
 echo "   create an account FIRST.)\n";
 echo "  </TD>";
 echo " </TR>\n";
 echo "</TABLE>\n</FORM>";
 echo "</div>\n";
 
 html_end();
}

function luser_newform() {
 $refresh = luser_loginstart("Enter email address");
 // Display table headings
 echo "<div align=\"center\">\n";
 printf('<FORM name="newform" method="post" action="%s">%s', $_SERVER['PHP_SELF'], "\n");
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
 printf ('  <TD colspan="2" align="center"><INPUT type="submit" name="submit" value="Create Account"></TD>%s', "\n");
 echo " </TR>\n";
 echo "</TABLE>\n</FORM>";
 echo "</div>\n";
 html_end();
}

?>
