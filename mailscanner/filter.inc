<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 Copyright (C) 2014-2015  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)

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

class Filter
{
    var $item;
    var $operators = array(
        '=' => "is equal to",
        '<>' => "is not equal to",
        '>' => "is greater than",
        '>=' => "is greater than or equal to",
        '<' => "is less than",
        '<=' => "is less than or equal to",
        'LIKE' => "contains",
        'NOT LIKE' => "does not contain",
        'REGEXP' => "matches the regular expression",
        'NOT REGEXP' => "does not match the regular expression",
        'IS NULL' => "is null",
        'IS NOT NULL' => "is not null"
    );
    var $columns = array(
        'date' => "Date",
        'headers' => "Headers",
        'id' => "Message ID",
        'size' => "Size (bytes)",
        'from_address' => "From",
        'from_domain' => "From Domain",
        'to_address' => "To",
        'to_domain' => "To Domain",
        'subject' => "Subject",
        'clientip' => "Received from (IP Address)",
        'isspam' => "is Spam (>0 = TRUE)",
        'ishighspam' => "is High Scoring Spam (>0 = TRUE)",
        'issaspam' => "is Spam according to SpamAssassin (>0 = TRUE)",
        'isrblspam' => "is Listed in one or more RBL's (>0 = TRUE)",
        'spamwhitelisted' => "is Whitelisted (>0 = TRUE)",
        'spamblacklisted' => "is Blacklisted (>0 = TRUE)",
        'sascore' => "SpamAssassin Score",
        'spamreport' => "Spam Report",
        'ismcp' => "is MCP (>0 = TRUE)",
        'ishighmcp' => "is High Scoring MCP (>0 = TRUE)",
        'issamcp' => "is MCP according to SpamAssassin (>0 = TRUE)",
        'mcpwhitelisted' => "is MCP Whitelisted (>0 = TRUE)",
        'mcpblacklisted' => "is MCP Blacklisted (>0 = TRUE)",
        'mcpscore' => "MCP Score",
        'mcpreport' => "MCP Report",
        'virusinfected' => "contained a Virus (>0 = TRUE)",
        'nameinfected' => "contained an Unacceptable Attachment (>0 = TRUE)",
        'otherinfected' => "contained other infections (>0 = TRUE)",
        'report' => "Virus Report",
        'hostname' => "MailScanner Hostname"
    );
    var $reports = array();
    var $last_operator;
    var $last_column;
    var $last_value;
    var $display_last = 0;

    public function Add($column, $operator, $value)
    {
        // Don't show the last column, operator, and value now
        $this->display_last = 0;

        //  Make sure this is not a duplicate
        if (count($this->item) > 0) {
            foreach ($this->item as $key => $val) {
                if (($val[0] == $column) && ($val[1] == $operator) && ($val[2] == $value)) {
                    return;
                }
            }
        }

        $this->item[] = array($column, $operator, $value);
    }

    public function Remove($item)
    {
        // Store the last column, operator, and value, and force the form to default to them
        $this->last_column = $this->item[$item][0];
        $this->last_operator = $this->item[$item][1];
        $this->last_value = $this->item[$item][2];
        $this->display_last = 1;
        unset($this->item[$item]);
    }

