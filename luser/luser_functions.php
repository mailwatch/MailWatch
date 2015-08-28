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

///////////////////////////////////////////////////////////////////////////////
// Settings - modify to suit your configuration
///////////////////////////////////////////////////////////////////////////////

// Who should password change messages appear to come from?
// Don't forget to whitelist this address.
define('LUSER_PASSCHANGEFROM', 'password_change@yourdomain.com');

// Which domains do we serve?  This list should include any
// domain that you want "lusers" to be able to use this
// interface for.
global $luser_allowed_domains;
$luser_allowed_domains = array('example1.com', 'another.example.com');

///////////////////////////////////////////////////////////////////////////////
// Functions
///////////////////////////////////////////////////////////////////////////////

function luser_loginstart($title)
{
    ?>
    <HTML>
    <HEAD>
        <TITLE>MailWatch for MailScanner User Interface</TITLE>
        <LINK REL="StyleSheet" TYPE="text/css" HREF="../style.css">
    </HEAD>
    <BODY>
    <br>&nbsp;<br>
<?php

}

function luser_auth($user, $pass)
{
    // Trever, 20031003
    // return true if user and password are valid, false otherwise.
    // BE SURE TO SANITY CHECK USERNAME AND PASSWORD FIRST!!!
    $sql = "SELECT count(*) FROM lusers WHERE lusername='$user' AND password=md5('$pass')";
    $sth = dbquery($sql);
    $count = mysql_fetch_row($sth);
    if (isset($user) && isset($pass) && $count[0] > 0) {
        return true;
    } else {
        return false;
    }
}

function luser_create($user, $pass)
{
    // Trever, 20031003
    // Create a new luser account.

    // Make sure no one can do bad stuff with our sql.
    if (!lusername_sanitycheck($user)) {
        echo "Error: That username is not allowed - failed sanitycheck.\n<br>\n";
        echo "Error: Username supplied was: " . sanitizeInput($user) . "\n<br>\n";
        return false;
    }

    // Make sure it doesn't already exist.
    if (luser_exists($user)) {
        // We really want to reset a password, not create a new user.
        if (luser_newpass($user, $pass)) {
            // Email the password.
            if (luser_sendpass($user, $pass)) {
                // Sent the password.
                // echo "Yay!\n";
                ;
            } else {
                // Failed to email the password for some reason
                echo "Error: Sending password failed.\n";
            }
            return true;
        } else {
            echo "Error: User exists, but unable to change password.\n<br>\n";
            return false;
        }
    }

    // Insert the record.  Yes, I know there's a race here - but we don't have
    // transactions in mysql 3.23, so...
    $sql = "INSERT INTO lusers VALUES ('$user', md5('$pass'))";
    $sth = dbquery($sql);
    if (!luser_exists($user)) {
        echo "Error: Unable to create user in database.\n";
        return false;
    }

    // Email the password.
    if (luser_sendpass($user, $pass)) {
        // Sent the password.
        // echo "Yay!\n";
    } else {
        // Failed to email the password for some reason
        echo "Sending password failed.\n";
    }

    return true;
}

function luser_newpass($user, $pass)
{
    // Trever, 20031003
    // Create a new luser account.

    // Make sure no one can do bad stuff with our sql.
    if (!lusername_sanitycheck($user)) {
        echo "Error: That username is not allowed - failed sanitycheck.\n<br>\n";
        echo "Error: Username supplied was: " . sanitizeInput($user) . "\n";
        return false;
    }

    // Make sure it doesn't already exist.
    if (!luser_exists($user)) {
        // We really want to create a new user, not reset a password.
        if (luser_create($user, $pass)) {
            if (luser_sendpass($user, $pass)) {
                // Sent the password.
                // echo "Yay!\n";
                return true;
            }
            // Failed to email the password for some reason
            echo "Error: Sending password failed.\n";
            return false;
        } else {
            echo "Error: User doesn't exist, but and I'm unable to create it.\n<br>\n";
            return false;
        }
    }

    // Insert the record.  Yes, I know there's a race here - but we don't have
    // transactions in mysql 3.23, so...
    $sql = "UPDATE lusers set password=md5('$pass') where lusername='$user'";
    $sth = dbquery($sql);
    $sql = "SELECT * from lusers where lusername='$user' and password=md5('$pass')";
    $sth = dbquery($sql);
    $count = mysql_fetch_row($sth);
    if (!$count[0] > 0) {
        echo "Error: Unable to update database.\n<br>\n";
        echo "count was:" . $count[0] . "\n<br>\n";
        return false;
    }

    return true;
}

