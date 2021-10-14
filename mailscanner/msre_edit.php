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

// Include of necessary functions
require_once __DIR__ . '/functions.php';

// Authentication checking
require __DIR__ . '/login.function.php';

// Check to see if the user is an administrator
if ($_SESSION['user_type'] !== 'A') {
    // If the user isn't an administrator send them back to the index page.
    header('Location: index.php');
    audit_log(__('auditlog55', true));
} else {
    if (isset($_POST['token'])) {
        if (false === checkToken($_POST['token'])) {
            header('Location: login.php?error=pagetimeout');
            die();
        }
    } else {
        if (false === checkToken($_GET['token'])) {
            header('Location: login.php?error=pagetimeout');
            die();
        }
    }

    // Add the header information such as the logo, search, menu, ....
    if (isset($_POST['file'])) {
        $short_filename = deepSanitizeInput($_POST['file'], 'url');
    } else {
        $short_filename = deepSanitizeInput($_GET['file'], 'url');
    }
    if (!validateInput($short_filename, 'file')) {
        die(__('dievalidate99'));
    }
    $short_filename = basename($short_filename);
    $pageheader = __('msreedit55') . ' ' . $short_filename;
    $filter = html_start($pageheader, 0, false, false);

    // Includes and whatnot
    include __DIR__ . '/msre_table_functions.php';

    // Config Vars (eventually these will be in a config file)

    // Ruleset keywords are the key words that you can use in a ruleset.
    // This value is used to populate the dropdown boxen.
    $CONF_ruleset_keyword = [
        'From:',
        'To:',
        'FromOrTo:',
        'FromAndTo:',
        'Virus:'
    ];

    define('MSRE_COLUMNS', 6);

    // Get the filename and put it into some easier to read variables.
    $full_filename = MSRE_RULESET_DIR . '/' . $short_filename;

    if (!file_exists($full_filename)) {
        die(__('diefnf55') . ' ' . $full_filename);
    }

    // Read the file into a variable, so that each function doesn't
    // need to do it themselves.
    $file_contents = Read_File($full_filename, filesize($full_filename));
    
    // This will get populated later by a function (I need it to find
    // the end of the comments @ the top of the file)

    // Check to see if the form was submitted, and if so process it.
    $status_message = '';
    if (isset($_POST['submitted'])) {
        if (false === checkFormToken('/msre_edit.php form token', $_POST['formtoken'])) {
            header('Location: login.php?error=pagetimeout');
            die();
        }

        list($bytes_written, $status_message) = Process_Form($file_contents, $short_filename);
        // Re-read the file after processing
        $file_contents = Read_File($full_filename, $bytes_written);
    }

    // The form always gets displayed, even if it was submitted, so
    // display the form now
    Show_Form($status_message, $short_filename, $file_contents, $CONF_ruleset_keyword);
    
    // Clear status message
    $status_message = '';
    echo '</table><tr><td>' . "\n";
    html_end();
}

// Functions

/**
 * @param string status_msg
 * @return displays the form
 */