    public function Display()
    {
        global $lang;
        echo '<table width="600" border="0" class="boxtable">' . "\n";
        echo ' <tr><th colspan="2">' . __('activefilters09') . '</th></tr>' . "\n";
        if (count($this->item) > 0) {
            foreach ($this->item as $key => $val) {
                echo '<tr><td>' .
                    $this->TranslateColumn($val[0]) . ' ' . $this->TranslateOperator($val[1]) .
                    ' \'' . stripslashes(
                        $val[2]
                    ) . '\'</td><td align="right"><a href="' . sanitizeInput($_SERVER["PHP_SELF"]) . '?action=remove&amp;column=' . $key . '">Remove</a></td></tr>' . "\n";
            }
        } else {
            echo '<tr><td colspan="2">' . __('none09') . '</td></tr>' . "\n";
        }

        // Add filter
        echo ' <tr><th colspan="2">' . __('addfilter09') . '</th></tr>' . "\n";
        echo ' <tr><td colspan="2">' . $this->DisplayForm() . '</td></tr>' . "\n";
        echo ' <tr><th colspan="2">Statistics (Filtered)</th></tr>' . "\n";
        $query = "
SELECT
 DATE_FORMAT(MIN(date),'" . DATE_FORMAT . "') AS oldest,
 DATE_FORMAT(MAX(date),'" . DATE_FORMAT . "') AS newest,
 COUNT(*) AS messages
FROM
 maillog
WHERE
 1=1 
" . $this->CreateSQL();
        $sth = dbquery($query);
        while ($row = mysql_fetch_object($sth)) {
            echo ' <tr><td>Oldest record:</td><td align="right">' . $row->oldest . '</td></tr>' . "\n";
            echo ' <tr><td>Newest record:</td><td align="right">' . $row->newest . '</td></tr>' . "\n";
            echo ' <tr><td>Message count:</td><td align="right">' . number_format($row->messages) . '</td></tr>' . "\n";
        }
        echo '<tr><th colspan="2">Reports</th></tr>' . "\n";
        echo '<tr><td colspan="2"><ul>' . "\n";
        foreach ($this->reports as $description => $url) {
            echo '<li><a href="' . $url . '">' . $description . '</a>' . "\n";
        }
        echo '</ul></td></tr>' . "\n";
        echo '</table>' . "\n";
    }

    public function CreateMtalogSQL()
    {
        $sql = '';
        if (count($this->item) > 0) {
            foreach ($this->item as $key => $val) {
                if ($val[0] == 'date') {
                    // Change field from timestamp to date format
                    $val[0] = "DATE_FORMAT(timestamp,'%Y-%m-%d')";
                    // If LIKE selected - place wildcards either side of the query string
                    if ($val[1] == "LIKE" or $val[1] == "NOT LIKE") {
                        $val[2] = '%' . $val[2] . '%';
                    }
                    if (is_numeric($val[2])) {
                        $sql .= "AND\n $val[0] $val[1] $val[2]\n";
                    } else {
                        // Handle NULL and NOT NULL's
                        if ($val[1] == "IS NULL" || $val[1] == "IS NOT NULL") {
                            $sql .= "AND\n $val[0] $val[1]\n";
                        } else {
                            // Allow !<sql_function>
                            if ($val[2]{0} == "!") {
                                $sql .= "AND\n $val[0] $val[1] " . substr($val[2], 1) . "\n";
                            } else {
                                // Regular string
                                $sql .= "AND\n $val[0] $val[1] '$val[2]'\n";
                            }
                        }
                    }
                }
            }
        }
        return $sql;
    }

    public function CreateSQL()
    {
        $sql = "AND " . $_SESSION['global_filter'] . "\n";
        if (count($this->item) > 0) {
            foreach ($this->item as $key => $val) {
                // If LIKE selected - place wildcards either side of the query string
                if ($val[1] == "LIKE" or $val[1] == "NOT LIKE") {
                    $val[2] = '%' . $val[2] . '%';
                }
                if (is_numeric($val[2])) {
                    $sql .= "AND\n $val[0] $val[1] $val[2]\n";
                } else {
                    // Handle NULL and NOT NULL's
                    if ($val[1] == "IS NULL" || $val[1] == "IS NOT NULL") {
                        $sql .= "AND\n $val[0] $val[1]\n";
                    } else {
                        // Allow !<sql_function>
                        if ($val[2]{0} == "!") {
                            $sql .= "AND\n $val[0] $val[1] " . substr($val[2], 1) . "\n";
                        } else {
                            // Regular string
                            $sql .= "AND\n $val[0] $val[1] '$val[2]'\n";
                        }
                    }
                }
            }
            return $sql;
        } else {
            return "AND " . $_SESSION['global_filter'] . "\n";
        }
    }

    public function TranslateColumn($column)
    {
        return ($this->columns[$column]);
    }

    public function TranslateOperator($operator)
    {
        return ($this->operators[$operator]);
    }