function luser_sendpass($user, $pass)
{
    // Trever, 20031003
    // Email a password to a user.
    // We don't sanitycheck the username here - that's elsewhere, since this
    // isn't a function to expose directly to user input.
    $to = $user;
    $from = LUSER_PASSCHANGEFROM;
    $subject = "Updated password for spam filter log";

    include('Mail.php');

    $message = "Your new password for MailWatch is:\n\n";
    $message .= "\tUsername: $user\n\tPassword: $pass\n\n";
    $message .= "You may use this information to log into the system here:\n";
    $message .= "http://relay.public.herff-jones.com/" . sanitizeInput($_SERVER['PHP_SELF']);
    $message .= "\n\nPlease save this message securely for future reference.\n";

    if (
    !mail(
        $to,
        $subject,
        $message,
        "From: MailWatch Password Update Service <$from>\r\n" .
        "Reply-To: $from"
    )
    ) {
        // Sending the message failed for some reason.
        echo "Error: Unable to send password email - please contact the admin.\n";
        return false;
    }

    return true;
}

function luser_exists($luser)
{
    // Trever, 20031003
    // return true if user and password are valid, false otherwise.
    // BE SURE TO SANITY CHECK USERNAME FIRST!!!
    $sql = "SELECT count(*) FROM lusers WHERE lusername='$luser'";
    $sth = dbquery($sql);
    $count = mysql_fetch_row($sth);
    if (isset($luser) && $count[0] > 0) {
        return true;
    } else {
        return false;
    }
}

function lusername_sanitycheck($lusername)
{
    // Trever, 20031003
    // Make sure a username is safe to use in a mysql query.  Also make sure
    // the username is an email address in our domain.
    // Return true if all checks succeed, false otherwise.

    // Note that % and _ are allowed even though they're special to mysql.  A
    // different function should escape them if that's needed.
    // (mysql_real_escape_string($lusername) could be used for that.)

    // Note also that I'm ambiguous about what to do regarding accounts that
    // are @ourdomain.com but don't exist.  For example, someone could create
    // an account for xxxxxxxxxxxxxxxxxxxxx@ourdomain.com and I'd happily send
    // email there - not a good thing.  But very few people keep a list of all
    // the valid email addresses at their domain (unfortunately) to check
    // against, so that's not a realistic sanity check to include code for.

    // Not even sure the protection of the sql command is needed - it just feels
    // like the safe thing to do.

    // Check just to make sure only chars I expect are included.  Is the list
    // broad enough to cover 99% of all email addresses?
    if (preg_match("/[^\w\d._@+%-]/", $lusername)) {
        // We matched something other than allowed characters - insane.
        echo "Error: You entered characters I can't allow in an email address.\n<br>\n";
        return false;
    }

    // Check to make sure there's an @.
    if (!preg_match("/@/", $lusername)) {
        // No @ in an email address?  I'll pretend I think that's impossible! ;^)
        echo "Error: Email address must contain an @.\n<br>\n";
        return false;
    }

    // Check to make sure it's not too long.  Would rather not hard-code this
    // here, but I'd rather not code in a limit period...
    if (strlen($lusername) > 1024) {
        echo "Error: Email address must be less than 1024 characters.\n<br>\n";
        return false;
    }

    // Check to make sure the domain is one of ours - otherwise we can be
    // used to DDoS others.
    // List of domains we accept email for
    // Define the entire list of domains for which users may be attempting to
    // log into MailWatch.
    global $luser_allowed_domains;
    // $luser_allowed_domains=array('wondious.com', 'www.wondious.com', 'herff-jones.com');
    // See above for definition of luser_allowed_domains.

    $domaincount = count($luser_allowed_domains);
    $domain = preg_replace('/.*@([^@]+)$/', '$1', $lusername);
    $sane = false;
    $i = 0;
    $matchstring = "/^" . $domain . "$/i";
    while ((!$sane) && ($i < $domaincount)) {
        if (preg_match($matchstring, $luser_allowed_domains[$i])) {
            $sane = true;
        }
        ++$i;
    }
    if (!$sane) {
        echo "Error: ($i, $domaincount, $valid_domains) Email domain ($domain) is not one handled by this system.\n<br>\n";
        return false;
    }

    return true;
}

