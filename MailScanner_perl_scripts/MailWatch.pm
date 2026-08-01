#
# MailWatch for MailScanner
# Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
# Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
# Copyright (C) 2014-2026  MailWatch Team (https://github.com/mailwatch/MailWatch/graphs/contributors)
#
#   Custom Module MailWatch
#
#   Version 2.0
#
# This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
# License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
# version.
#
# This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
# warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
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
# Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#

package MailScanner::CustomConfig;

use strict;
use warnings;
use utf8;
use Sys::Hostname;
use Storable(qw[freeze thaw]);
use POSIX;
use Socket;
use Encoding::FixLatin qw(fix_latin);
use Digest::SHA;
use Sys::Syslog;
use Socket;
use LWP::UserAgent;
use MailWatchConf;
use MailWatchClient;
# use Data::Dumper; # Uncomment this for debugging

# Uncomment the following line when debugging MailWatch.pm
#use Data::Dumper;

use vars qw($VERSION);

### The package version, both in 1.23 style *and* usable by MakeMaker:
$VERSION = substr q$Revision: 2.0 $, 10;

my ($hostname) = hostname;
my $loop = inet_aton("127.0.0.1");
my $server_port = 11553;
my $timeout = 3600;

# Get HTTP endpoint information from MailWatchConf.pm
use File::Basename;
my $dirname = dirname(__FILE__);
require $dirname . '/MailWatchConf.pm';
my $api_base_url = mailwatch_get_api_base_url();
my $api_endpoint = $api_base_url . '/api/logmail.php';
my $api_key = mailwatch_get_api_key();
my $api_max_retries = mailwatch_get_api_max_retries();
my $api_retry_delay = mailwatch_get_api_retry_delay();
my $api_max_retry_delay = defined &mailwatch_get_api_max_retry_delay
    ? mailwatch_get_api_max_retry_delay()
    : 60;
my $api_spool_directory = defined &mailwatch_get_api_spool_directory
    ? mailwatch_get_api_spool_directory()
    : '/var/spool/MailScanner/mailwatch';
my $api_spool_max_messages = defined &mailwatch_get_api_spool_max_messages
    ? mailwatch_get_api_spool_max_messages()
    : 10_000;
my $api_spool_replay_limit = defined &mailwatch_get_api_spool_replay_limit
    ? mailwatch_get_api_spool_replay_limit()
    : 10;
my $local_logger_max_retries = defined &mailwatch_get_local_logger_max_retries
    ? mailwatch_get_local_logger_max_retries()
    : 3;
my $local_logger_retry_delay = defined &mailwatch_get_local_logger_retry_delay
    ? mailwatch_get_local_logger_retry_delay()
    : 5;

my $RunInForeground;

my $httpClient = LWP::UserAgent->new(
    protocols_allowed => [ 'http', 'https' ],
    timeout           => 10, # Set a 10 seconds timeout for HTTP requests
    agent             => "MailWatchAPIPerlClient/$VERSION"
);

my $mailWatchClient = MailWatchClient->new(
    user_agent             => $httpClient,
    api_endpoint           => $api_endpoint,
    api_key                => $api_key,
    api_max_retries        => $api_max_retries,
    api_retry_delay        => $api_retry_delay,
    api_max_retry_delay    => $api_max_retry_delay,
    api_spool_directory    => $api_spool_directory,
    api_spool_max_messages => $api_spool_max_messages,
    api_spool_replay_limit => $api_spool_replay_limit,
    api_logger             => \&LogMessage,
    local_sender           => \&_send_to_logging_child,
    local_starter          => \&InitMailWatchLogging,
    local_max_retries      => $local_logger_max_retries,
    local_retry_delay      => $local_logger_retry_delay,
    local_logger           => \&_log_local_delivery,
);