function Show_Form($status_msg, $short_filename, $file_contents, $CONF_ruleset_keyword)
{
    // Display top of page stuff
    echo '<table border="0" class="mailwatch" align="center">' . "\n";
    echo '<form method="post" name="msre_edit" action="msre_edit.php">' . "\n";
    echo '<input type="hidden" name="file" value="' . $short_filename . '">' . "\n";
    echo '<input type="hidden" name="token" value="' . $_SESSION['token'] . '">' . "\n";
    echo '<input type="hidden" name="formtoken" value="' . generateformtoken('/msre_edit.php form token') . '">' . "\n";
    echo '<input type="hidden" name="submitted" value="1">' . "\n";
    
    // Check for status message, and append it to the end of the header
    $my_header = '';
    if ($status_msg) {
        $my_header .= '<br>' . "\n" . $status_msg;
    }
    
    // Show page header
    if ($my_header) {
        TR_Single($my_header, 'colspan="' . MSRE_COLUMNS . '" class="header"');
    }

    // Write out the table header(s)
    TRH_Single(sprintf(__('contentsof55'), $short_filename), 'colspan="' . MSRE_COLUMNS . '"');
    
    // Display the file contents
    TR_Single("<pre>$file_contents</pre>", 'colspan="' . MSRE_COLUMNS . '"');

    // Now grab any lines in the file that aren't comments.
    $ruleset = [];
    $previous_line = '';
    foreach (preg_split("/\n/", $file_contents) as $line) {
        //echo "$i: $line<br>\n";
        //$i++;
        // this should find lines w/out comments, or lines that
        // start with #DISABLED#.
        // Treat empty lines as comments
        if ($line === '') {
            $line = '#';
        }
        if ((substr($line, 0, 1) !== '#')
            || preg_match('/^#DISABLED#/', $line)
        ) {
            // Check for a description on the previous line
            $desc = '';
            if (substr($previous_line, 0, 1) === '#') {
                $desc = $previous_line;
            }
            $ruleset[] = [$desc, $line];
        }
        $previous_line = $line;
    }

    // Okay, now display it again, but in a format that the user can edit.
    TRH_Single(__('editrules55'), 'colspan="' . MSRE_COLUMNS . '"');
    $colorpicker = 0;
    $rule_count = 0;
    foreach ($ruleset as $ruleanddesc) {
        $desc = $ruleanddesc[0];
        $rule = $ruleanddesc[1];
        // Get rid of the # on the front of description
        $desc = substr($desc, 1);
        // Need to split the rule on tabs and spaces
        $rule_part = preg_split("/[\t\s]/", $rule);
        // Now I have to get rid of any blank elements, which would be
        // created if there are multiple tabs or spaces in the line
        $old_rule_part = $rule_part;
        $rule_part = [];
        foreach ($old_rule_part as $current_part) {
            if ($current_part !== '') {
                $rule_part[] = $current_part;
            }
        }

        // The format for the rules is (pretty much) known, so I should
        // be able to pull something out of it.
        // First of all, the last element will always be the action
        // that is to be taken.  We need to get that right away, since
        // different rules can have different parts.
        $old_rule_part = $rule_part;
        $rule_part = [];
        $rule_part['99action'] = array_pop($old_rule_part);
        // Now I should be able to assign the other parts names as well
        // if fewer than 5 parts to rule, fill other parts with NULL
        // too many don't matter, so push 4 NULLs anyway
        if (count($old_rule_part) < 5) {
            array_push($old_rule_part, null, null, null, null);
        }
        list(
            $rule_part['0direction'],
            $rule_part['1target'],
            $rule_part['2and'],
            $rule_part['3and_direction'],
            $rule_part['4and_target']
            ) = $old_rule_part;

        // Clean out whitespace from the rule parts
        foreach ($rule_part as &$a_part) {
            trim($a_part);
        }
        // I need to check
        // for "missing pieces" of the rule, that may
        // exist in the old_rule_part array in between
        // 4and_target and 99action.
        // if there are missing pieces, the last element
        // of old_rule_part won't match 4and_target.  In that
        // case, we need to tack them onto the front of 99action
        // until we find the matching 4and_target.. I think.
        // Hmm, also need to stop if it matches 1target.
        // I think the only time this "situation" will occur
        // is if there isn't an "and", and there are multiple actions.
        // Not positive, but I'll know more as I test.
        $last_old_rule_part = array_pop($old_rule_part);
        // Need two differnt while loops I think, based on
        // if there was an and or not.
        if (strtolower($rule_part['2and']) === 'and') {
            // If there's an and, grab up to the 4and_target.
            $grab_to_field = '4and_target';
        } else {
            // If it's not an and, that means it's either blank, or
            // improperly parsed... in both cases, we should then
            // grab up to the 1target field.
            $grab_to_field = '1target';
        }

        // Now grab shit.
        while ($last_old_rule_part !== $rule_part[$grab_to_field]) {
            //echo "lorp$rule_count: $last_old_rule_part<br>\n";
            if ($last_old_rule_part !== null) {
                $rule_part['99action'] = $last_old_rule_part . ' ' . $rule_part['99action'];
            }
            $last_old_rule_part = array_pop($old_rule_part);
        }

        // Ok, now that we've taken care of any "missing pieces",
        // we need to clear out the bogus data that was in the fields
        // leading up to the action. But only if the rule
        // isn't supposed to have an "and".  w/an "and", it already
        // has the proper data thx to the above code.
        if ($rule_part['2and'] && strtolower($rule_part['2and']) !== 'and') {
            // clean shit out
            $rule_part['2and'] = null;
            $rule_part['3and_direction'] = null;
            $rule_part['4and_target'] = null;
        }

        // Now create the rule text
        // sort by keys first
        ksort($rule_part);
        $rule_text = [];
        
        // Description line (and action select box)
        $rule_action_select = 'rule' . $rule_count . '_rule_action';
        
        // Need to create the select box now too, but the options
        // that are available to us depend on if the rule is
        // disabled or not.
        // To find out if the rule is disabled, we look @ the
        // direction field for #DISABLED# on the beginning.
        $desc_value = htmlentities($desc, ENT_QUOTES);
        if (preg_match('/#DISABLED#/', $rule_part['0direction'])) {
            $rule_disabled = 1;
            $rule_action_select_options = '<option value="Enable">' . __('enable55') . '</option>' . "\n";
            $disable_desc_text = ' disabled ';
            $desc_field = 'rule' . $rule_count . '_description_disabled';
            $hidden_field_code = '<input type="hidden" name="' .
                preg_replace('/_disabled$/', '', $desc_field) . "\" value=\"$desc_value\">";
        } else {
            $rule_disabled = 0;
            $rule_action_select_options = '<option value="Disable">' . __('disable55') .'</option>' . "\n";
            $disable_desc_text = '';
            $desc_field = 'rule' . $rule_count . '_description';
            $hidden_field_code = '';
        }
        $rule_action_select_html = '<select name="' . $rule_action_select . '"';
        
        // If this is the default rule, the select box is disabled,
        // because you can't delete, disable, or enable the default
        // rule, only change it.  Originally I had it not there at
        // all, but w/the new way i'm writing the rules (w/the border),
        // each one is in a seperate table, and they don't line up
        // w/out the select box.
        if (strtolower($rule_part['1target'] === 'default')) {
            $rule_action_select_html .= ' disabled';
        }
        
        // Now continue on.
        $rule_action_select_html .= '>' . "\n" .
            '  <option value="" selected>----</option>' . "\n" .
            '  <option value="Delete">' . __('delete55') . '</option>' . "\n" .
            $rule_action_select_options . '</select>' . "\n";
        $desc_text = [
            $rule_action_select_html => 'rowspan="3"',
            '<b>' . __('description55') . '</b>&nbsp;&nbsp;<input type="text" ' .
            'name="' . $desc_field . '" size="95" value="' . $desc_value . '"' .
            $disable_desc_text . '>' . $hidden_field_code
            => 'colspan="' . (MSRE_COLUMNS - 1) . '"'
        ];

        foreach ($rule_part as $key => $value) {
            $part_name = 'rule' . $rule_count . '_' . preg_replace("/\d/", '', $key);
            // Depending on what part it is, we may need to do something
            // special, like create a select or checkbox.
            switch (strtolower($key)) {
                case '2and':
                    // And gets a checkbox
                    $field_name = $part_name;
                    if ($rule_disabled) {
                        $field_name .= '_disabled';
                    }
                    $checkbox_html = '<input type="checkbox" name="' . $field_name . '" value="';
                    $checkbox_html .= 'and';
                    $checkbox_html .= '"';
                    if ($value) {
                        $checkbox_html .= ' checked';
                    }
                    if ($rule_disabled) {
                        $checkbox_html .= ' disabled ';
                    }
                    $checkbox_html .= '> ' . __('and55');
                    if ($rule_disabled) {
                        $checkbox_html .= "\n" . '<input type="hidden" name="' . $part_name . '" value="';
                        if ($value) {
                            $checkbox_html .= 'and';
                        }
                        $checkbox_html .= '">' . "\n";
                    }
                    $rule_text[] = $checkbox_html;
                    break;
                case '0direction':
                case '3and_direction':
                    // These get select boxen
                    $field_name = $part_name;
                    if ($rule_disabled) {
                        $field_name .= '_disabled';
                    }
                    $select_html = '<select name="' . $field_name . '"';
                    if ($rule_disabled) {
                        $select_html .= ' disabled ';
                    }
                    $select_html .= '>' . "\n" . '<option value=""></option>';
                    foreach ($CONF_ruleset_keyword as $current_kw) {
                        $select_html .= '<option value="' . $current_kw . '"';
                        $match = strtolower(preg_replace('/#DISABLED#/', '', $value));
                        $kw = '';
                        // Use MailScanner's direction-matching rules
                        if (preg_match('/and/', $match)) {
                            $kw = 'fromandto:';
                        } elseif (preg_match('/from/', $match) && preg_match('/to/', $match)) {
                            $kw = 'fromorto:';
                        } elseif (preg_match('/from/', $match)) {
                            $kw = 'from:';
                        } elseif (preg_match('/to/', $match)) {
                            $kw = 'to:';
                        } elseif (preg_match('/virus/', $match)) {
                            $kw = 'virus:';
                        }
                        if (strtolower($current_kw) === $kw) {
                            $select_html .= ' selected';
                        }
                        $select_html .= '>' . $current_kw . '</option>';
                    }
                    // Need to close my select tag..
                    $select_html .= '</select>';

                    if ($rule_disabled) {
                        $select_html .= "\n" . '<input type="hidden" name="' . $part_name . '" value="' . $value . '">' . "\n";
                    }
                    $rule_text[] = $select_html;
                    break;
                default:
                    // Others get regular text boxen
                    $field_name = $part_name;
                    if ($rule_disabled) {
                        $field_name .= '_disabled';
                    }
                    if (strtolower($key) === '99action') {
                        $temp_text = '</td></tr><tr><td colspan="' . (MSRE_COLUMNS - 1) . '"><b>' . __('action55')
                        . '</b>&nbsp;&nbsp;<input type="text" name="' . $field_name . '" value="' . $value . '" size="100"';
                    } else {
                        $temp_text = '<input type="text" name="' . $field_name . '" value="' . $value . '"';
                    }
                    if ($rule_disabled || (strtolower($key) === '1target' && strtolower($value) === 'default')) {
                        $temp_text .= ' disabled ';
                    }
                    $temp_text .= '>';
                    if ($rule_disabled) {
                        $temp_text .= "\n" . '<input type="hidden" name="' . $part_name . '" value="' . $value . '">' . "\n";
                    }
                    $rule_text [] = $temp_text;
                    break;
            }
        }
        if ($colorpicker) {
            //echo "colorpicker 1<br>\n";
            $tr_param = ' class="alt"';
            $colorpicker = 0;
            $boxclass = 'dashblackbox';
        } else {
            //echo "colorpicker 0<br>\n";
            $tr_param = '';
            $colorpicker = 1;
            $boxclass = 'dashgreybox';
        }
        // Something new for v0.2.1. I'm going to try to put a box
        // around each ruleset, so it's easier to pick them out from
        // each other in the list.
        echo '<tr>' . "\n" .
            '<td class="' . $boxclass . '" colspan="' . MSRE_COLUMNS . '">' . "\n" . '<table border="0">' . "\n";
        TR_Extended($desc_text, $tr_param);
        TR($rule_text, $tr_param);
        echo '</table>' . "\n" .
            '</td>' . "\n" .
            '</tr>' . "\n";
            
        // And a blank space too to break them up a li'l more
        //echo "<tr><td colspan=\"" . MSRE_COLUMNS . "\" bgcolor=\"white\">&nbsp;</td></tr>\n";

        $rule_count++;
    }

    // Write the rule count as a hidden field, so that I have it
    // for submit procesing.
    echo '<input type="hidden" name="rule_count" value="' . $rule_count . '">' . "\n";

    // Now put a blank one on the bottom, so the user can add a new one.
    $add_rule_text = [];
    $add_prefix = 'rule' . $rule_count . '_';
    
    // Description
    $desc_text = [
        '' => 'rowspan="3"',
        '<b>' . __('description55') . '</b>&nbsp;&nbsp;<input type="text" name="' .
        $add_prefix . 'description" value="" size="95">' =>
            'colspan="' . (MSRE_COLUMNS - 1) . '"'
    ];
    
    // Direction
    $temp_html = '<b>' . __('conditions55') . '</b>&nbsp;&nbsp;<select name="' . $add_prefix .
        'direction"><option value=""></option>';
    foreach ($CONF_ruleset_keyword as $kw) {
        $temp_html .= '<option value="' . $kw . '">' . $kw . '</option>';
    }
    $temp_html .= '</select>' . "\n";
    $add_rule_text[] = $temp_html;
    
    // Target
    $add_rule_text[] = '<input type="text" name="' . $add_prefix .
        'target" value="">';
    $add_rule_text[] = '<input type="checkbox" name="' . $add_prefix .
        'and" value="and"> ' . __('and55');
    $temp_html = '<select name="' . $add_prefix .
        'and_direction"><option value=""></option>';
    foreach ($CONF_ruleset_keyword as $kw) {
        $temp_html .= '<option value="' . $kw . '">' . $kw . '</option>';
    }
    $temp_html .= '</select>' . "\n";
    $add_rule_text[] = $temp_html;
    
    // And target
    $add_rule_text[] = '<input type="text" name="' . $add_prefix .
        'and_target" value="">';
    $add_rule_text[] = '</td></tr><tr><td colspan="' . (MSRE_COLUMNS - 1) . '"><b>' . __('action55') . '</b>&nbsp;&nbsp;<input type="text" name="' .
        $add_prefix . 'action" value="" size="100">';

    // Now write it
    TRH_Single(__('newrule55'), 'colspan="' . MSRE_COLUMNS . '"');
    TR_Extended($desc_text, '');
    TR($add_rule_text);

    // Need to put a submit button on the bottom
    TRH_Single('<input type="submit" name="submit" value="' . __('savevalue55') . '">', 'colspan="' . MSRE_COLUMNS . '"');

    // Finally, display page footer
    TR_Single(
        '<a href="msre_index.php">' . __('backmsre55') . '</a><br>' . "\n" . '<a href="other.php">' . __('backmw55') . '</a><br>' . "\n",
        'colspan="' . MSRE_COLUMNS . '" class="footer"'
    );
}