function pickone($fromstring)
{
    // Trever, 20031003
    // Given a string, randomly return one letter out of it.
    $pos = rand(0, (strlen($fromstring) - 1));
    return substr($fromstring, $pos, 1);
}

function picktwo($fromstring)
{
    // Trever, 20031003
    // Given a string, randomly return a pair of letters out of it, where
    // a pair always starts with an even character position (numbered from 0).
    $pos = rand(0, (floor(strlen($fromstring) / 2) - 1)); // So we need >3 chars!!!
    return substr($fromstring, $pos * 2, 2);
}

function genpassword()
{
    // Trever, 20031003
    // Generate a "random" password string that's easy to remember.
    // We're not worried about really strong security here - we just
    // want to enforce some common sense.

    // This function produces passwords that fit patterns, with the patterns
    // chosen to be something the user reduces to syllables that are more easily
    // remembered than truely random strings.

    // Here are some sample passwords from this function:
    // prtipp5 1sain wotakex sadobux 64radre roaun0 pultug0 koy7jut uwrej5
    // mmeith8 15rowra druomm9 euqj6 nniill1 yooch5 pey4lec piyabem nnieth7
    // pre38 yomsiw1 shhach3 votuces trjuch8 ooyd8 celimal 8xawr wioll1


    /*
    We define an array of patterns to choose from.  Once a pattern
    is chosen, we use it to decide how to build the password string.

    The following characters may be used in the the patterns:
       c = Replace with a consonant.
       v = Replace with a vowel.
       p = Replace with a "pair" of letters from the "pairs string"
       d = Replace with a numeric digit.
    */

    $consonants = 'bcdfghjklmnpqrstvwxyz';
    $vowels = 'aeiou';
    $pairs = 'thphchshwrtrllnnmmppdrprunin'; // two-letter combinations

    $patterns = array(
        "cvccvcd",
        "cvcdcvc",
        "cvvpd",
        "pvvpd",
        "dcvp",
        "ddcvpv",
        "pcvpd",
        "cvcvcvc",
        "pvdd",
        "vpvcd",
        "vvccd"
    );

    $mypattern = $patterns[rand(0, (count($patterns) - 1))];

    $password = "";
    $i = 0;
    while ($i < strlen($mypattern)) {
        $type = substr($mypattern, $i, 1);
        if ($type == 'c') {
            $addendum = pickone($consonants);
        } elseif ($type == 'v') {
            $addendum = pickone($vowels);
        } elseif ($type == 'p') {
            $addendum = picktwo($pairs);
        } else {
            $addendum = rand(0, 9);
        }

        $password .= $addendum;
        ++$i;
    }

    return $password;
}

