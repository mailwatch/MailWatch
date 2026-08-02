<?php

declare(strict_types=1);

namespace MailWatch\Shared\Http;

final class RouteRegistry
{
    /** @var list<string> */
    private const PAGES = [
        'auto-release.php',
        'bayes_info.php',
        'checklogin.php',
        'detail.php',
        'do_message_ops.php',
        'docs.php',
        'geoip_update.php',
        'index.php',
        'lists.php',
        'login.php',
        'logout.php',
        'mailq.php',
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
        'status.php',
        'user_manager.php',
        'viewmail.php',
        'viewpart.php',
    ];

    /** Path to API handler name. */
    private const API = [
        '/api/messages' => 'messages',
        '/api/allow-block-list' => 'allow-block-list',
        '/api/spam-settings' => 'spam-settings',
    ];

    /**
     * Path to controller handler and its parameters.
     *
     * These paths name what the page is, not the file that serves it.
     *
     * @var array<string, array{string, array<string, string>}>
     */
    private const CONTROLLERS = [
        '/status/antivirus/clamav' => ['antivirus-status', ['scanner' => 'clamav']],
        '/status/antivirus/sophos' => ['antivirus-status', ['scanner' => 'sophos']],
        '/status/antivirus/mcafee' => ['antivirus-status', ['scanner' => 'mcafee']],
        '/status/antivirus/f-prot' => ['antivirus-status', ['scanner' => 'f-prot']],
        '/status/antivirus/f-secure' => ['antivirus-status', ['scanner' => 'f-secure']],
        '/status/antivirus/f-secure-12' => ['antivirus-status', ['scanner' => 'f-secure12']],
    ];

    /** Paths that used to be page scripts, kept working for one release. */
    private const MOVED = [
        '/clamav_status.php' => '/status/antivirus/clamav',
        '/sophos_status.php' => '/status/antivirus/sophos',
        '/mcafee_status.php' => '/status/antivirus/mcafee',
        '/f-prot_status.php' => '/status/antivirus/f-prot',
        '/f-secure_status.php' => '/status/antivirus/f-secure',
        '/f-secure12_status.php' => '/status/antivirus/f-secure-12',
    ];

    public function match(string $path): ?Route
    {
        if ('/' === $path) {
            return new Route(RouteType::Page, 'index.php');
        }

        if (isset(self::API[$path])) {
            return new Route(RouteType::Api, self::API[$path]);
        }

        if (isset(self::CONTROLLERS[$path])) {
            [$handler, $parameters] = self::CONTROLLERS[$path];

            return new Route(RouteType::Controller, $handler, $parameters);
        }

        if (isset(self::MOVED[$path])) {
            return new Route(RouteType::Redirect, self::MOVED[$path]);
        }

        $page = ltrim($path, '/');
        if ('/' . $page !== $path || !in_array($page, self::PAGES, true)) {
            return null;
        }

        return new Route(RouteType::Page, $page);
    }
}
