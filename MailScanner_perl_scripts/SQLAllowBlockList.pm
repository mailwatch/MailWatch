#
# MailWatch for MailScanner
# Copyright (C) 2003-2011 Steve Freegard (steve@freegard.name)
# Copyright (C) 2011 Garrod Alwood (garrod.alwood@lorodoes.com)
# Copyright (C) 2014-2026 MailWatch Team (https://github.com/mailwatch/MailWatch/graphs/contributors)
#
#   Custom Module SQLAllowBlockList
#
#   Version 2.0
#
# This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
# License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
# version.
#
# This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
# warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
#
# In addition, as a special exception, the copyright holder gives permission to link the code of this program with
# those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
# that use the same license as those files), and distribute linked combinations including the two.
# You must obey the GNU General Public License in all respects for all of the code used other than those files in the
# PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
# your version of the program, but you are not obligated to do so.
# If you do not wish to do so, delete this exception statement from your version.
#
# You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
# Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
#

# This module downloads complete allowlist and blocklist snapshots from
# MailWatch and keeps the active lookup tables in memory for MailScanner.

package MailScanner::CustomConfig;

use strict 'vars';
use strict 'refs';
no strict 'subs'; # Allow bare words for MailScanner parameter hashes

use File::Basename;
use LWP::UserAgent;
use MailWatchClient;

our $VERSION = '2.0';
our $mailWatchListClient;

my $dirname = dirname(__FILE__);
require $dirname . '/MailWatchConf.pm';

my $api_base_url = mailwatch_get_api_base_url();
my $snapshot_endpoint = $api_base_url . '/api/allow-block-list.php';
my $api_key = mailwatch_get_api_key();
my $api_max_retries = mailwatch_get_api_max_retries();
my $api_retry_delay = mailwatch_get_api_retry_delay();
my $api_max_retry_delay = defined &mailwatch_get_api_max_retry_delay
    ? mailwatch_get_api_max_retry_delay()
    : 60;
my $abl_refresh_time = mailwatch_get_ABL_refresh_time();

my (%Allowlist, %Blocklist);
my ($refresh_time, $snapshot_etag, $snapshot_loaded);

my $http_client = LWP::UserAgent->new(
    protocols_allowed => ['http', 'https'],
    timeout           => 10,
    max_size          => 5 * 1024 * 1024,
    agent             => "MailWatchAllowBlockList/$VERSION",
);

$mailWatchListClient = MailWatchClient->new(
    user_agent          => $http_client,
    api_key             => $api_key,
    api_max_retries     => $api_max_retries,
    api_retry_delay     => $api_retry_delay,
    api_max_retry_delay => $api_max_retry_delay,
    api_logger          => \&_log_api,
);

# Convert the shared client's log levels to MailScanner log calls without
# including the endpoint API key in messages.
sub _log_api {
    my ($level, $message) = @_;

    if ($level eq 'error' || $level eq 'warn') {
        MailScanner::Log::WarnLog('MailWatch: SQLAllowBlockList:: %s', $message);
    } else {
        MailScanner::Log::InfoLog('MailWatch: SQLAllowBlockList:: %s', $message);
    }
}

# Initialise both callbacks from the same snapshot. MailScanner invokes the
# allowlist and blocklist initialisers separately, but only one refresh is made.
sub InitSQLAllowlist {
    MailScanner::Log::InfoLog('MailWatch: Starting up MailWatch REST Allowlist');
    RefreshAllowBlockLists() if _refresh_due();
    MailScanner::Log::InfoLog('MailWatch: Read %d allowlist entries', _entry_count(\%Allowlist));
}

sub InitSQLBlocklist {
    MailScanner::Log::InfoLog('MailWatch: Starting up MailWatch REST Blocklist');
    RefreshAllowBlockLists() if _refresh_due();
    MailScanner::Log::InfoLog('MailWatch: Read %d blocklist entries', _entry_count(\%Blocklist));
}

# Refresh on the configured interval, then apply the existing in-memory lookup
# semantics to the current message.
sub SQLAllowlist {
    RefreshAllowBlockLists() if _refresh_due();
    my ($message) = @_;

    return LookupList($message, \%Allowlist);
}

sub SQLBlocklist {
    RefreshAllowBlockLists() if _refresh_due();
    my ($message) = @_;

    return LookupList($message, \%Blocklist);
}

# MailScanner shutdown callbacks. There is no persistent HTTP connection or
# database handle to close.
sub EndSQLAllowlist {
    MailScanner::Log::InfoLog('MailWatch: Closing down MailWatch REST Allowlist');
}

sub EndSQLBlocklist {
    MailScanner::Log::InfoLog('MailWatch: Closing down MailWatch REST Blocklist');
}

sub _refresh_due {
    return 1 unless defined $refresh_time;

    return (time() - $refresh_time) >= ($abl_refresh_time * 60);
}

