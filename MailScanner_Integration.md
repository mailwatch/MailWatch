# MailScanner integration

MailScanner communicates with MailWatch through authenticated REST APIs. It sends
processed message data to MailWatch and periodically downloads the allow/block
lists and SpamAssassin settings used by the custom modules.

The integration works the same way when MailScanner and MailWatch run on one host
or on separate systems. One MailWatch installation can serve multiple MailScanner
gateways.

## Requirements

- a working MailWatch installation;
- HTTPS connectivity from each MailScanner gateway to MailWatch;
- Perl with `LWP::UserAgent`, `JSON` and `Encoding::FixLatin`;
- one private API key shared by the MailWatch PHP configuration and the
  MailScanner custom modules.

The non-core Perl modules can be installed with `cpanm`:

```console
cpanm LWP::UserAgent JSON Encoding::FixLatin
```

## Install the custom modules

Copy these files from `MailScanner_perl_scripts` to the MailScanner custom
configuration directory, normally `/etc/MailScanner/custom`:

- `MailWatch.pm`
- `MailWatchClient.pm`
- `SQLAllowBlockList.pm`
- `SQLSpamSettings.pm`
- `MailWatchConf.pm`

## Configure authentication

Define a long, random API key in `mailscanner/conf.php` on the MailWatch host:

```php
define('API_KEY', 'replace-with-a-long-random-value');
```

Set the public MailWatch base URL and the same key in `MailWatchConf.pm` on every
MailScanner gateway:

```perl
my ($api_base_url) = 'https://mailwatch.example.com'; # no trailing slash
my ($api_key) = 'replace-with-a-long-random-value';
```

The base URL is the public URL configured for MailWatch. The custom modules append
the appropriate `/api/...` path automatically.

Keep the API key private and use HTTPS to protect it in transit.

## Configure MailScanner

Use `tools/MailScanner_config/00_MailWatch.conf` as the starting point for the
MailScanner settings. Message logging is enabled with:

```ini
Always Looked Up Last = &MailWatchLogging
```

The same sample contains optional callbacks for the MailWatch allow/block lists
and no-scan settings. Enable only the integrations required by the installation,
then restart MailScanner.

## Network access

Each gateway makes HTTPS requests to these paths below the configured base URL:

- `/api/messages`
- `/api/allow-block-list`
- `/api/spam-settings`

The PHP application handles database access. MailScanner needs network access to
the MailWatch web server, while the database can remain isolated from the gateways.

## Verify the integration

After restarting MailScanner, process a test message and check the MailScanner log.
Confirm that:

- the message is delivered to the logging API;
- the configured snapshots are downloaded;
- no authentication, TLS or connection errors are reported.

Failed logging deliveries are stored in the spool configured by
`mailwatch_get_api_spool_directory()` and retried automatically. The default path
is `/var/spool/MailScanner/mailwatch`.

Once the integration is running, [MailScanner API operations](docs/mailscanner-api-operations.md)
covers what happens during a MailWatch outage, how the spool drains, how stale the
downloaded snapshots can become, how to replace the API key, and how to read the
messages the modules write to the MailScanner log.

## Upgrading from MailWatch 1.2

MailWatch 1.3 replaces direct database access from the supported Perl modules with
REST APIs. When upgrading:

1. copy the complete 1.3 module set, including the new `MailWatchClient.pm`;
2. configure the API base URL and shared key;
3. remove the old database settings and getters from a customised
   `MailWatchConf.pm`;
4. remove Perl `DBI` and MySQL/MariaDB drivers if no other local application uses
   them.

`RPC_ALLOWED_CLIENTS` and `RPC_REMOTE_SERVER` belong to the separate legacy
XML-RPC features and are not part of this REST integration.