    public function DisplayForm()
    {
        global $lang;
        // Form
        $return = '<form action="' . sanitizeInput($_SERVER['PHP_SELF']) . '">' . "\n";

        // Table
        $return .= '<table width="100%">' . "\n";

        // Columns
        $return .= '<tr><td colspan="2">' . "\n";
        $return .= '<select name="column">' . "\n";
        foreach ($this->columns as $key => $val) {
            $return .= ' <option value="' . $key . '"';
            if ($this->display_last) {
                //  Use the last value as the default
                if ($key == $this->last_column) {
                    $return .= " SELECTED";
                }
            }
            $return .= '>' . $val . '</option>' . "\n";
        }
        $return .= '</select>' . "\n";
        $return .= '</td></tr>' . "\n";

        // Operators
        $return .= '<tr><td colspan="2">' . "\n";
        $return .= '<select name="operator">' . "\n";
        foreach ($this->operators as $key => $val) {
            $return .= ' <option value="' . $key . '"';
            if ($this->display_last) {
                //  Use the last value as the default
                if ($key == $this->last_operator) {
                    $return .= " SELECTED";
                }
            }
            $return .= '>' . $val . '</option>' . "\n";
        }
        $return .= '</select><br>' . "\n";
        $return .= '</td></tr>' . "\n";

        // Input
        $return .= '<tr><td>' . "\n";
        $return .= '<input type="text" size="50" name="value"';
        if ($this->display_last) {
            //  Use the last value as the default
            $return .= " value=\"" . htmlentities(stripslashes($this->last_value)) . "\"";
        }
        $return .= ">\n";
        $return .= '</td><td align="right"><input type="submit" name="action" value="Add"></td></tr>' . "\n";
        $return .= '<tr><td align="left">' . __('tosetdate09') . '</td>' . "\n" . ' <td></td></tr>' . "\n";
        $return .= '<tr><th colspan="2">' . __('loadsavef09') . '</th></tr>' . "\n";
        $return .= '<tr><td><input type="text" size="50" name="save_as"></td><td align="right"><input type="SUBMIT" name="action" value="Save"></td></tr>' . "\n";
        $return .= '<tr><td>' . "\n";
        $return .= $this->ListSaved(
            ) . '</td><td style="white-space: nowrap; text-align:right;"><input type="SUBMIT" name="action" value="Load">&nbsp;<input type="SUBMIT" name="action" value="Save">&nbsp;<input type="SUBMIT" name="action" value="Delete"></td></tr>' . "\n";
        $return .= '</table>' . "\n";
        $return .= '</form>' . "\n";


        return $return;
    }

    public function AddReport($url, $description)
    {
        $this->reports[$description] = $url;
    }

    public function Save($name)
    {
        dbconn();
        if (count($this->item) > 0) {
            // Delete the existing first
            $dsql = "DELETE FROM saved_filters WHERE username='" . $_SESSION['myusername'] . "' AND name='" . mysql_real_escape_string(
                    $name
                ) . "'";
            dbquery($dsql);
            foreach ($this->item as $key => $val) {
                $sql = "REPLACE INTO saved_filters VALUES ('$name',";
                foreach ($val as $value) {
                    $sql .= "'$value',";
                }
                $sql .= "'" . $_SESSION['myusername'] . "')";
                dbquery($sql);
            }
        }
    }

    public function Load($name)
    {
        dbconn();
        $sql = "SELECT col, operator, value FROM saved_filters WHERE name='" . mysql_real_escape_string(
                $name
            ) . "' AND username='" . $_SESSION['myusername'] . "'";
        $sth = dbquery($sql);
        while ($row = mysql_fetch_row($sth)) {
            $this->item[] = $row;
        }
    }

    public function Delete($name)
    {
        dbconn();
        $sql = "DELETE FROM saved_filters WHERE username='" . $_SESSION['myusername'] . "' AND name='" . mysql_real_escape_string(
                $name
            ) . "'";
        dbquery($sql);
    }

    public function ListSaved()
    {
        $sql = "SELECT DISTINCT name FROM saved_filters WHERE username='" . $_SESSION['myusername'] . "'";
        $sth = dbquery($sql);
        $return = '<select name="filter">' . "\n";
        $return .= ' <option value="_none_">None</option>' . "\n";
        while ($row = mysql_fetch_array($sth)) {
            $return .= ' <option value="' . $row[0] . '">' . $row[0] . '</option>' . "\n";
        }
        $return .= '</select>' . "\n";
        return $return;
    }

}
