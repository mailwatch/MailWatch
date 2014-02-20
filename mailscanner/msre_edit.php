<?php
// $Id: msre_edit.php,v 1.9 2005/06/14 20:29:21 jofcore Exp $
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

// Authentication checking
session_start();
require('login.function.php');

// Check to see if the user is an administrator
if ($_SESSION['user_type'] != 'A') {
    // If the user isn't an administrator send them back to the index page.
    header("Location: index.php");
    audit_log('Non-admin user attempted to view MailScanner Rule Editor Page');
} else {
    // add the header information such as the logo, search, menu, ....
    $pageheader = "Edit MailScanner Ruleset " . $_GET["file"];
    $filter = html_start($pageheader, 0, false, false);

    // ############################
    // ### Includes and whatnot ###
    // ############################
    include("msre_table_functions.php");

    // ###############################################################
    // ### Config Vars (eventually these will be in a config file) ###
    // ###############################################################

    //ruleset keywords are the key words that you can use in a ruleset.  This value is used to populate the dropdown boxen.
    $CONF_ruleset_keyword = array(
        "From:",
        "To:",
        "FromOrTo:",
        "Virus:"
    );

    $CONF_ruleset_keyword2 = array(
        "From:",
        "To:",
        "FromOrTo:"
    );

    define('MSRE_COLUMNS', 6);

    // ############
    // ### Main ###
    // ############

    // get the filename and put it into some easier to read variables
    $short_filename = $_GET["file"];
    $full_filename = MSRE_RULESET_DIR . "/" . $short_filename;

    // read the file into a variable, so that each function doesn't
    // need to do it themselves
    $file_contents = Read_File($full_filename, filesize($full_filename));
    // this will get populated later by a function (I need it to find
    // the end of the comments @ the top of the file)

    // check to see if the form was submitted, and if so process it..
    if (isset($_POST["submitted"])) {
        list($bytes_written, $status_message) = Process_Form();
        // re-read the file after processing
        $file_contents = Read_File($full_filename, $bytes_written);
    }

    // the form always gets displayed, even if it was submitted, so
    // display the form now
    Show_Form($status_message);
    // clear status message
    $status_message = "";

    echo "</table><tr><td>\n";
    html_end();
}

// #################
// ### Functions ###
// #################


