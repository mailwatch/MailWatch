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
}