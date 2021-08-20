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

/**
 * Class Filter
 */
class Filter
{
    /** @var array */
    public $item = [];
    public $operators = [];
    public $columns = [];
    public $reports = [];
    public $last_operator;
    public $last_column;
    public $last_value;
    public $display_last = 0;

    /**
     * Filter constructor.
     */
    public function __construct()
    {
        $this->operators = [
            '=' => __('equal09'),
            '<>' => __('notequal09'),
            '>' => __('greater09'),
            '>=' => __('greaterequal09'),
            '<' => __('less09'),
            '<=' => __('lessequal09'),
            'LIKE' => __('like09'),
            'NOT LIKE' => __('notlike09'),
            'REGEXP' => __('regexp09'),
            'NOT REGEXP' => __('notregexp09'),
            'IS NULL' => __('isnull09'),
            'IS NOT NULL' => __('isnotnull09')
        ];
        $this->columns = [
            'date' => __('date09'),
            'headers' => __('headers09'),
            'id' => __('id09'),
            'size' => __('size09'),
            'from_address' => __('fromaddress09'),
            'from_domain' => __('fromdomain09'),
            'to_address' => __('toaddress09'),
            'to_domain' => __('todomain09'),
            'subject' => __('subject09'),
            'clientip' => __('clientip09'),
            'isspam' => __('isspam09'),
            'ishighspam' => __('ishighspam09'),
            'issaspam' => __('issaspam09'),
            'isrblspam' => __('isrblspam09'),
            'spamwhitelisted' => __('spamwhitelisted09'),
            'spamblacklisted' => __('spamblacklisted09'),
            'sascore' => __('sascore09'),
            'spamreport' => __('spamreport09'),
            'ismcp' => __('ismcp09'),
            'ishighmcp' => __('ishighmcp09'),
            'issamcp' => __('issamcp09'),
            'mcpwhitelisted' => __('mcpwhitelisted09'),
            'mcpblacklisted' => __('mcpblacklisted09'),
            'mcpscore' => __('mcpscore09'),
            'mcpreport' => __('mcpreport09'),
            'virusinfected' => __('virusinfected09'),
            'nameinfected' => __('nameinfected09'),
            'otherinfected' => __('otherinfected09'),
            'report' => __('report09'),
            'hostname' => __('hostname09')
        ];
    }

    /**
     * @param $column
     * @param $operator
     * @param $value
     */
    public function Add($column, $operator, $value)
    {
        // Don't show the last column, operator, and value now
        $value = deepSanitizeInput($value, 'string');
        if (!$this->ValidateOperator($operator) || !$this->ValidateColumn($column)
            || !validateInput($value, 'general')) {
            return;
        }
        $this->display_last = 0;

        //  Make sure this is not a duplicate
        foreach ($this->item as $val) {
            if (($val[0] === $column) && ($val[1] === $operator) && ($val[2] === $value)) {
                return;
            }
        }

        $this->item[] = [$column, $operator, $value];
    }

    /**
     * @param string $item
     */
    public function Remove($item)
    {
        // Store the last column, operator, and value, and force the form to default to them
        $this->last_column = $this->item[$item][0];
        $this->last_operator = $this->item[$item][1];
        $this->last_value = $this->item[$item][2];
        $this->display_last = 1;
        unset($this->item[$item]);
    }