function Show_Form($status_msg)
{
    // displays the form
    //
    // inputs:
    //		status_msg		if there's anything in the status_msg variable,
    //						it'll be printed below the header when the form
    //						is displayed
    //
    // ouput:
    //		displays the form
    //

    include("msre_function_global_vars.php");

    // display top of page stuff
    echo "<table border=\"0\" class=\"mailwatch\" align=\"center\">\n";
    echo "<form method=\"post\" name=\"MSRE_edit\" action=\"msre_edit.php?file=$short_filename\">\n";
    echo "<input type=\"hidden\" name=\"submitted\" value=\"1\">\n";
    // check for status message, and append it to the end of the header

    $my_header = '';
    if ($status_msg) {
        $my_header .= "<br>\n" . $status_msg;
    }
    // show page header
    if ($my_header) {
        TR_Single($my_header, "colspan=\"" . MSRE_COLUMNS . "\" class=\"header\"");
    }

    // write out the table header(s)
    TRH_Single("Current contents of <b>$short_filename</b>:", "colspan=\"" . MSRE_COLUMNS . "\"");
    // display the file contents
    TR_Single("<pre>$file_contents</pre>", "colspan=\"" . MSRE_COLUMNS . "\"");

    // now grab any lines in the file that aren't comments.
    $ruleset = array();
    $previous_line = "";
    foreach (preg_split("/\n/", $file_contents) as $line) {
        //echo "$i: $line<br>\n";
        //$i++;
        // this should find lines w/out comments, or lines that
        // start with #DISABLED#.
        // Treat empty lines as comments
        if ($line == "") {
            $line = "#";
        }
        if ((substr($line,0,1) != "#")
            or preg_match("/^#DISABLED#/", $line) ) {
            // check for a description on the previous line
	    if (substr($previous_line,0,1) == "#" ) {
                $desc = $previous_line;
            } else {
                $desc = "";
            }
            $ruleset[] = array($desc, $line);
        }
        $previous_line = $line;
    }

    // okay, now display it again, but in a format that the user can edit
    TRH_Single("Edit Ruleset:", "colspan=\"" . MSRE_COLUMNS . "\"");
    $rule_count = 0;
    foreach ($ruleset as $ruleanddesc) {
        $desc = $ruleanddesc[0];
        $rule = $ruleanddesc[1];
        // get rid of the # on the front of desc
        $desc = substr($desc, 1);
        // need to split the rule on tabs and spaces...
        $rule_part = preg_split("/[\t\s]/", $rule);
        // now I have to get rid of any blank elements, which would be
        // created if there are multiple tabs or spaces in the line
        $old_rule_part = $rule_part;
        $rule_part = array();
        foreach ($old_rule_part as $current_part) {
            if ($current_part != "") {
                $rule_part[] = $current_part;
            }
        }

        // The format for the rules is (pretty much) known, so I should
        // be able to pull something out of it...
        // First of all, the last element will always be the action
        // that is to be taken.  We need to get that right away, since
        // different rules can have different parts....
        $old_rule_part = $rule_part;
        $rule_part = array();
        $rule_part["99action"] = array_pop($old_rule_part);
        // now I should be able to assign the other parts names as well
        list (
            $rule_part["0direction"],
            $rule_part["1target"],
            $rule_part["2and"],
            $rule_part["3and_direction"],
            $rule_part["4and_target"]
            ) = $old_rule_part;

        // I need to check
        // for "missing pieces" of the rule, that may
        // exist in the old_rule_part array in between
        // 4and_target and 99action...
        // if there are missing pieces, the last element
        // of old_rule_part won't match 4and_target.  In that
        // case, we need to tack them onto the front of 99action
        // until we find the matching 4and_target.. I think.
        // Hmm, also need to stop if it matches 1target...
        // I think the only time this "situation" will occur
        // is if there isn't an "and", and there are multiple actions.
        // Not positive, but I'll know more as I test...
        $last_old_rule_part = array_pop($old_rule_part);
        // need two differnt while loops I think, based on
        // if there was an and or not..
        if (strtolower($rule_part["2and"]) == "and") {
            // if there's an and, grab up to the 4and_target
            $grab_to_field = "4and_target";
        } else {
            // if it's not an and, that means it's either blank, or
            // improperly parsed... in both cases, we should then
            // grab up to the 1target field
            $grab_to_field = "1target";
        }
        // now grab shit.
        while ($last_old_rule_part != $rule_part[$grab_to_field]) {
            //echo "lorp$rule_count: $last_old_rule_part<br>\n";
            $rule_part["99action"] = $last_old_rule_part . " " . $rule_part["99action"];
            $last_old_rule_part = array_pop($old_rule_part);
        }

        // ok, now that we've taken care of any "missing pieces",
        // we need to clear out the bogus data that was in the fields
        // leading up to the action... but only if the rule
        // isn't supposed to have an "and".  w/an "and", it already
        // has the proper data thx to the above code
        if (strtolower($rule_part["2and"]) != "and"
            and $rule_part["2and"]
        ) {
            // clean shit out
            $rule_part["2and"] = null;
            $rule_part["3and_direction"] = null;
            $rule_part["4and_target"] = null;
        }

        // now create the rule text
        // sort by keys first
        ksort($rule_part);
        $rule_text = array();
        // description line (and action select box)
        $rule_action_select = "rule" . $rule_count . "_rule_action";
        // need to create the select box now too, but the options
        // that are available to us depend on if the rule is
        // disabled or not
        // To find out if the rule is disabled, we look @ the
        // direction field for #DISABLED# on the beginning
        $desc_value = htmlentities($desc, ENT_QUOTES);
        if (preg_match("/#DISABLED#/", $rule_part["0direction"])) {
            $rule_disabled = 1;
            $rule_action_select_options = "<option value=\"Enable\">Enable</option>\n";
            $disable_desc_text = " disabled ";
            $desc_field = "rule" . $rule_count . "_description_disabled";
            $hidden_field_code = "<input type=\"hidden\" name=\"" .
                preg_replace("/_disabled$/", "", $desc_field) . "\" value=\"$desc_value\">";
        } else {
            $rule_disabled = 0;
            $rule_action_select_options = "<option value=\"Disable\">Disable</option>\n";
            $disable_desc_text = "";
            $desc_field = "rule" . $rule_count . "_description";
            $hidden_field_code = "";
        }
        $rule_action_select_html = "<select name=\"$rule_action_select\"";
        // if this is the default rule, the select box is disabled,
        // because you can't delete, disable, or enable the default
        // rule, only change it.  Originally I had it not there at
        // all, but w/the new way i'm writing the rules (w/the border),
        // each one is in a seperate table, and they don't line up
        // w/out the select box...
        if (strtolower($rule_part["1target"] == "default")) {
            $rule_action_select_html .= " disabled";
        }
        // now continue on..
        $rule_action_select_html .= ">\n" .
            "  <option value=\"\" selected>----</option>\n" .
            "  <option value=\"Delete\">Delete</option>\n" .
            $rule_action_select_options . "</select>\n";
        $desc_text = array(
            $rule_action_select_html => "rowspan=\"3\"",
            "<b>Description:</b>&nbsp;&nbsp;<input type=\"text\" " .
            "name=\"$desc_field\" size=\"95\" value=\"" . $desc_value . "\"" .
            $disable_desc_text . ">" . $hidden_field_code
            => "colspan=\"" . (MSRE_COLUMNS - 1) . "\""
        );

        foreach ($rule_part as $key => $value) {
            $part_name = "rule" . $rule_count . "_" . preg_replace("/\d/", "", $key);
            // depending on what part it is, we may need to do something
            // special, like create a select or checkbox
            switch (strtolower($key)) {
                case "2and":
                    // and gets a checkbox
                    $field_name = $part_name;
                    if ($rule_disabled) {
                        $field_name .= "_disabled";
                    }
                    $checkbox_html = "<input type=\"checkbox\" name=\"$field_name\" value=\"";
                    $checkbox_html .= "and";
                    $checkbox_html .= "\"";
                    if ($value) {
                        $checkbox_html .= " checked";
                    }
                    if ($rule_disabled) {
                        $checkbox_html .= " disabled ";
                    }
                    $checkbox_html .= ">and";
                    if ($rule_disabled) {
                        $checkbox_html .= "\n<input type=\"hidden\" name=\"$part_name\" value=\"";
                        if ($value) {
                            $checkbox_html .= "and";
                        }
                        $checkbox_html .= "\">\n";
                    }
                    $rule_text[] = $checkbox_html;
                    break;
                case "0direction":
                case "3and_direction":
                    // these get select boxen
                    $field_name = $part_name;
                    if ($rule_disabled) {
                        $field_name .= "_disabled";
                    }
                    $select_html = "<select name=\"$field_name\"";
                    if ($rule_disabled) {
                        $select_html .= " disabled ";
                    }
                    $select_html .= ">\n" .
                        "<option value=\"\"></option>";
                    if (strtolower($key) == "0direction") {
                        foreach ($CONF_ruleset_keyword as $current_kw) {
                            $select_html .= "<option value=\"$current_kw\"";
                            if (strtolower($current_kw) == strtolower(preg_replace("/#DISABLED#/", "", $value))) {
                                $select_html .= " selected";
                            }
                            $select_html .= ">$current_kw</option>";
                        }
                    } else {
                        foreach ($CONF_ruleset_keyword2 as $current_kw) {
                            $select_html .= "<option value=\"$current_kw\"";
                            if (strtolower($current_kw) == strtolower(preg_replace("/#DISABLED#/", "", $value))) {
                                $select_html .= " selected";
                            }
                            $select_html .= ">$current_kw</option>";
                        }
                    }
                    // need to close my select tag..
                    $select_html .= "</select>";

                    if ($rule_disabled) {
                        $select_html .= "\n<input type=\"hidden\" name=\"$part_name\" value=\"$value\">\n";
                    }
                    $rule_text[] = $select_html;
                    break;
                default:
                    // others get regular text boxen
                    $field_name = $part_name;
                    if ($rule_disabled) {
                        $field_name .= "_disabled";
                    }
                    if (strtolower($key) == "99action") {
                        $temp_text = "</td></tr><tr><td colspan=\"" . (MSRE_COLUMNS - 1) . "\"><b>Action:</b>&nbsp;&nbsp;<input type=\"text\" name=\"$field_name\" value=\"$value\" size=\"100\"";
                    } else {
                        $temp_text = "<input type=\"text\" name=\"$field_name\" value=\"$value\"";
                    }
                    if ($rule_disabled || (strtolower($key) == "1target" && strtolower($value) == "default")) {
                        $temp_text .= " disabled ";
                    }
                    $temp_text .= ">";
                    if ($rule_disabled) {
                        $temp_text .= "\n<input type=\"hidden\" name=\"$part_name\" value=\"$value\">\n";
                    }
                    $rule_text [] = $temp_text;
                    break;
            }
        }
        if ($colorpicker) {
            //echo "colorpicker!<br>\n";
            $tr_param = " class=\"alt\"";
            $colorpicker = 0;
            $boxclass = "dashblackbox";
        } else {
            //echo "colorpicker 0<br>\n";
            $tr_param = "";
            $colorpicker = 1;
            $boxclass = "dashgreybox";
        }
        // something new for v0.2.1 ... I'm going to try to put a box
        // around each ruleset, so it's easier to pick them out from
        // each other in the list...
        echo "<tr>\n" .
            "<td class=\"$boxclass\" colspan=\"" . MSRE_COLUMNS . "\">\n" .
            "<table border=\"0\">\n";
        TR_Extended($desc_text, $tr_param);
        TR($rule_text, $tr_param);
        echo "</table>\n" .
            "</td>\n" .
            "</tr>\n";
        // and a blank space too to break them up a li'l more
        //echo "<tr><td colspan=\"" . MSRE_COLUMNS . "\" bgcolor=\"white\">&nbsp;</td></tr>\n";

        $rule_count++;
    }

    // write the rule count as a hidden field, so that I have it
    // for submit procesing
    echo "<input type=\"hidden\" name=\"rule_count\" value=\"$rule_count\">\n";

    // now put a blank one on the bottom, so the user can add a new one
    $add_rule_text = array();
    $add_prefix = "rule" . $rule_count . "_";
    // description
    $desc_text = array(
        "" => "rowspan=\"3\"",
        "<b>Description:</b>&nbsp;&nbsp;<input type=\"text\" name=\"" .
        $add_prefix . "description\" value=\"\" size=\"95\">" =>
            "colspan=\"" . (MSRE_COLUMNS - 1) . "\""
    );
    // direction
    $temp_html = "<b>Condition:</b>&nbsp;&nbsp;<select name=\"" . $add_prefix .
        "direction\"><option value=\"\"></option>";
    foreach ($CONF_ruleset_keyword as $kw) {
        $temp_html .= "<option value=\"$kw\">$kw</option>";
    }
    $temp_html .= "</select>\n";
    $add_rule_text[] = $temp_html;
    // target
    $add_rule_text[] = "<input type=\"text\" name=\"" . $add_prefix .
        "target\" value=\"\">";
    // and
    $add_rule_text[] = "<input type=\"checkbox\" name=\"" . $add_prefix .
        "and\" value=\"and\">and";
    // and direction
    $temp_html = "<select name=\"" . $add_prefix .
        "and_direction\"><option value=\"\"></option>";
    foreach ($CONF_ruleset_keyword as $kw) {
        $temp_html .= "<option value=\"$kw\">$kw</option>";
    }
    $temp_html .= "</select>\n";
    $add_rule_text[] = $temp_html;
    // and target
    $add_rule_text[] = "<input type=\"text\" name=\"" . $add_prefix .
        "and_target\" value=\"\">";
    // action
    $add_rule_text[] = "</td></tr><tr><td colspan=\"" . (MSRE_COLUMNS - 1) . "\"><b>Action:</b>&nbsp;&nbsp;<input type=\"text\" name=\"" .
        $add_prefix . "action\" value=\"\" size=\"100\">";

    // now write it
    TRH_Single("Add New Rule:", "colspan=\"" . MSRE_COLUMNS . "\"");
    TR_Extended($desc_text, "");
    TR($add_rule_text);

    // need to put a submit button on the bottom
    TRH_Single("<input type=\"submit\" name=\"submit\" value=\"Save Changes\">", "colspan=\"" . MSRE_COLUMNS . "\"");

    // finally, display page footer
    TR_Single(
        "<a href=\"msre_index.php\">Back to MSRE ruleset index</a><br>\n<a href=\"/mailscanner/other.php\">Back to MailWatch</a><br>\n",
        "colspan=\"" . MSRE_COLUMNS . "\" class=\"footer\""
    );
    // that's it
    return;
}


