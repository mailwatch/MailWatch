# UPGRADE INSTRUCTIONS

Upgrading instruction has moved to [http://docs.mailwatch.org](http://docs.mailwatch.org)

## Upgrading to 2.0

### MailWatch must be served from the root of a host

MailWatch emits absolute paths and no longer works from a subdirectory. An
installation reached as `https://mail.example.com/mailwatch/`, through an Apache
`Alias` or a document root above `public_html`, must be given a host or virtual
host of its own with the document root set to `public_html`.

See [web server configuration](docs/web-servers.md).

### The PDO MySQL extension is now required

MailWatch 2.0 moves its database access onto Doctrine DBAL, which reaches
MySQL and MariaDB through PDO rather than through `mysqli`. Both extensions
are required during the transition: `mysqli` still serves the pages that have
not been migrated.

Install `php-pdo` and `php-mysql` (Debian and Ubuntu: `php-mysql` provides
both drivers; RHEL and derivatives: `php-mysqlnd`) before upgrading, or
`composer install` stops with an unsatisfied platform requirement.

Verify with:

```
php -m | grep pdo_mysql
```

### Establish the migration baseline and update the REST API schema

Back up the database before starting. Stop MailScanner message ingestion and
the MailWatch web application for the migration window: changing the `users`
primary key can rebuild and lock that table on MySQL or MariaDB.

First run the existing one-shot converter, which brings a 1.2.x installation
to the first 2.0 migration:

```bash
php upgrade.php
```

Initialise Doctrine's migration metadata storage. This creates or updates only
the `mailwatch_migrations` tracking table; it does not change the application
schema:

```bash
vendor/bin/doctrine-migrations migrations:sync-metadata-storage --no-interaction
```

Then record the baseline migration. Do this only after `upgrade.php` has
completed successfully; the baseline command records existing schema and does
not create it:

```bash
vendor/bin/doctrine-migrations migrations:version 'MailWatch\Migrations\Version20260803090000' --add --no-interaction
```

Inspect the remaining operations and apply them:

```bash
vendor/bin/doctrine-migrations migrate --dry-run --no-interaction
vendor/bin/doctrine-migrations migrate --no-interaction
composer schema-verify
```

The final command is read-only. It exits with status 0 when `allowlist`,
`blocklist`, `user_filters` and `users` match the schema required by the two
MailScanner REST snapshot APIs, status 1 for a schema mismatch, and status 2 when the
configuration or database cannot be read.

The migration preserves rows but changes three schema details:

- `users.id` becomes the primary key and `users.username` remains unique;
- `users.type` changes from a MySQL `ENUM` to `VARCHAR(1)`;
- `user_filters.active` changes from a MySQL `ENUM` to `VARCHAR(1)`.

Application values remain unchanged: user types continue to use `A`, `D`, `U`,
`R` and `H`, while filter activity continues to use `N` and `Y`. Scripts that
inspect primary-key or column-type metadata must be updated. Ordinary queries
by username and existing data are unaffected.

New installations currently still import `create.sql` for the complete legacy
schema. They must also record the first migration as above, run the later
migrations, and verify the REST API tables. A migration-only clean installer
will replace this transitional path when the remaining legacy tables have a
canonical definition.

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
