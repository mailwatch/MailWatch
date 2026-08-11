# MailScanner API operations

How the MailScanner integration behaves in production: what happens when MailWatch
is unreachable, where queued messages are kept, how the downloaded snapshots age,
and how to replace the API key.

Installation and first configuration are covered in
[MailScanner integration](../MailScanner_Integration.md). This document assumes
the integration is already working and describes running it.

Most of what follows is invisible while everything works. It is worth reading
before an incident rather than during one, because several behaviours here look
like faults and are not: a gateway that keeps accepting mail while MailWatch is
down is working as designed.

## What each gateway sends

| Endpoint | Method | Purpose | Contract |
|---|---|---|---|
| `/api/messages` | POST | One processed message | — |
| `/api/allow-block-list` | GET | Full allow/block list snapshot | `mailwatch.allow-block-list.v1` |
| `/api/spam-settings` | GET | Effective spam scores and no-scan settings | `mailwatch.spam-settings.v1` |

Every request carries the shared key in the `X-MailWatch-API-Key` header. Snapshot
requests also send `X-MailWatch-Contract-Version: 1`, and `If-None-Match` once a
snapshot has been downloaded. The shared client sends an `X-Request-ID`, which
MailWatch returns unchanged on every response. Errors come back as
`mailwatch.api.error.v1`.

Mail flow never waits on MailWatch. If logging fails, the message is queued and
MailScanner carries on delivering.

## Message logging

### Retry, then queue

A failed delivery is retried before it is queued, but only when retrying could
help:

| Response | Behaviour |
|---|---|
| `201` | Logged |
| `200` with `"duplicate": true` | Already logged; nothing to do |
| `408`, `429`, any `5xx` | Retried with growing delays |
| `401`, `400`, `413`, other `4xx` | Not retried — the request itself is wrong |
| No response, timeout, TLS failure | Retried |

Delays double each time, starting at `api_retry_delay` and stopping at
`api_max_retry_delay`. With the defaults — 5 retries, 5 seconds, 60 seconds — a
gateway waits 5, 10, 20, 40 and 60 seconds, roughly two and a half minutes in
total, before queueing the message.

A non-retryable rejection is queued too, but it will keep failing on replay: a
`401` in the log means the key is wrong, and no amount of waiting will fix it.

### The spool

| Property | Default | Set by |
|---|---|---|
| Directory | `/var/spool/MailScanner/mailwatch` | `api_spool_directory` |
| Maximum queued messages | 10000 | `api_spool_max_messages` |
| Messages replayed per successful delivery | 10 | `api_spool_replay_limit` |

The directory is created mode `0700` and each queued message mode `0600`, owned by
the MailScanner runtime user, because a queued message contains mail metadata.
The parent directory must be writable by that user.

Each file is named for the SHA-256 idempotency key of the message, so the same
message queued twice occupies one file. Writes are atomic: content goes to a
temporary file, is flushed and synced, then renamed into place. A file that later
turns out to be unreadable or invalid is renamed with a `.invalid` suffix and left
alone rather than deleted.

**When the spool is full the message is dropped**, with an error in the log. Ten
thousand queued messages means MailWatch has been unreachable for a long time;
treat the limit as an alarm threshold, not a cushion.

### How the queue drains

There is no replay command. Replay happens automatically after each *successful*
delivery: a gateway that logs a new message also pushes up to
`api_spool_replay_limit` queued ones, oldest first.

The practical consequence is that the queue drains in proportion to mail traffic,
not to elapsed time. With the default limit of 10, a gateway handling one message
per minute clears a backlog of a thousand in about a hundred minutes; a quiet
gateway clears it slowly. On a large backlog, raise `api_spool_replay_limit`, or
push traffic through the gateway, rather than waiting.

Concurrent MailScanner children cannot replay at the same time: replay takes a
non-blocking lock on `.replay.lock` in the spool directory, and any child that
does not get it simply skips the attempt.

### Inspecting the queue

```bash
ls -1 /var/spool/MailScanner/mailwatch/*.json | wc -l
```

```bash
ls -1t /var/spool/MailScanner/mailwatch/*.invalid 2>/dev/null
```

Queued files are JSON and can be read directly, but they contain message metadata:
treat them as you would the mail log itself.

### Duplicates

Each message carries an `Idempotency-Key` derived from its hostname, message id
and token. If a message is delivered twice — a retry that actually succeeded the
first time, or a replay after an ambiguous failure — MailWatch answers `200` with
`"duplicate": true` and stores nothing further. Replays are therefore safe, and a
duplicate in the log is not a defect.

## Snapshots

`SQLAllowBlockList.pm` and `SQLSpamSettings.pm` hold their data in memory and
refresh it on a timer, independently of each other.