function Process_Form()
{
    // Processes the form, writes the updated file
    //
    // returns the number of bytes written and status messages, which it
    // gets from Write_File
    //

    include("msre_function_global_vars.php");

    $new_file = array();
    $bytes = 0;
    $status_msg = "";

    // Debugging... this displays all my post vars for me
    /*
    echo "<span class=\"debug\">\n";
    echo "POST vars:<br>\n";
    foreach ($_POST as $key => $value) {
        echo "$key: $value<br>\n";
    }
    echo "</span>\n";
    */

    // mmkay... what we'll want to do here is write out
    // a new file with the updated rules that the user has
    // just saved.  Rather than trying to edit the file every
    // time, I'm just going to overwrite it each time.
    // But that means that I need to keep comments on the top...

    // look thru the file, and grab comments on the top,
    // stopping when we have reached a non-comment line
    $previous_line = "";
    $first_line = true;
    foreach (preg_split("/\n/", $file_contents) as $line) {
        if ($line == "" or (substr($line, 0, 1) == "#"
                and !preg_match("/#DISABLED#/", $line)) ) {
	    if (!$first_line) {
	        $new_file[] = $previous_line . "\n";
            }
        } else {
            break;
        }
        $previous_line = $line;
	$first_line = false;
    }

    // to make my life easier (or possibly harder), I'm going
    // to re-arrange the rule varibles from the _POST var
    // into a single multi-dimensional array that will hold
    // all the info i need for the rules.
    $new_ruleset = array();
    // I should know the number of rules I have... right?
    // we do <= so that we can check for the add rule thingy,
    // which will end up being on the end of the ruleset
    // Also, we will be pulling out the "default" rule, if
    // it exists, because we want to tack that back onto
    // the end of the ruleset when we're done (default should
    // stay @ the bottom)
    $default_direction = "FromOrTo:";
    $default_action = "";
    $default_desc = "";
    for ($i = -1; $i <= $_POST["rule_count"]; $i++) {
        $rule_prefix = "rule" . $i . "_";
        $description = $rule_prefix . "description";
        $direction = $rule_prefix . "direction";
        $target = $rule_prefix . "target";
        $and = $rule_prefix . "and";
        $and_direction = $rule_prefix . "and_direction";
        $and_target = $rule_prefix . "and_target";
        $action = $rule_prefix . "action";
        $rule_action = $rule_prefix . "rule_action";
        // check for "default" rule
        if (!isset($_POST[$target])) {
            $_POST[$target] = "default";
        }
        // we need to remove any "magic quoting" from the description, target,
        // and action fields, so that it doesn't put it into the file
        $_POST[$description] = Fix_Quotes($_POST[$description]);
        //echo "$description: " . $_POST[$description] . "<br>\n";
        $_POST[$target] = Fix_Quotes($_POST[$target]);
        $_POST[$and_target] = Fix_Quotes($_POST[$and_target]);
        $_POST[$action] = Fix_Quotes($_POST[$action]);

        if (strtolower($_POST[$target]) == "default") {
            // Default 'direction' can only be "Virus:" or "FromOrTo:"
            if ($_POST[$direction] == "Virus:") {
                $default_direction = "Virus:";
            } else {
                $default_direction = "FromOrTo:";
            }
            $default_action = $_POST[$action];
            $default_desc = $_POST[$description];
            continue;
        }

        // check to see if any rule action was specified, like delete,
        // disable, enable.
        // If so, we need to do something here..
        //echo "$rule_action: |" . $_POST[$rule_action] . "|<br>\n";
        switch ($_POST[$rule_action]) {
            case "Delete":
                // deletions are simple, just ignore this rule and
                // go to the next one (and it won't get written to
                // the new file)
                //echo "rule$i: $rule_action says delete<br>\n";
                continue 2;
                break;
            case "Disable":
                // to disable a rule, we simply add "#DISABLED" to the
                // beginning of the direction field,
                // which will end up being the first thing on the line
                $_POST[$direction] = "#DISABLED#" . $_POST[$direction];
                break;
            case "Enable":
                // enable is the opposite of disable..
                $_POST[$direction] = preg_replace("/^#DISABLED#/", "", $_POST[$direction]);
                break;
        }

        //echo "after case, rule $i<br>\n";
        // make sure there's something there... direction is required
        if (!isset($_POST[$and])) {
            $_POST[$and] = "";
            $_POST[$and_direction] = "";
            $_POST[$and_target] = "";
        }

        if ($_POST[$direction]) {
            //echo "$direction: $_POST[$direction]<br>\n";
            $new_ruleset[] = array(
                "description" => $_POST[$description],
                "direction" => $_POST[$direction],
                "target" => $_POST[$target],
                "and" => $_POST[$and],
                "and_direction" => $_POST[$and_direction],
                "and_target" => $_POST[$and_target],
                "action" => $_POST[$action]
            );
        }
    }

    // ok, at this point I think we can finish assembling the new file
    foreach ($new_ruleset as $new_rule) {
        $new_file [] =
            "#" . $new_rule["description"] . "\n" .
            $new_rule["direction"] . "\t" .
            $new_rule["target"] . "\t" .
            $new_rule["and"] . "\t" .
            $new_rule["and_direction"] . "\t" .
            $new_rule["and_target"] . "\t" .
            $new_rule["action"] . "\n";
    }
    // and add on the default rule if there is one.
    if ($default_action != "") {
        $new_file[] = "#" . $default_desc . "\n";
        $new_file[] = "$default_direction\tdefault\t\t\t$default_action\n";
    }

    // ### ---> Debugging
    /*
    echo "<span class=\"debug\">\n";
    echo "new file:<br>\n";
    echo "<pre>";
    foreach ($new_file as $line) {
        echo $line;
    }
    echo "</pre>\n";

    echo "</span>\n";
    */

    // mmmkay, now we should be able to write the new file
    $filename = MSRE_RULESET_DIR . "/" . $_GET["file"];
    list ($bytes, $status_msg) = Write_File($filename, $new_file);

    // schedule a reload of mailscanner's stuff. We can't do an immediate
    // reload w/out giving the apache user rights to run the MailScanner
    // startup/reload script, and that could be a bad idea...
    //So instead, I schedule a reload with the msre_reload.cron cron job
    $status_msg .= "<span class=\"status\">\n";
    $status_msg .= "Scheduling reload of MailScanner...";
    $fh = fopen("/tmp/msre_reload", "w");
    // we don't need to write to the file, just it existing is enough
    if (!$fh) {
        $status_msg .= "<span class=\"error\">**ERROR** Couldn't schedule a reload of " .
            "MailScanner!  (You will have to manually do a " .
            "|/etc/init.d/MailScanner reload| )</span><br>\n";
    } else {
        $status_msg .= "Ok.<br>\n" .
            "Your changes will take effect in the next " .
            MSRE_RELOAD_INTERVAL . " minutes, when MailScanner reloads.<br>\n";
    }
    $status_msg .= "</span>\n";

    $returnvalue = array($bytes, $status_msg);
    return ($returnvalue);
}