function Process_Form($file_contents, $short_filename)
{
    // Processes the form, writes the updated file
    // returns the number of bytes written and status messages, which it
    // gets from Write_File

    $new_file = [];
    $bytes = 0;
    $status_msg = '';

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
    $previous_line = '';
    $first_line = true;
    foreach (preg_split("/\n/", $file_contents) as $line) {
        if ($line === '' ||
             (substr($line, 0, 1) === '#' && !preg_match('/#DISABLED#/', $line))

        ) {
            if (!$first_line) {
                $new_file[] = $previous_line . "\n";
            }
        } else {
            break;
        }
        $previous_line = $line;
        $first_line = false;
    }

    // To make my life easier (or possibly harder), I'm going
    // to re-arrange the rule varibles from the _POST var
    // into a single multi-dimensional array that will hold
    // all the info i need for the rules.
    $new_ruleset = [];
    // I should know the number of rules I have... right?
    // we do <= so that we can check for the add rule thingy,
    // which will end up being on the end of the ruleset
    // Also, we will be pulling out the "default" rule, if
    // it exists, because we want to tack that back onto
    // the end of the ruleset when we're done (default should
    // stay @ the bottom)
    $default_direction = 'FromOrTo:';
    $default_action = '';
    $default_desc = '';
    $count = deepSanitizeInput($_POST['rule_count'], 'num');
    if (!validateInput($count, 'num')) {
        die(__('dievalidate99'));
    }
    for ($i = -1; $i <= $count; $i++) {
        $rule_prefix = 'rule' . $i . '_';
        $description = $rule_prefix . 'description';
        $direction = $rule_prefix . 'direction';
        $target = $rule_prefix . 'target';
        $and = $rule_prefix . 'and';
        $and_direction = $rule_prefix . 'and_direction';
        $and_target = $rule_prefix . 'and_target';
        $action = $rule_prefix . 'action';
        $rule_action = $rule_prefix . 'rule_action';
        // we need to remove any "magic quoting" from the description, target,
        // and action fields, so that it doesn't put it into the file
        if (isset($_POST[$description])) {
            $_POST[$description] = Fix_Quotes($_POST[$description]);
        } else {
            $_POST[$description] = '';
        }
        //echo "$description: " . $_POST[$description] . "<br>\n";
        // Check for "default" rule
        if (isset($_POST[$target])) {
            $_POST[$target] = Fix_Quotes($_POST[$target]);
        } else {
            $_POST[$target] = 'default';
        }
        // Strip out any embedded blanks from Target
        $_POST[$target] = str_replace(' ', '', $_POST[$target]);

        if (!isset($_POST[$and_direction])) {
            $_POST[$and_direction] = '';
        }
        if (isset($_POST[$and_target])) {
            $_POST[$and_target] = Fix_Quotes($_POST[$and_target]);
        } else {
            $_POST[$and_target] = '';
        }
        // Strip out any embedded blanks from AndTarget
        $_POST[$and_target] = str_replace(' ', '', $_POST[$and_target]);

        if (isset($_POST[$action])) {
            $_POST[$action] = Fix_Quotes($_POST[$action]);
        } else {
            $_POST[$action] = '';
        }
        // On no account allow invalid rule
        // Target and Action must both have values
        // delete rule if they don't
        if ($_POST[$target] === '' || $_POST[$action] === '') {
            continue;
        }
        if (strtolower($_POST[$target]) === 'default') {
            // Default 'direction' can only be "Virus:" or "FromOrTo:"
            if ($_POST[$direction] === 'Virus:') {
                $default_direction = 'Virus:';
            } else {
                $default_direction = 'FromOrTo:';
            }
            $default_action = $_POST[$action];
            $default_desc = $_POST[$description];
            continue;
        }

        // Check to see if any rule action was specified, like delete,
        // disable, enable.
        // If so, we need to do something here.
        //echo "$rule_action: |" . $_POST[$rule_action] . "|<br>\n";
        if (isset($_POST[$rule_action])) {
            switch ($_POST[$rule_action]) {
                case 'Delete':
                    // Deletions are simple, just ignore this rule and
                    // go to the next one (and it won't get written to
                    // the new file)
                    //echo "rule$i: $rule_action says delete<br>\n";
                    continue 2;
                case 'Disable':
                    // To disable a rule, we simply add "#DISABLED" to the
                    // beginning of the direction field,
                    // which will end up being the first thing on the line
                    $_POST[$direction] = '#DISABLED#' . $_POST[$direction];
                    break;
                case 'Enable':
                    // enable is the opposite of disable..
                    $_POST[$direction] = preg_replace('/^#DISABLED#/', '', $_POST[$direction]);
                    break;
            }
        }

        //echo "after case, rule $i<br>\n";
        // Make sure there's something there... direction is required
        if (!isset($_POST[$and])) {
            $_POST[$and] = '';
        }
        // If any of the "and" parts are missing, clear the whole and part
        if ($_POST[$and] === '' || $_POST[$and_direction] === '' || $_POST[$and_target] === '') {
            $_POST[$and] = '';
            $_POST[$and_direction] = '';
            $_POST[$and_target] = '';
        }

        if (isset($_POST[$direction]) && $_POST[$direction]) {
            //echo "$direction: $_POST[$direction]<br>\n";
            $new_ruleset[] = [
                'description' => $_POST[$description],
                'direction' => $_POST[$direction],
                'target' => $_POST[$target],
                'and' => $_POST[$and],
                'and_direction' => $_POST[$and_direction],
                'and_target' => $_POST[$and_target],
                'action' => $_POST[$action]
            ];
        }
    }

    // Ok, at this point I think we can finish assembling the new file.
    foreach ($new_ruleset as $new_rule) {
        $new_file [] =
            '#' . $new_rule['description'] . "\n" .
            $new_rule['direction'] . "\t" .
            $new_rule['target'] . "\t" .
            $new_rule['and'] . "\t" .
            $new_rule['and_direction'] . "\t" .
            $new_rule['and_target'] . "\t" .
            $new_rule['action'] . "\n";
    }
    // And add on the default rule if there is one.
    if ($default_action !== '') {
        $new_file[] = '#' . sanitizeInput($default_desc) . "\n";
        $new_file[] = sanitizeInput($default_direction) . "\tdefault\t\t\t" . sanitizeInput($default_action) ."\n";
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
    $getFile = basename(sanitizeInput($short_filename));
    $filename = MSRE_RULESET_DIR . '/' . $getFile;
    list($bytes, $status_msg) = Write_File($filename, $new_file);

    // Schedule a reload of mailscanner's stuff. We can't do an immediate
    // reload w/out giving the apache user rights to run the MailScanner
    // startup/reload script, and that could be a bad idea.
    // So instead, I schedule a reload with the msre_reload.cron cron job
    $status_msg .= '<span class="status">' . "\n";
    $status_msg .= __('schedureloadmw55');
    $fh = fopen('/tmp/msre_reload', 'wb');
    // We don't need to write to the file, just it existing is enough
    if (!$fh) {
        $status_msg .= '<span class="error">' . __('error0155') . '</span><br>' . "\n";
    } else {
        $status_msg .= __('error55') . '<br>' . "\n" . sprintf(__('message55'), MSRE_RELOAD_INTERVAL) . '<br>' . "\n";
    }
    $status_msg .= "</span>\n";

    return [$bytes, $status_msg];
}

function Read_File($filename, $size)
{
    // Read contents of file
    $fh = fopen($filename, 'rb');
    // Read contents into string
    $returnvalue = fread($fh, $size);
    // Close file
    fclose($fh);

    return $returnvalue;
}

/**
 * @param string $filename
 * @param array $content
 * @return array
 */
function Write_File($filename, $content)
{
    // Writes a file to $filename (which must include the full path!)
    // and fills it with $content (array)
    // Returns the number of bytes written and status messages
    
    // Return the number of bytes written
    $bytes = 0;
    $status_msg = '';

    // We will print some status messages as we're doing it.
    $status_msg .= '<span class="status">' . "\n";
    
    // Make a backup copy of the file first, in case anything goes wrong.
    $status_msg .= __('backupfile55');
    $backup_name = $filename . '.bak';
    if (!copy($filename, $backup_name)) {
        $status_msg .= '<span class="error">' . __('error0255') . '</span><br>' . "\n";
    } else {
        $status_msg .= __('ok55') . '<br>' . "\n";
        // Now open the file for writing
        $status_msg .= sprintf(__('openwriting55'), $filename);
        $fh = fopen($filename, 'wb');
        if (!$fh) {
            $status_msg .= '<span class="error">' . sprintf(__('error0355'), $filename) . '</span><br>' . "\n";
        } else {
            $status_msg .= __('ok55') . '<br>' . "\n";
            // Write contents
            $status_msg .= __('writefile55');
            foreach ($content as $line) {
                $bytes += fwrite($fh, $line);
            }
            $status_msg .= sprintf(__('writebytes55'), $bytes) . '<br>' . "\n";
            // Close file
            fclose($fh);
            $status_msg .= __('fileclosed55') . '<br>' . "\n";
        }
    }

    $status_msg .= __('donewrite55') . '<br>' . "\n";
    $status_msg .= '</span>' . "\n";

    return [$bytes, $status_msg];
}

function Fix_Quotes($stuff)
{
    // Gets rid of any backslashed quotes in the stuff given to it.
    // Also gets rid of any multiple backslashes.
    $stuff = str_replace("\\\\", "\\", $stuff);
    $stuff = str_replace("\\'", "'", $stuff);
    $stuff = str_replace('\"', '"', $stuff);
    return $stuff;
}
