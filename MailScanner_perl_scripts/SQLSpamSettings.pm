#
# MailWatch for MailScanner
# Copyright (C) 2003-2011 Steve Freegard (steve@freegard.name)
# Copyright (C) 2011 Garrod Alwood (garrod.alwood@lorodoes.com)
# Copyright (C) 2014-2026 MailWatch Team (https://github.com/mailwatch/MailWatch/graphs/contributors)
#
#   Custom Module SQLSpamSettings
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

# This module downloads SpamAssassin score and no-scan settings from MailWatch
# and keeps the three active lookup tables in memory for MailScanner.

package MailScanner::CustomConfig;

use strict 'vars';
use strict 'refs';
no strict 'subs'; # Allow bare words for MailScanner parameter hashes

use File::Basename;
use LWP::UserAgent;
use MailWatchClient;
use Scalar::Util qw(looks_like_number);

our $VERSION = '2.0';
our $mailWatchSpamSettingsClient;

my $dirname = dirname(__FILE__);
require $dirname . '/MailWatchConf.pm';

my $api_base_url = mailwatch_get_api_base_url();
my $snapshot_endpoint = $api_base_url . '/api/spam-settings.php';
my $api_key = mailwatch_get_api_key();
my $api_max_retries = mailwatch_get_api_max_retries();
my $api_retry_delay = mailwatch_get_api_retry_delay();
my $api_max_retry_delay = defined &mailwatch_get_api_max_retry_delay
    ? mailwatch_get_api_max_retry_delay()
    : 60;
my $ss_refresh_time = mailwatch_get_SS_refresh_time();

my (%LowSpamScores, %HighSpamScores, %ScanList);
my ($refresh_time, $snapshot_etag, $snapshot_loaded);

my $http_client = LWP::UserAgent->new(
    protocols_allowed => ['http', 'https'],
    timeout           => 10,
    max_size          => 5 * 1024 * 1024,
    agent             => "MailWatchSpamSettings/$VERSION",
);

$mailWatchSpamSettingsClient = MailWatchClient->new(
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
        MailScanner::Log::WarnLog('MailWatch: SQLSpamSettings:: %s', $message);
    } else {
        MailScanner::Log::InfoLog('MailWatch: SQLSpamSettings:: %s', $message);
    }
}

# MailScanner initialises the three callbacks separately. They share one REST
# snapshot, so a successful first call supplies all three lookup tables.
sub InitSQLSpamScores {
    RefreshSpamSettings() if _spam_settings_refresh_due();
    MailScanner::Log::InfoLog('MailWatch: SQLSpamSettings:: Read %d Spam entries', scalar keys %LowSpamScores);
}

sub InitSQLHighSpamScores {
    RefreshSpamSettings() if _spam_settings_refresh_due();
    MailScanner::Log::InfoLog('MailWatch: SQLSpamSettings:: Read %d high Spam entries', scalar keys %HighSpamScores);
}

sub InitSQLNoScan {
    RefreshSpamSettings() if _spam_settings_refresh_due();
    MailScanner::Log::InfoLog('MailWatch: SQLSpamSettings:: Read %d No Spam Scan entries', scalar keys %ScanList);
}

# Refresh on the configured interval before resolving the current message.
sub SQLSpamScores {
    RefreshSpamSettings() if _spam_settings_refresh_due();
    my ($message) = @_;

    return LookupScoreList($message, \%LowSpamScores);
}

sub SQLHighSpamScores {
    RefreshSpamSettings() if _spam_settings_refresh_due();
    my ($message) = @_;

    return LookupScoreList($message, \%HighSpamScores);
}

sub SQLNoScan {
    RefreshSpamSettings() if _spam_settings_refresh_due();
    my ($message) = @_;

    return LookupNoScanList($message, \%ScanList);
}

# MailScanner shutdown callbacks. There is no persistent HTTP connection or
# database handle to close.
sub EndSQLSpamScores {
    MailScanner::Log::InfoLog('MailWatch: SQLSpamSettings:: Closing down MailWatch REST Spam Scores');
}

sub EndSQLHighSpamScores {
    MailScanner::Log::InfoLog('MailWatch: SQLSpamSettings:: Closing down MailWatch REST High Spam Scores');
}

sub EndSQLNoScan {
    MailScanner::Log::InfoLog('MailWatch: SQLSpamSettings:: Closing down MailWatch REST No Scan');
}

sub _spam_settings_refresh_due {
    return 1 unless defined $refresh_time;

    return (time() - $refresh_time) >= ($ss_refresh_time * 60);
}