function Read_File($filename, $size)
{
    // reads $filename up to $size bytes, and returns what it contains
    include("msre_function_global_vars.php");

    $returnvalue = "";

    // read contents of file
    $fh = fopen($filename, "r");
    // read contents into string
    $returnvalue = fread($fh, $size);
    // close file
    fclose($fh);

    return ($returnvalue);
}


function Write_File($filename, $content)
{
    // writes a file to $filename (which must include the full path!)
    // and fills it with $content (array)
    //
    // returns the number of bytes written and status messages

    include("msre_function_global_vars.php");

    // return the number of bytes written
    $bytes = 0;
    $status_msg = "";

    // we will print some status messages as we're doing it..
    $status_msg .= "<span class=\"status\">\n";
    // make a backup copy of the file first, in case anything goes wrong..
    $status_msg .= "Backing up current file...";
    $backup_name = $filename . ".bak";
    if (!copy($filename, $backup_name)) {
        $status_msg .= "<span class=\"error\">**ERROR** Could not make backup!</span><br>\n";
    } else {
        $status_msg .= "Ok.<br>\n";
        // now open the file for writing
        $status_msg .= "Opening $filename for writing...";
        $fh = fopen($filename, "w");
        if (!$fh) {
            $status_msg .= "<span class=\"error\">**ERROR** Couldn't open $filename for write!</span><br>\n";
        } else {
            $status_msg .= "Ok.<br>\n";
            // write contents
            $status_msg .= "Writing new file...";
            foreach ($content as $line) {
                $bytes += fwrite($fh, $line);
            }
            $status_msg .= " wrote $bytes bytes.<br>\n";
            // close file
            fclose($fh);
            $status_msg .= "File closed.<br>\n";
        }
    }

    $status_msg .= "Done with Write_File.<br>\n";
    $status_msg .= "</span>\n";

    $returnvalue = array($bytes, $status_msg);
    return ($returnvalue);
}


function Fix_Quotes($stuff)
{
    // gets rid of any backslashed quotes in the stuff given to it.
    // also gets rid of any multiple backslashes...

    $stuff = str_replace("\\\\", "\\", $stuff);
    $stuff = str_replace("\\'", "'", $stuff);
    $stuff = str_replace('\"', '"', $stuff);
    return ($stuff);
}