# Fetch and replace both lists as one operation. A transport error, incompatible
# contract or invalid entry leaves the last known valid snapshot untouched. If
# startup has never succeeded, empty lists preserve the historical fail-open
# behaviour.
sub RefreshAllowBlockLists {
    MailScanner::Log::InfoLog('MailWatch: Allow/block list refresh started') if $snapshot_loaded;
    $refresh_time = time();

    my $result = $mailWatchListClient->fetch_api_snapshot($snapshot_endpoint, $snapshot_etag);
    unless (defined $result) {
        MailScanner::Log::WarnLog(
            'MailWatch: SQLAllowBlockList:: Snapshot refresh failed; retaining %s snapshot',
            $snapshot_loaded ? 'last known valid' : 'empty fail-open',
        );

        return 0;
    }
    if ($result->{not_modified}) {
        MailScanner::Log::InfoLog('MailWatch: Allow/block list snapshot is unchanged');

        return 1;
    }

    my ($allowlist, $blocklist) = ValidateAllowBlockSnapshot($result->{snapshot});
    unless (defined $allowlist && defined $blocklist) {
        MailScanner::Log::WarnLog(
            'MailWatch: SQLAllowBlockList:: Invalid snapshot; retaining %s snapshot',
            $snapshot_loaded ? 'last known valid' : 'empty fail-open',
        );

        return 0;
    }

    %Allowlist = %{$allowlist};
    %Blocklist = %{$blocklist};
    $snapshot_etag = $result->{etag} if defined $result->{etag};
    $snapshot_loaded = 1;
    MailScanner::Log::InfoLog(
        'MailWatch: Loaded allow/block list snapshot (%d allowlist, %d blocklist entries)',
        _entry_count(\%Allowlist),
        _entry_count(\%Blocklist),
    );

    return 1;
}

# Validate the complete response into temporary lookup maps before either live
# list is replaced. Unknown top-level fields are tolerated for future contracts.
sub ValidateAllowBlockSnapshot {
    my ($snapshot) = @_;

    return unless ref $snapshot eq 'HASH';
    return unless defined $snapshot->{contract}
        && $snapshot->{contract} eq 'mailwatch.allow-block-list.v1';
    return unless defined $snapshot->{snapshot_version}
        && !ref $snapshot->{snapshot_version}
        && $snapshot->{snapshot_version} =~ /\A[a-f0-9]{64}\z/;

    my @maps;
    for my $name (qw(allowlist blocklist)) {
        return unless ref $snapshot->{$name} eq 'ARRAY';

        my %map;
        for my $entry (@{$snapshot->{$name}}) {
            return unless ref $entry eq 'HASH';
            return unless defined $entry->{to_address} && !ref $entry->{to_address};
            return unless defined $entry->{from_address} && !ref $entry->{from_address};

            $map{lc $entry->{to_address}}{lc $entry->{from_address}} = 1;
        }
        push @maps, \%map;
    }

    return @maps;
}

sub _entry_count {
    my ($list) = @_;
    my $count = 0;
    $count += scalar keys %{$list->{$_}} for keys %{$list};

    return $count;
}

# Based on the recipients, sender and client IP, choose the applicable list.
# Recipient scopes are checked in this order: "default", every exact recipient,
# then every recipient domain. Within each scope the sender can match by exact
# address, domain, @domain, client IP, shortened IPv4 prefix or wildcard domain.
# Return 1 for a match and 0 when no rule applies.
sub LookupList {
    my ($message, $AllowBlock) = @_;

    return 0 unless $message;

    my $from = $message->{from};
    my $fromdomain = $message->{fromdomain};
    my $subdom = $fromdomain;
    my @subdomains;
    # news.example.org produces *.example.org and *.org candidates. Combining
    # these with the sender local part also supports bounce@*.example.org.
    while ($subdom =~ /.*?\.(.*)/) {
        $subdom = $1;
        push @subdomains, '*.' . $subdom;
    }

    my @keys = ('default', @{$message->{to}}, @{$message->{todomain}});
    my $ip = $message->{clientip};
    # Preserve historical matching of the first three, two or one IPv4 octets,
    # both with and without the trailing dot.
    $ip =~ /(\d{1,3}\.)(\d{1,3}\.)(\d{1,3}\.)/;
    my $ip3 = "$1$2$3";
    my $ip3c = substr($ip3, 0, -1);
    my $ip2 = "$1$2";
    my $ip2c = substr($ip2, 0, -1);
    my $ip1 = $1;
    my $ip1c = substr($ip1, 0, -1);
    my ($localpart) = split /@/, $from;

    for my $key (@keys) {
        return 1 if $AllowBlock->{$key}{$from};
        return 1 if $AllowBlock->{$key}{$fromdomain};
        return 1 if $AllowBlock->{$key}{'@' . $fromdomain};
        return 1 if $AllowBlock->{$key}{$ip};
        return 1 if $AllowBlock->{$key}{$ip3};
        return 1 if $AllowBlock->{$key}{$ip3c};
        return 1 if $AllowBlock->{$key}{$ip2};
        return 1 if $AllowBlock->{$key}{$ip2c};
        return 1 if $AllowBlock->{$key}{$ip1};
        return 1 if $AllowBlock->{$key}{$ip1c};
        return 1 if $AllowBlock->{$key}{default};
        for my $subdomain (@subdomains) {
            return 1 if $AllowBlock->{$key}{$subdomain};
            return 1 if $AllowBlock->{$key}{$localpart . '@' . $subdomain};
        }
    }

    return 0;
}

1;