sub InitMailWatchLogging {
    # Detect if MailScanner Milter is calling this custom function and do not spawn
    # MSMilter uses the blocklists and allowlists, but not the logger
    if ($0 !~ /MSMilter/) {
        # Grab values from config prior to fork, after which MailScanner methods become
        # inaccessible to descendant
        my $facility = MailScanner::Config::Value('logfacility');
        my $logsock  = MailScanner::Config::Value('logsock');
        $RunInForeground = MailScanner::Config::Value('runinforeground');

        $| = 1 if $RunInForeground;

        my $pid = fork();
        if ($pid) {
            # MailScanner child process
            waitpid $pid, 0;
        } else {
            # New process
            # Detach from parent, make connections, and listen for requests
            POSIX::setsid();
            if (!fork()) {
                $SIG{HUP} = $SIG{INT} = $SIG{PIPE} = $SIG{TERM} = $SIG{ALRM} = \&ExitLogging;
                alarm $timeout;
                $0 = "MailWatch API";

                # Reinitialize logging (cannot use MailScanner::Log due to detach)
                if ($logsock eq '') {
                    if ($^O =~ /solaris|sunos|irix/i) {
                        $logsock = 'udp';
                    } else {
                        $logsock = 'unix';
                    }
                }

                eval { Sys::Syslog::setlogsock($logsock) unless $RunInForeground; };
                eval { Sys::Syslog::openlog($0, 'pid, nowait', $facility) unless $RunInForeground; };

                # Listen for messages unless connection can't be initialized
                ListenForMessages() unless InitConnection() == 1;
            }
        exit;
        }
    }
}

sub LogMessage {
    my $level = shift;
    my $msg = shift;

    eval {
        if (!$RunInForeground) {
            Sys::Syslog::syslog($level, $msg);
        } else {
            print STDOUT "$0: $level: $msg\n";
        }
    };
}

sub _log_local_delivery {
    my ($level, $message) = @_;

    if ($level eq 'info') {
        MailScanner::Log::InfoLog("MailWatch: $message");
    } else {
        MailScanner::Log::WarnLog("MailWatch: $message");
    }
}

sub _send_to_logging_child {
    my ($payload) = @_;

    socket(my $socket, PF_INET, SOCK_STREAM, getprotobyname("tcp")) or return 0;
    my $addr = sockaddr_in($server_port, $loop);
    unless (connect($socket, $addr)) {
        close $socket;

        return 0;
    }

    my $sent = print {$socket} $payload;
    close $socket;

    return $sent ? 1 : 0;
}

sub BindPort {
    # Set up TCP/IP socket. We will start one server per MailScanner
    # child, but only one child will actually be able to get the socket.
    # The rest will die silently. When one of the MailScanner children
    # tries to log a message and fails to connect, it will start a new
    # server.
    socket(SERVER, PF_INET, SOCK_STREAM, getprotobyname("tcp"));
    setsockopt(SERVER, SOL_SOCKET, SO_REUSEADDR, 1);
    my $addr = sockaddr_in($server_port, $loop);
    bind(SERVER, $addr) or return 1;

    return 0;
}

sub ListenPort {
    # Start listening
    listen(SERVER, SOMAXCONN) or return 1;

    return 0;
}

sub InitConnection {
    # Fail to bind, we'll just exit, port in use
    if (BindPort() == 1) { return 1; }
    if (ListenPort() == 1) {
        # We are bound, but couldn't listen, so close it all down
        close(SERVER);
        return 1;
    }
    return 0;
}

sub ExitLogging {
    # Server exit - close socket, and exit gracefully.
    close(SERVER);
    exit;
}

sub ListenForMessages {
    my $message;
    LogMessage('info', "Started MailWatch API Logging child");
    $mailWatchClient->replay_api_spool();

    # Wait for messages
    while (my $cli = accept(CLIENT, SERVER)) {
        my ($port, $packed_ip) = sockaddr_in($cli);
        my $dotted_quad = inet_ntoa($packed_ip);

        # Reset emergency timeout - if we haven"t heard anything in $timeout
        # seconds, there is probably something wrong, so we should clean up
        # and let another process try.
        alarm $timeout;

        # Make sure we're only receiving local connections
        if ($dotted_quad ne "127.0.0.1") {
            LogMessage('warn', "Error: unexpected connection from $dotted_quad");
            close CLIENT;
            next;
        }
        my @in;
        while (<CLIENT>) {
            # End of normal logging message
            last if /^END$/;
            # MailScanner child telling us to shut down
            ExitLogging if /^EXIT$/;
            chop;
            push @in, $_;
        }
        my $data = join "", @in;
        my $tmp = unpack("u", $data);
        $message = thaw $tmp;

        next unless defined $$message{id};

        $mailWatchClient->send_api_message($message);

        # Unset
        $message = undef;
    }
}

sub EndMailWatchLogging {
    # Tell server to shut down. Another child will start a new server
    # if we are here due to old age instead of administrative intervention
    socket(TO_SERVER, PF_INET, SOCK_STREAM, getprotobyname("tcp"));
    my $addr = sockaddr_in($server_port, $loop);
    connect(TO_SERVER, $addr) or return;

    print TO_SERVER "EXIT\n";
    close TO_SERVER;
}