function luser_html_start($title, $refresh = 0, $cacheable = true)
{
    global $luser;
    if (!$cacheable) {
        // Cache control (as per PHP website)
        header("Expires: Sat, 10 May 2003 00:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
    }
    ?>
    <HTML>
    <HEAD>
        <TITLE>MailWatch for MailScanner - <?php echo $title;
    ?></TITLE>
        <LINK REL="StyleSheet" TYPE="text/css" HREF="../style.css">
        <?php
        if ($refresh > 0) {
            echo "<META HTTP-EQUIV=\"refresh\" CONTENT=\"$refresh\">";
        }
    ?>
    </HEAD>
<BODY>
<TABLE BORDER=0 CELLPADDING=5 WIDTH=100%>
    <TR>
        <TD ALIGN="LEFT"><IMG SRC="../images/mailscannerlogo.gif"></TD>
        <?php
        if (MAILQ) {
            echo "  <TD ALIGN=\"RIGHT\" VALIGN=\"TOP\" WIDTH=20%>\n";
            $inq = mysql_result(dbquery("SELECT COUNT(*) FROM inq"), 0);
            $outq = mysql_result(dbquery("SELECT COUNT(*) FROM outq"), 0);
            echo "   <TABLE BORDER=0 CLASS=\"MAIL\" WIDTH=200>\n";
            echo "    <THEAD><TH ALIGN=\"CENTER\" COLSPAN=2>Mail Queue</TH></THEAD>\n";
            echo "    <TR><TD><A HREF=\"mailq.php?queue=inq\">Inbound Queue:</A></TD><TD>" . $inq . "</TD>\n";
            echo "    <TR><TD><A HREF=\"mailq.php?queue=outq\">Outbound Queue:</A></TD><TD>" . $outq . "</TD>\n";
            echo "   </TABLE>\n";
            echo "  </TD>\n";
        }

    echo '<TD WIDTH="20%" ALIGN="RIGHT">';

    $sth = dbquery(
            "
 SELECT
  COUNT(*) AS processed,
  SUM(CASE WHEN virusinfected>0 THEN 1 ELSE 0 END) AS virii,
  ROUND((SUM(CASE WHEN virusinfected>0 THEN 1 ELSE 0 END)/COUNT(*))*100,1) AS viriipercent,
  SUM(CASE WHEN nameinfected>0 THEN 1 ELSE 0 END) AS blockedfiles,
  ROUND((SUM(CASE WHEN nameinfected>0 THEN 1 ELSE 0 END)/COUNT(*))*100,1) AS blockedfilespercent,
  SUM(CASE WHEN otherinfected>0 THEN 1 ELSE 0 END) AS otherinfected,
  ROUND((SUM(CASE WHEN otherinfected>0 THEN 1 ELSE 0 END)/COUNT(*))*100,1) AS otherinfectedpercent,
  SUM(CASE WHEN isspam>0 THEN 1 ELSE 0 END) AS spam,
  ROUND((SUM(CASE WHEN isspam>0 THEN 1 ELSE 0 END)/COUNT(*))*100,1) AS spampercent,
  SUM(CASE WHEN ishighspam>0 THEN 1 ELSE 0 END) AS highspam,
  ROUND((SUM(CASE WHEN ishighspam>0 THEN 1 ELSE 0 END)/COUNT(*))*100,1) AS highspampercent,
  SUM(size) AS size 
 FROM
  maillog
 WHERE
  date = CURRENT_DATE()
  and to_address='$luser'
"
        );

    while ($row = mysql_fetch_object($sth)) {
        echo "<TABLE BORDER=0 CLASS=\"mail\" WIDTH=200>\n";
        echo " <THEAD><TH ALIGN=\"CENTER\" COLSPAN=3>Today's Totals for $luser</TH></THEAD>\n";
        echo " <TR><TD>Processed:</TD><TD ALIGN=\"RIGHT\">" . $row->processed . "</TD><TD ALIGN=\"RIGHT\">" . format_mail_size(
                    $row->size
                ) . "</TD></TR>\n";
        echo " <TR><TD>Spam:</TD><TD ALIGN=\"RIGHT\">$row->spam</TD><TD ALIGN=\"RIGHT\">$row->spampercent%</TD></TR>\n";
        echo " <TR><TD>High Scoring Spam:</TD><TD ALIGN=\"RIGHT\">$row->highspam</TD><TD ALIGN=\"RIGHT\">$row->highspampercent%</TD></TR>\n";
        echo " <TR><TD>Virii:</TD><TD ALIGN=\"RIGHT\">$row->virii</TD><TD ALIGN=\"RIGHT\">$row->viriipercent%</TR>\n";
        echo " <TR><TD>Top Virus:</TD><TD COLSPAN=\"3\" ALIGN=\"RIGHT\">" . return_todays_top_virus(
                ) . "</TD></TR>\n";
        echo " <TR><TD>Blocked files:</TD><TD ALIGN=\"RIGHT\">$row->blockedfiles</TD><TD ALIGN=\"RIGHT\">$row->blockedfilespercent%</TD></TR>\n";
        echo " <TR><TD>Others:</TD><TD ALIGN=\"RIGHT\">$row->otherinfected</TD><TD ALIGN=\"RIGHT\">$row->otherinfectedpercent%</TD></TR>\n";
        echo "</TABLE>\n";
    }
    ?>
        </TD>
    </TR>
    <TR>
        <TD COLSPAN=<?php echo(MAILQ ? 3 : 2);
    ?>>
            <TABLE CLASS="navigation" WIDTH=100%>
                <TR ALIGN=CENTER>
                    <TD><A HREF="luser_login.php?reqtype=logout">Log Out</A></TD>
                    <TD WIDTH=20%><A HREF="docs.php">Documentation</A></TD>
                </TR>
            </TABLE>
        </TD>
    </TR>
    <TR>
        <TD COLSPAN=3>
    <?php
    return $refresh;
}