| Setting | Default | Set by |
|---|---|---|
| Allow/block list refresh | 15 minutes | `abl_refresh_time` |
| Spam settings refresh | 15 minutes | `ss_refresh_time` |
| Maximum snapshot size | 5 MiB | client and `API_MAX_SNAPSHOT_BYTES` |
| HTTP timeout | 10 seconds | module |

A refresh is due when a message arrives and the interval has elapsed, so on a
gateway with no traffic the data simply stays as it is. **A change made in the
MailWatch interface reaches a gateway within one refresh interval, not
immediately** — allow up to 15 minutes with the defaults before concluding that a
list change had no effect.

The web interface applies the local MailWatch account scope before reading or
changing an entry. Administrators can manage every recipient. Domain
administrators can manage their own domain plus active domain filters. Users can
manage their own address plus active address filters. LDAP and IMAP authenticate
the account but do not widen this local role and filter policy. Additions and
deletions are POST operations protected by both the session token and the form
token; a copied deletion URL no longer changes a list.

Refreshes use `If-None-Match`. When nothing has changed MailWatch answers `304`
and no body is transferred, so a short interval is cheap.

### When a refresh fails

The module keeps the last snapshot it successfully validated and logs:

```
MailWatch: SQLAllowBlockList:: Snapshot refresh failed; retaining last known valid snapshot
```

Scanning continues on that data. This is deliberate, and it means a MailWatch
outage degrades slowly rather than changing policy abruptly. It also means the
data can be stale without any hard limit: if the outage lasts a day, the gateway
enforces yesterday's lists. There is no maximum staleness today, so the log is
the only thing that tells you the data has stopped moving. Alert on that line.

A snapshot that arrives malformed, or larger than the size limit, is rejected the
same way — the previous one is kept.

### The cold-start case

If a module has **never** loaded a snapshot — MailWatch was already down when
MailScanner started — there is nothing to retain, and it logs:

```
MailWatch: SQLAllowBlockList:: Snapshot refresh failed; retaining empty fail-open snapshot
```

Empty means no MailWatch-sourced rules apply: no allowlist or blocklist entries
match, and no MailWatch spam scores or no-scan settings override MailScanner's own
configuration. Mail keeps flowing and MailScanner's configured defaults decide the
outcome, but MailWatch-managed policy is absent until a refresh succeeds.

This is the one failure mode that changes filtering behaviour, so it is worth
restarting MailScanner after a MailWatch outage that spanned a gateway restart, to
confirm the snapshots have loaded.

## The API key

One key authenticates every gateway and every endpoint. It lives in two places
and both must match:

- `API_KEY` in `mailscanner/conf.php` on the MailWatch host;
- `$api_key` in `MailWatchConf.pm` on each MailScanner gateway.

MailWatch compares it in constant time. Use a long random value and keep the
transport on HTTPS, since the key travels in a header on every request.

### Replacing it

There is currently one valid key at a time, so replacement is a brief coordinated
change:

1. Choose the moment: gateways will fail to log while the two sides disagree.
   Messages are queued, not lost.
2. Update `API_KEY` on the MailWatch host.
3. Update `$api_key` in `MailWatchConf.pm` on every gateway.
4. Restart MailScanner on each gateway.
5. Confirm normal logging resumes, and that the spool drains.

Between steps 2 and 4 gateways receive `401` and queue their messages. Keep the
window short, and remember that a `401` is not retried — those messages wait for
replay after the key matches again.

A future release will accept several keys at once, one per installation, which
removes the coordinated window and allows a compromised gateway to be revoked on
its own.

## Correlating requests and logs

Every API response contains `X-Request-ID`. The MailScanner client supplies a
safe identifier and includes it in its success and failure messages. If another
client omits the header, MailWatch generates a 32-character hexadecimal value;
an unsafe value is replaced rather than reflected into a response header.

MailWatch writes one JSON record per API event through the PHP error log. The
line starts with `MailWatch API` and contains only operational metadata:

```text
MailWatch API {"timestamp":"2026-08-11T12:00:00Z","level":"info","event":"mailwatch.api.request.completed","request_id":"example-request","endpoint":"messages","status":201,"outcome":"inserted","duration_ms":4.217}
```

Search the MailScanner and MailWatch logs for the same request ID to follow one
delivery across the HTTP boundary. Completed requests record duration in
milliseconds; rejected and failed requests record a stable reason code.

API keys, request payloads, SQL, exception messages and stack traces are never
part of these records. Only the exception class is retained for an unexpected
server failure. Treat a credential or message payload appearing in an API log as
a defect.

## Troubleshooting

