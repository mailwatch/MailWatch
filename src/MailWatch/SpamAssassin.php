<?php
/**
 * Created by PhpStorm.
 * User: Alan Urquhart
 * Company: ASU Web Services LTD
 * Web: www.asuweb.co.uk
 * Date: 08/01/2018
 * Time: 13:58
 */

namespace MailWatch;


class SpamAssassin
{
    /**
     * @param $spamreport
     * @return bool|string
     */
    public static function autolearn($spamreport)
    {
        if (preg_match('/autolearn=spam/', $spamreport) === 1) {
            return __('saspam03');
        }

        if (preg_match('/autolearn=not spam/', $spamreport) === 1) {
            return __('sanotspam03');
        }

        return false;
    }

    /**
     * @param $rule
     * @return string
     */
    public static function get_rule_desc($rule)
    {
        // Check if SA scoring is enabled
        $rule_score = '';
        if (preg_match('/^(.+) (.+)$/', $rule, $regs)) {
            $rule = $regs[1];
            $rule_score = $regs[2];
        }
        $result = \MailWatch\Db::query("SELECT rule, rule_desc FROM sa_rules WHERE rule='$rule'");
        $row = $result->fetch_object();
        if ($row && $row->rule && $row->rule_desc) {
            return ('<tr><td>' . $rule_score . '</td><td>' . $row->rule . '</td><td>' . $row->rule_desc . '</td></tr>' . "\n");
        }

        return "<tr><td>$rule_score</td><td>$rule</td><td>&nbsp;</td></tr>";
    }

    /**
     * @param $rule
     * @return bool|string
     */
    public static function return_rule_desc($rule)
    {
        $result = \MailWatch\Db::query("SELECT rule, rule_desc FROM sa_rules WHERE rule='$rule'");
        $row = $result->fetch_object();
        if ($row) {
            return htmlentities($row->rule_desc);
        }

        return false;
    }

    /**
     * @param $spamreport
     * @return string
     */
    public static function format_spam_report($spamreport)
    {
        // Run regex against the MailScanner spamreport picking out the (score=xx, required x, RULES...)
        if (preg_match('/\s\((.+?)\)/i', $spamreport, $sa_rules)) {
            // Get rid of the first match from the array
            array_shift($sa_rules);
            // Split the array
            $sa_rules = explode(', ', $sa_rules[0]);
            // Check to make sure a check was actually run
            if ($sa_rules[0] === 'Message larger than max testing size' || $sa_rules[0] === 'timed out') {
                return $sa_rules[0];
            }

            // Get rid of the 'score=', 'required' and 'autolearn=' lines
            $notRulesLines = [
                //english
                'cached',
                'score=',
                'required',
                'autolearn=',
                //italian
                'punteggio=',
                'necessario',
                //german
                'benoetigt',
                'Wertung=',
                'gecached',
                //french
                'requis'
            ];
            array_walk($notRulesLines, function ($value) {
                return preg_quote($value, '/');
            });
            $notRulesLinesRegex = '(' . implode('|', $notRulesLines) . ')';

            $sa_rules = array_filter($sa_rules, function ($val) use ($notRulesLinesRegex) {
                return preg_match("/$notRulesLinesRegex/i", $val) === 0;
            });

            $output_array = [];
            foreach ($sa_rules as $sa_rule) {
                $output_array[] = SpamAssassin::get_rule_desc($sa_rule);
            }

            // Return the result as an html formatted string
            if (count($output_array) > 0) {
                return '<table class="sa_rules_report" cellspacing="2" width="100%"><tr><th>' . __('score03') . '</th><th>' . __('matrule03') . '</th><th>' . __('description03') . '</th></tr>' . implode(
                        "\n",
                        $output_array
                    ) . '</table>' . "\n";
            }

            return $spamreport;
        }

        // Regular expression did not match, return unmodified report instead
        return $spamreport;
    }

}