    /**
     * @param string $token
     */
    public function Display($token)
    {
        echo '<table width="600" border="0" class="boxtable">' . "\n";
        echo ' <tr><th colspan="2">' . __('activefilters09') . '</th></tr>' . "\n";
        if (count($this->item) > 0) {
            foreach ($this->item as $key => $val) {
                echo '<tr><td>' .
                    $this->TranslateColumn($val[0]) . ' ' . $this->TranslateOperator($val[1]) .
                    ' "' . stripslashes(
                        $val[2]
                    ) . '"</td><td align="right"><a href="' . sanitizeInput($_SERVER['PHP_SELF']) . '?token=' . $_SESSION['token'] . '&amp;action=remove&amp;column=' . $key . '">' . __('remove09') . '</a></td></tr>' . "\n";
            }
        } else {
            echo '<tr><td colspan="2">' . __('none09') . '</td></tr>' . "\n";
        }

        // Add filter
        echo ' <tr><th colspan="2">' . __('addfilter09') . '</th></tr>' . "\n";
        echo ' <tr><td colspan="2">' . $this->DisplayForm() . '</td></tr>' . "\n";
        echo ' <tr><th colspan="2">' . __('stats09') . '</th></tr>' . "\n";
        $query = "
SELECT
 DATE_FORMAT(MIN(date),'" . DATE_FORMAT . "') AS oldest,
 DATE_FORMAT(MAX(date),'" . DATE_FORMAT . "') AS newest,
 COUNT(date) AS messages
FROM
 maillog
WHERE
 1=1
" . $this->CreateSQL();
        $sth = dbquery($query);
        while ($row = $sth->fetch_object()) {
            echo ' <tr><td>' . __('oldrecord09') . '</td><td align="right">' . $row->oldest . '</td></tr>' . "\n";
            echo ' <tr><td>' . __('newrecord09') . '</td><td align="right">' . $row->newest . '</td></tr>' . "\n";
            echo ' <tr><td>' . __('messagecount09') . '</td><td align="right">' . number_format($row->messages) . '</td></tr>' . "\n";
        }
        echo '<tr><th colspan="2">' . __('reports09') . '</th></tr>' . "\n";
        echo '<tr><td colspan="2"><ul>' . "\n";
        foreach ($this->reports as $report) {
            $url = $report['url'];
            if ($report['useToken']) {
                $url .= '?token=' . $token;
            }
            echo '<li><a href="' . $url . '">' . $report['description'] . '</a>' . "\n";
        }
        echo '</ul></td></tr>' . "\n";
        echo '</table>' . "\n";
    }

    public function CreateMtalogSQL()
    {
        $sql = '';
        foreach ($this->item as $key => $val) {
            if ($val[0] === 'date') {
                // Change field from timestamp to date format
                $val[0] = "DATE_FORMAT(timestamp,'%Y-%m-%d')";
                
                $sql .= self::getSqlCondition($val);
            }
        }

        return $sql;
    }

    public function CreateSQL()
    {
        $sql = 'AND ' . $_SESSION['global_filter'] . "\n";
        foreach ($this->item as $key => $val) {
            $sql .= self::getSqlCondition($val);
        }

        return $sql;
    }
    
    private static function getSqlCondition($val)
    {
        // If LIKE selected - place wildcards either side of the query string
        if ($val[1] === 'LIKE' || $val[1] === 'NOT LIKE') {
            $val[2] = '%' . $val[2] . '%';
        }
        if (is_numeric($val[2])) {
            return "AND\n $val[0] $val[1] $val[2]\n";
        } elseif ($val[1] === 'IS NULL' || $val[1] === 'IS NOT NULL') {
            // Handle NULL and NOT NULL's
            return "AND\n $val[0] $val[1]\n";
        } elseif ($val[2]!=='' && $val[2][0] === '!') {
            // Allow !<sql_function>
            return "AND\n $val[0] $val[1] " . substr($val[2], 1) . "\n";
        } else {
            // Regular string
            return "AND\n $val[0] $val[1] '$val[2]'\n";
        }
    }

    /**
     * @param $column
     * @return mixed
     */
    public function TranslateColumn($column)
    {
        return $this->columns[$column];
    }

    /**
     * @param $operator
     * @return mixed
     */
    public function TranslateOperator($operator)
    {
        return $this->operators[$operator];
    }

