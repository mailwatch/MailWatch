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

namespace MailWatch;

class Mcp
{
    /**
     * @param $mcpreport
     *
     * @return null|string|string[]
     */
    public static function format_report($mcpreport)
    {
        // Clean-up input
        $mcpreport = preg_replace('/\n/', '', $mcpreport);
        $mcpreport = preg_replace('/\t/', ' ', $mcpreport);
        // Run regex against the MailScanner mcpreport picking out the (score=xx, required x, RULES...)
        if (preg_match('/ \((.+?)\)/i', $mcpreport, $sa_rules)) {
            // Get rid of the first match from the array
            array_shift($sa_rules);
            // Split the array
            $sa_rules = explode(', ', $sa_rules[0]);
            // Check to make sure a check was actually run
            if ('Message larger than max testing size' === $sa_rules[0] || 'timed out' === $sa_rules[0]) {
                return $sa_rules[0];
            }
            // Get rid of the 'score=', 'required' and 'autolearn=' lines
            foreach (['score=', 'required', 'autolearn='] as $val) {
                if (preg_match("/$val/", $sa_rules[0])) {
                    array_shift($sa_rules);
                }
            }
            $output_array = [];
            foreach ($sa_rules as $val) {
                $output_array[] = self::get_rule_desc($val);
            }
            // Return the result as an html formatted string
            if (\count($output_array) > 0) {
                return '<table class="sa_rules_report" cellspacing="2" width="100%">"."<tr><th>' . Translation::__('score03') . '</th><th>' . Translation::__('matrule03') . '</th><th>' . Translation::__('description03') . '</th></tr>' . implode(
                        "\n",
                        $output_array
                    ) . '</table>' . "\n";
            }

            return $mcpreport;
        }

        // Regular expression did not match, return unmodified report instead
        return $mcpreport;
    }

    /**
     * @param $rule
     *
     * @return string
     */
    public static function get_rule_desc($rule)
    {
        // Check if SA scoring is enabled
        $rule_score = '';
        if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
            list($rule, $rule_score) = $regs;
        }
        $result = Db::query("SELECT rule, rule_desc FROM mcp_rules WHERE rule='$rule'");
        $row = $result->fetch_object();
        if ($row && $row->rule && $row->rule_desc) {
            return '<tr><td>' . $rule_score . '</td><td>' . $row->rule . '</td><td>' . $row->rule_desc . '</td></tr>' . "\n";
        }

        return '<tr><td>' . $rule_score . '<td>' . $rule . '</td><td>&nbsp;</td></tr>' . "\n";
    }

    /**
     * @param $rule
     *
     * @return bool
     */
    public static function return_rule_desc($rule)
    {
        $result = Db::query("SELECT rule, rule_desc FROM mcp_rules WHERE rule='$rule'");
        $row = $result->fetch_object();
        if ($row) {
            return $row->rule_desc;
        }

        return false;
    }
}
