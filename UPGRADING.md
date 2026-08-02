# UPGRADE INSTRUCTIONS

Upgrading instruction has moved to [http://docs.mailwatch.org](http://docs.mailwatch.org)

## Upgrading to 2.0

### MailWatch must be served from the root of a host

MailWatch emits absolute paths and no longer works from a subdirectory. An
installation reached as `https://mail.example.com/mailwatch/`, through an Apache
`Alias` or a document root above `public_html`, must be given a host or virtual
host of its own with the document root set to `public_html`.

See [web server configuration](docs/web-servers.md).

### The antivirus status pages have new paths

| Now | Was |
|---|---|
| `/status/antivirus/clamav` | `/clamav_status.php` |
| `/status/antivirus/sophos` | `/sophos_status.php` |
| `/status/antivirus/mcafee` | `/mcafee_status.php` |
| `/status/antivirus/f-prot` | `/f-prot_status.php` |
| `/status/antivirus/f-secure` | `/f-secure_status.php` |
| `/status/antivirus/f-secure-12` | `/f-secure12_status.php` |

The old paths answer with a permanent redirect. Custom links and bookmarks keep
working, but should be updated: the redirects are removed in a later release.
