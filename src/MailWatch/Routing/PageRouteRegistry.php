<?php

declare(strict_types=1);

namespace MailWatch\Routing;

final class PageRouteRegistry
{
    /** @var list<string> */
    private const PAGES = [
        'auto-release.php',
        'bayes_info.php',
        'checklogin.php',
        'clamav_status.php',
        'detail.php',
        'do_message_ops.php',
        'docs.php',
        'f-prot_status.php',
        'f-secure12_status.php',
        'f-secure_status.php',
        'geoip_update.php',
        'index.php',
        'lists.php',
        'login.php',
        'logout.php',
        'mailq.php',
        'mcafee_status.php',
        'mcp_rules_update.php',
        'ms_lint.php',
        'msconfig.php',
        'msmailq.php',
        'msre_edit.php',
        'msre_index.php',
        'msrule.php',
        'mysql_status.php',
        'other.php',
        'password_reset.php',
        'postfixmailq.php',
        'quarantine.php',
        'quarantine_action.php',
        'rep_audit_log.php',
        'rep_mcp_rule_hits.php',
        'rep_mcp_score_dist.php',
        'rep_message_listing.php',
        'rep_message_ops.php',
        'rep_previous_day.php',
        'rep_sa_rule_hits.php',
        'rep_sa_score_dist.php',
        'rep_top_mail_relays.php',
        'rep_top_recipient_domains_by_quantity.php',
        'rep_top_recipient_domains_by_volume.php',
        'rep_top_recipients_by_quantity.php',
        'rep_top_recipients_by_volume.php',
        'rep_top_sender_domains_by_quantity.php',
        'rep_top_sender_domains_by_volume.php',
        'rep_top_senders_by_quantity.php',
        'rep_top_senders_by_volume.php',
        'rep_top_viruses.php',
        'rep_total_mail_by_date.php',
        'rep_viruses.php',
        'reports.php',
        'rpcserver.php',
        'sa_lint.php',
        'sa_rules_update.php',
        'sf_version.php',
        'sophos_status.php',
        'status.php',
        'user_manager.php',
        'viewmail.php',
        'viewpart.php',
    ];

    public function pageForPath(string $path): ?string
    {
        if ('/' === $path) {
            return 'index.php';
        }

        $page = ltrim($path, '/');
        if ('/' . $page !== $path || !in_array($page, self::PAGES, true)) {
            return null;
        }

        return $page;
    }
}