sub MailWatchLogging {
    my ($message) = @_;

    # Don't bother trying to do an insert if no message is passed-in
    return unless $message;

    # Fix duplicate 'to' addresses for Postfix users
    my (%rcpts);
    map { $rcpts{$_} = 1; } @{$message->{to}};
    @{$message->{to}} = keys %rcpts;

    # Get rid of control chars and fix chars set in Subject
    my $subject = fix_latin($message->{utf8subject});
    $subject =~ s/\n/ /g;  # Make sure text subject only contains 1 line (LF)
    $subject =~ s/\t/ /g;  # and no TAB characters
    $subject =~ s/\r/ /g;  # and no CR characters

    # Uncomment the following line when debugging SQLAllowBlockList.pm
    #MailScanner::Log::WarnLog("MailWatch: Debug: var subject: %s", Dumper($subject));

    # Get rid of control chars and tidy-up SpamAssassin report
    my $spamreport = $message->{spamreport};
    $spamreport =~ s/\n/ /g;  # Make sure text report only contains 1 line (LF)
    $spamreport =~ s/\t//g;   # and no TAB characters
    $spamreport =~ s/\r/ /g;  # and no CR characters

    # Get rid of control chars and tidy-up SpamAssassin MCP report
    my $mcpreport = $message->{mcpreport};
    $mcpreport =~ s/\n/ /g;  # Make sure text report only contains 1 line (LF)
    $mcpreport =~ s/\t//g;   # and no TAB characters
    $mcpreport =~ s/\r/ /g;  # and no CR characters

    # Workaround tiny bug in original MCP code
    my ($mcpsascore);
    if (defined $message->{mcpsascore}) {
        $mcpsascore = $message->{mcpsascore};
    } else {
        $mcpsascore = $message->{mcpscore};
    }

    # Set quarantine flag - This only works on MailScanner 4.43.7 or later
    my ($quarantined);
    $quarantined = 0;
    if ((scalar(@{$message->{quarantineplaces}}))
        + (scalar(@{$message->{spamarchive}})) > 0)
    {
        $quarantined = 1;
    }

    # Get timestamp, and format it so it is suitable to use with MySQL
    my ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime();
    my ($timestamp) = sprintf("%d-%02d-%02d %02d:%02d:%02d",
        $year + 1900, $mon + 1, $mday, $hour, $min, $sec);

    my ($date) = sprintf("%d-%02d-%02d", $year + 1900, $mon + 1, $mday);
    my ($time) = sprintf("%02d:%02d:%02d", $hour, $min, $sec);

    # Also print 1 line for each report about this message. These lines
    # contain all the info above, + the attachment filename and text of
    # each report.
    my ($file, $text, @report_array);
    while(($file, $text) = each %{$message->{allreports}}) {
        $file = "the entire message" if $file eq "";
        # Use the sanitised filename to avoid problems caused by people forcing
        # logging of attachment filenames which contain nasty SQL instructions.
        $file = $message->{file2safefile}{$file}
            if defined $message->{file2safefile}{$file};
        $text =~ s/\n/ /g;  # Make sure text report only contains 1 line (LF)
        $text =~ s/\t/ /g;  # and no TAB characters
        $text =~ s/\r/ /g;  # and no CR characters

        # Uncomment the following line when debugging MailWatch.pm
        #MailScanner::Log::WarnLog("MailWatch: Debug: VAR text: %s", Dumper($text));

        push (@report_array, $text);
    }

    # Sanitize reports
    my $reports = join(",", @report_array);

    # Uncomment the following line when debugging MailWatch.pm
    #MailScanner::Log::WarnLog("MailWatch: DEBUG: var reports: %s", Dumper($reports));

    # Fix the $message->{clientip} for later versions of Exim
    # where $message->{clientip} contains ip.ip.ip.ip.port
    my $clientip = $message->{clientip};
    $clientip =~ s/^(\d+\.\d+\.\d+\.\d+)(\.\d+)$/$1/;

    # Integrate SpamAssassin Allowlist/Blocklist reporting
    if ($spamreport =~ /USER_IN_WHITELIST/) {
        $message->{spamwhitelisted} = 1;
    }
    if ($spamreport =~ /USER_IN_BLACKLIST/) {
        $message->{spamblacklisted} = 1;
    }

    # Get the first domain from the list of recipients
    my ($todomain, @todomain);
    @todomain = @{$message->{todomain}};
    $todomain = $todomain[0];

    # Generate token for mail viewing
    my ($token, $sha1);
    $sha1 = Digest::SHA->new(1);
    $sha1->add($message->{id}, $timestamp, $message->{size}, $message->{headers});
    $token = $sha1->hexdigest;

    # Extract Message-ID from header
    my ($messageid, $inmessageid, $messageidbuffer);
    $messageid = "";
    $messageidbuffer = "";
    $inmessageid = 0;

    # Extract Message-ID from header (unfold if needed)
    foreach my $line (@{$message->{headers}}) {
        chomp $line;

        if ($line =~ /^message-id:\s*(.*)/i) {
            # Start Message-ID (value may be empty due to folding)
            $messageidbuffer = $1;
            $inmessageid = 1;

            # Uncomment the following line when debugging MailWatch.pm
            # MailScanner::Log::DebugLog("MailWatch: Found Message-ID header start: [%s]", $line);
            next;
        }
        elsif ($inmessageid) {
            if ($line =~ /^\s+(.*)/) {
                # RFC 5322 unfolding (MailScanner-safe)
                $messageidbuffer .= ' ' . $1;

                # Uncomment the following line when debugging MailWatch.pm
                # MailScanner::Log::DebugLog("MailWatch: Message-ID continuation line: [%s]", $line);
            } else {
                # Uncomment the following line when debugging MailWatch.pm
                #MailScanner::Log::DebugLog("MailWatch: End of Message-ID header");

                # End of Message-ID header
                last;
            }
        }
    }

    # Uncomment the following line when debugging MailWatch.pm
    # MailScanner::Log::DebugLog("MailWatch: Raw unfolded Message-ID buffer: [%s]", $messageidbuffer);

    # Normalize and extract the Message-ID value
    if ($messageidbuffer =~ /(<[^>]+>)/) {
        $messageid = $1;
        $messageid =~ s/^\s+|\s+$//g;

        # Uncomment the following line when debugging MailWatch.pm
        # MailScanner::Log::DebugLog("MailWatch: Extracted Message-ID value: [%s]", $messageid);
    }

    # Warn if Message-ID was not found
    if ($messageid eq "") {
      MailScanner::Log::WarnLog("MailWatch: Could not extract Message-ID for %s", $message->{id});
    }

    # Place all data into %msg
    my %msg;
    $msg{timestamp} = $timestamp;
    $msg{id} = $message->{id};
    $msg{size} = $message->{size};
    $msg{from} = $message->{from};
    $msg{from_domain} = $message->{fromdomain};
    $msg{to} = join(",", @{$message->{to}});
    $msg{to_domain} = $todomain;
    $msg{subject} = $subject;
    $msg{clientip} = $clientip;
    $msg{archiveplaces} = join(",", @{$message->{archiveplaces}});
    $msg{isspam} = $message->{isspam};
    $msg{ishigh} = $message->{ishigh};
    $msg{issaspam} = $message->{issaspam};
    $msg{isrblspam} = $message->{isrblspam};
    $msg{spamallowlisted} = $message->{spamwhitelisted};
    $msg{spamblocklisted} = $message->{spamblacklisted};
    $msg{sascore} = $message->{sascore};
    $msg{spamreport} = fix_latin($spamreport);
    $msg{ismcp} = $message->{ismcp};
    $msg{ishighmcp} = $message->{ishighmcp};
    $msg{issamcp} = $message->{issamcp};
    $msg{mcpallowlisted} = $message->{mcpwhitelisted};
    $msg{mcpblocklisted} = $message->{mcpblacklisted};
    $msg{mcpsascore} = $mcpsascore;
    $msg{mcpreport} = fix_latin($mcpreport);
    $msg{virusinfected} = $message->{virusinfected};
    $msg{nameinfected} = $message->{nameinfected};
    $msg{otherinfected} = $message->{otherinfected};
    $msg{reports} = fix_latin($reports);
    $msg{hostname} = $hostname;
    $msg{date} = $date;
    $msg{"time"} = $time;
    $msg{headers} = join("\n", map { fix_latin($_)} @{$message->{headers}});
    $msg{quarantined} = $quarantined;
    $msg{rblspamreport} = $message->{rblspamreport};
    $msg{token} = $token;
    $msg{messageid} = fix_latin($messageid);

    # Prepare data for transmission
    my $f = freeze \%msg;
    my $p = pack("u", $f);

    $mailWatchClient->send_local_message($p, $msg{id});
}

1;