# Fetch and replace low scores, high scores and no-scan values atomically. Any
# transport or validation failure retains the last known valid snapshot. Before
# the first success, empty maps preserve the historical safe defaults: score
# 999 and Spam scanning enabled.
sub RefreshSpamSettings {
    MailScanner::Log::InfoLog('MailWatch: Spam settings refresh started') if $snapshot_loaded;
    $refresh_time = time();

    my $result = $mailWatchSpamSettingsClient->fetch_api_snapshot($snapshot_endpoint, $snapshot_etag);
    unless (defined $result) {
        MailScanner::Log::WarnLog(
            'MailWatch: SQLSpamSettings:: Snapshot refresh failed; retaining %s settings',
            $snapshot_loaded ? 'last known valid' : 'empty fail-open',
        );

        return 0;
    }
    if ($result->{not_modified}) {
        MailScanner::Log::InfoLog('MailWatch: Spam settings snapshot is unchanged');

        return 1;
    }

    my ($spam_scores, $high_spam_scores, $no_scan) = ValidateSpamSettingsSnapshot($result->{snapshot});
    unless (defined $spam_scores && defined $high_spam_scores && defined $no_scan) {
        MailScanner::Log::WarnLog(
            'MailWatch: SQLSpamSettings:: Invalid snapshot; retaining %s settings',
            $snapshot_loaded ? 'last known valid' : 'empty fail-open',
        );

        return 0;
    }

    %LowSpamScores = %{$spam_scores};
    %HighSpamScores = %{$high_spam_scores};
    %ScanList = %{$no_scan};
    $snapshot_etag = $result->{etag} if defined $result->{etag};
    $snapshot_loaded = 1;
    MailScanner::Log::InfoLog(
        'MailWatch: Loaded spam settings snapshot (%d Spam, %d high Spam, %d No Scan entries)',
        scalar keys %LowSpamScores,
        scalar keys %HighSpamScores,
        scalar keys %ScanList,
    );

    return 1;
}

# Validate the complete response into temporary maps before changing live
# settings. Scores must be positive numbers because zero and negative database
# values historically mean "not configured".
sub ValidateSpamSettingsSnapshot {
    my ($snapshot) = @_;

    return unless ref $snapshot eq 'HASH';
    return unless defined $snapshot->{contract}
        && $snapshot->{contract} eq 'mailwatch.spam-settings.v1';
    return unless defined $snapshot->{snapshot_version}
        && !ref $snapshot->{snapshot_version}
        && $snapshot->{snapshot_version} =~ /\A[a-f0-9]{64}\z/;

    my @score_maps;
    for my $name (qw(spam_scores high_spam_scores)) {
        return unless ref $snapshot->{$name} eq 'HASH';

        my %scores;
        for my $username (keys %{$snapshot->{$name}}) {
            my $score = $snapshot->{$name}{$username};
            return if ref $score || !looks_like_number($score) || $score <= 0;

            $scores{lc $username} = 0 + $score;
        }
        push @score_maps, \%scores;
    }

    return unless ref $snapshot->{no_scan} eq 'ARRAY';
    my %no_scan;
    for my $username (@{$snapshot->{no_scan}}) {
        return if !defined $username || ref $username;

        $no_scan{lc $username} = 1;
    }

    return (@score_maps, \%no_scan);
}

# Choose the score using the historical precedence for the first recipient:
# exact address, recipient domain, domain-admin@recipient-domain, then admin.
# Return 999 when nothing is configured so the message is allowed through, and
# return 0 for an absent message to preserve the MailScanner callback contract.
sub LookupScoreList {
    my ($message, $LowHigh) = @_;

    return 0 unless $message;

    my $todomain = $message->{todomain}[0];
    my $to = $message->{to}[0];

    return $LowHigh->{$to} if $LowHigh->{$to};
    return $LowHigh->{$todomain} if $LowHigh->{$todomain};
    return $LowHigh->{'domain-admin@' . $todomain} if $LowHigh->{'domain-admin@' . $todomain};
    return $LowHigh->{admin} if $LowHigh->{admin};

    return 999;
}

# Decide whether the first recipient should be scanned for Spam. A no-scan rule
# on the exact address or recipient domain returns 0; otherwise return 1 so
# scanning remains enabled. Unlike scores, there is no admin fallback.
sub LookupNoScanList {
    my ($message, $NoScan) = @_;

    return 0 unless $message;

    my $todomain = $message->{todomain}[0];
    my $to = $message->{to}[0];

    return 0 if $NoScan->{$to};
    return 0 if $NoScan->{$todomain};

    return 1;
}

1;