    public function DisplayForm()
    {
        // Form
        $return = '<form method="post" action="' . sanitizeInput($_SERVER['PHP_SELF']) . '">' . "\n";

        // Table
        $return .= '<table width="100%">' . "\n";

        // Columns
        $return .= '<tr><td colspan="2">' . "\n";
        $return .= '<select name="column">' . "\n";
        foreach ($this->columns as $key => $val) {
            $return .= ' <option value="' . $key . '"';
            //  Use the last value as the default
            if ($this->display_last && $key === $this->last_column) {
                $return .= ' SELECTED';
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
            //  Use the last value as the default
            if ($this->display_last && $key === $this->last_operator) {
                $return .= ' SELECTED';
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
            $return .= ' value="' . htmlentities(stripslashes($this->last_value)) . '"';
        }
        $return .= ">\n";
        $return .= '</td><td align="right"><button type="submit" name="action" value="add">' . __('add09') . '</button></td></tr>' . "\n";
        $return .= '<tr><td align="left">' . __('tosetdate09') . '</td>' . "\n" . ' <td></td></tr>' . "\n";
        $return .= '<tr><th colspan="2">' . __('loadsavef09') . '</th></tr>' . "\n";
        $return .= '<tr><td><input type="text" size="50" name="save_as"></td><td align="right"><button type="submit" name="action" value="save">' . __('save09') . '</button></td></tr>' . "\n";
        $return .= '<tr><td>' . "\n";
        $return .= $this->ListSaved();
        $return .= '</td><td class="filterbuttons"><button type="submit" name="action" value="load">' . __('load09') . '</button>&nbsp;<button type="submit" name="action" value="save">' . __('save09') . '</button>&nbsp;<button type="submit" name="action" value="delete">' . __('delete09') . '</button></td></tr>' . "\n";
        $return .= '</table>' . "\n";
        $return .= '<input type="hidden" name="token" value="' . $_SESSION['token'] . '">' . "\n";
        $return .= '<input type="hidden" name="formtoken" value="' . generateFormToken('/filter.inc.php form token') . '">' . "\n";
        $return .= '</form>' . "\n";

        return $return;
    }

    /**
     * @param string $url
     * @param string $description
     * @param bool $useToken
     */
    public function AddReport($url, $description, $useToken = false)
    {
        //test if url exists if it is remove the old one. This fixes double shown urls for the reports
        foreach ($this->reports as $key => $report) {
            if ($report['url'] === $url) {
                unset($this->reports[$key]);
            }
        }
        $this->reports[] = ['url' => $url, 'description' => $description, 'useToken' => $useToken];
    }

    /**
     * @param string $name
     */
    public function Save($name)
    {
        $name = deepSanitizeInput($name, 'string');
        if (!validateInput($name, 'general')) {
            return;
        }

        dbconn();
        if (count($this->item) > 0) {
            // Delete the existing first
            $dsql = "DELETE FROM `saved_filters` WHERE `username`='" . safe_value(stripslashes($_SESSION['myusername'])) . "' AND `name`='$name'";
            dbquery($dsql);
            foreach ($this->item as $key => $val) {
                $sql = "REPLACE INTO `saved_filters` (`name`, `col`, `operator`, `value`, `username`)  VALUES ('$name',";
                foreach ($val as $value) {
                    $sql .= "'" . safe_value($value) . "',";
                }
                $sql .= "'" . safe_value(stripslashes($_SESSION['myusername'])) . "')";
                dbquery($sql);
            }
        }
    }

    /**
     * @param string $name
     */
    public function Load($name)
    {
        $name = deepSanitizeInput($name, 'string');
        if (!validateInput($name, 'general')) {
            return;
        }
        
        dbconn();
        $sql = "SELECT `col`, `operator`, `value` FROM `saved_filters` WHERE `name`='$name' AND username='" . safe_value(stripslashes($_SESSION['myusername'])) . "'";
        $sth = dbquery($sql);
        while ($row = $sth->fetch_row()) {
            $this->item[] = $row;
        }
    }

    /**
     * @param string $name
     */
    public function Delete($name)
    {
        $name = deepSanitizeInput($name, 'string');
        if (!validateInput($name, 'general')) {
            return;
        }
        
        dbconn();
        $sql = "DELETE FROM `saved_filters` WHERE `username`='" . safe_value(stripslashes($_SESSION['myusername'])) . "' AND `name`='$name'";
        dbquery($sql);
    }

    public function ListSaved()
    {
        $sql = "SELECT DISTINCT `name` FROM `saved_filters` WHERE `username`='" . safe_value(stripslashes($_SESSION['myusername'])) . "'";
        $sth = dbquery($sql);
        $return = '<select name="filter">' . "\n";
        $return .= ' <option value="_none_">' . __('none09') . '</option>' . "\n";
        while ($row = $sth->fetch_array()) {
            $return .= ' <option value="' . $row[0] . '">' . $row[0] . '</option>' . "\n";
        }
        $return .= '</select>' . "\n";

        return $return;
    }

    /**
     * @param string $operator
     * @return bool
     */
    private function ValidateOperator($operator)
    {
        $validKeys = array_keys($this->operators);

        return in_array($operator, $validKeys, true);
    }
    
    /**
     * @param string $column
     * @return bool
     */
    private function ValidateColumn($column)
    {
        $validKeys = array_keys($this->columns);

        return in_array($column, $validKeys, true);
    }
}