| Symptom in the MailScanner log | Cause | Action |
|---|---|---|
| `MailWatch API rejected message: 401 Unauthorized; not retrying` | Keys differ, or `API_KEY` is undefined or empty in `conf.php` | Compare both sides; confirm `conf.php` actually defines `API_KEY` |
| `Unauthorized - Set an API KEY to use this API` | MailWatch has no key configured | Define `API_KEY` in `mailscanner/conf.php` |
| `500 Invalid API configuration` | `API_KEY` present but not usable, or a size constant is not a positive integer | Check `API_KEY`, `API_MAX_PAYLOAD_BYTES`, `API_MAX_SNAPSHOT_BYTES` |
| `406 Unsupported contract version` | Perl modules and PHP application are from different releases | Upgrade both to the same release |
| `413 Payload Too Large` | A message exceeds `API_MAX_PAYLOAD_BYTES`, 10 MiB by default | Raise the limit, or accept that oversized messages are not logged |
| `Failed to log to MailWatch API` followed by `Queued in the MailWatch API spool` | MailWatch unreachable or erroring | Normal degradation; check MailWatch, then confirm the spool drains |
| `MailWatch API spool limit reached; message could not be queued` | Long outage; messages now being **dropped** | Restore MailWatch urgently; raise `api_spool_max_messages` only as a stopgap |
| `Snapshot refresh failed; retaining last known valid snapshot` | Transient API or network failure | Safe short-term; investigate if it repeats |
| `Snapshot refresh failed; retaining empty fail-open snapshot` | No snapshot has ever loaded | MailWatch policy is not being applied; fix connectivity, then restart MailScanner |
| `MailWatch API snapshot exceeded the configured response limit` | Snapshot larger than 5 MiB | Raise `API_MAX_SNAPSHOT_BYTES` and the client limit, or reduce list size |
| `MailWatch API returned an invalid JSON snapshot` | A proxy, error page or redirect is being returned instead of the API | Request the endpoint directly and inspect what comes back |
| TLS or certificate errors | Untrusted or expired certificate on the MailWatch host | Fix the certificate; do not disable verification |

The API key never appears in log messages. If you find it in a log, that is a bug
worth reporting.

### Checking an endpoint by hand

```bash
curl -sS -i -H 'X-MailWatch-API-Key: your-key' -H 'X-MailWatch-Contract-Version: 1' https://mailwatch.example.com/api/allow-block-list
```

A healthy response is `200` with `ETag` and `X-Request-ID` headers and a body
whose `contract` field is `mailwatch.allow-block-list.v1`. Run this from a gateway
rather than from a workstation, so it exercises the same network path.

### Checking the REST API database schema

After an install or upgrade, verify the four tables used by the two snapshot
APIs. Message ingestion has its own canonical `maillog` migration and contract
tests.

```bash
composer schema-verify
```

The check reads the database configured in `mailscanner/conf.php` and makes no
changes. A successful result confirms the required columns, types, nullability,
primary keys and indexes for `allowlist`, `blocklist`, `user_filters` and
`users`. A reported mismatch means the Doctrine migrations have not completed
or the schema has drifted; do not work around it by editing a table by hand.
Follow the database procedure in [UPGRADING.md](../UPGRADING.md) and rerun the
check.

## Defaults reference

| Setting | Default | Where |
|---|---|---|
| `api_max_retries` | 5 | `MailWatchConf.pm` |
| `api_retry_delay` | 5 seconds | `MailWatchConf.pm` |
| `api_max_retry_delay` | 60 seconds | `MailWatchConf.pm` |
| `api_spool_directory` | `/var/spool/MailScanner/mailwatch` | `MailWatchConf.pm` |
| `api_spool_max_messages` | 10000 | `MailWatchConf.pm` |
| `api_spool_replay_limit` | 10 | `MailWatchConf.pm` |
| `abl_refresh_time` | 15 minutes | `MailWatchConf.pm` |
| `ss_refresh_time` | 15 minutes | `MailWatchConf.pm` |
| `API_KEY` | none, required | `mailscanner/conf.php` |
| `API_MAX_PAYLOAD_BYTES` | 10 MiB | `mailscanner/conf.php` |
| `API_MAX_SNAPSHOT_BYTES` | 5 MiB | `mailscanner/conf.php` |

## Coming from MailWatch 1.2

Gateways no longer connect to the database, so they need no database driver,
no credentials and no network access to the database host — only HTTPS to
MailWatch. The migration steps are in
[MailScanner integration](../MailScanner_Integration.md#upgrading-from-mailwatch-12).

Two operational habits change with it. Message logging is now asynchronous under
failure: a message may be logged seconds or minutes after it was delivered, or
after a replay. And list changes now propagate on the refresh interval rather than
being read per message, so they take effect within minutes rather than instantly.
