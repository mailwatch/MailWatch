<?php

declare(strict_types=1);

namespace MailWatch\Shared\Http;

/**
 * What every page decides before it writes anything: whether the session is
 * still valid, whether the visitor may see the page, and how the response may
 * be cached.
 *
 * These are transport and authorisation decisions, which is why they are here
 * and not in the layout that renders the frame around them.
 */
final readonly class PageGuard
{
    /**
     * @param array<string, mixed> $session
     */
    public function __construct(private array $session)
    {
    }

    public function isAdministrator(): bool
    {
        return 'A' === ($this->session['user_type'] ?? null);
    }

    /**
     * Redirects and stops the request when the session is no longer valid, so
     * it must run before any output.
     */
    public function enforce(bool $cacheable, int $refresh): void
    {
        if ('cli' !== \PHP_SAPI) {
            if (!$cacheable) {
                disableBrowserCache();
            } else {
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600 * 48) . ' GMT');
                header('Cache-Control: store, cache, must-revalidate, post-check=0, pre-check=1');
                header('Pragma: cache');
            }
        }

        $username = $this->session['myusername'] ?? null;

        if (true === checkPrivilegeChange($username) || true === checkLoginExpiry($username)) {
            header('Location: /logout.php?error=timeout');

            exit;
        }

        if (0 === $refresh) {
            // The user is moving about on non-refreshing pages; keep the session alive.
            updateLoginExpiry($username);
        }
    }
}
