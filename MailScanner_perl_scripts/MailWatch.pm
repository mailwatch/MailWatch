#
# MailWatch for MailScanner
# Copyright (C) 2003  Steve Freegard (smf@f2s.com)
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#

package MailScanner::CustomConfig;

use strict;
use DBI;
use Sys::Hostname;
use Storable(qw[freeze thaw]);
use POSIX;
use Socket;
use Encoding::FixLatin qw(fix_latin);

# Trace settings - uncomment this to debug
# DBI->trace(2,'/root/dbitrace.log');

my($dbh);
my($sth);
my($hostname) = hostname;
my $loop = inet_aton("127.0.0.1");
my $server_port = 11553;
my $timeout = 3600;


# Modify this as necessary for your configuration
my($db_name) = 'mailscanner';
my($db_host) = 'localhost';
my($db_user) = 'mailwatch';
my($db_pass) = 'mailwatch';

 sub InitMailWatchLogging {
   my $pid = fork();
   if ($pid) {
     # MailScanner child process
     waitpid $pid, 0;
     MailScanner::Log::InfoLog("Started SQL Logging child");
   } else {
     # New process
     # Detach from parent, make connections, and listen for requests
     POSIX::setsid();
     if (!fork()) {
       $SIG{HUP} = $SIG{INT} = $SIG{PIPE} = $SIG{TERM} = $SIG{ALRM} = \&ExitLogging;
       alarm $timeout;
       $0 = "MailWatch SQL";
       InitConnection();
       ListenForMessages();
     }
     exit;
   }
 }

 sub InitConnection {
   # Set up TCP/IP socket.  We will start one server per MailScanner
   # child, but only one child will actually be able to get the socket.
   # The rest will die silently.  When one of the MailScanner children
   # tries to log a message and fails to connect, it will start a new
   # server.
   socket(SERVER, PF_INET, SOCK_STREAM, getprotobyname("tcp"));
   setsockopt(SERVER, SOL_SOCKET, SO_REUSEADDR, 1);
   my $addr = sockaddr_in($server_port, $loop);
   bind(SERVER, $addr) or exit;
   listen(SERVER, SOMAXCONN) or exit;

   # Our reason for existence - the persistent connection to the database
   $dbh = DBI->connect("DBI:mysql:database=$db_name;host=$db_host", $db_user, $db_pass, {PrintError => 0, AutoCommit => 1, RaiseError => 1, mysql_enable_utf8 => 1});
   if (!$dbh) {
    MailScanner::Log::WarnLog("Unable to initialise database connection: %s", $DBI::errstr);
   }

   $sth = $dbh->prepare("INSERT INTO maillog (timestamp, id, size, from_address, from_domain, to_address, to_domain, subject, clientip, archive, isspam, ishighspam, issaspam, isrblspam, spamwhitelisted, spamblacklisted, sascore, spamreport, virusinfected, nameinfected, otherinfected, report, ismcp, ishighmcp, issamcp, mcpwhitelisted, mcpblacklisted, mcpsascore, mcpreport, hostname, date, time, headers, quarantined) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)") or 
   MailScanner::Log::WarnLog($DBI::errstr);
 }


 sub ExitLogging {
   # Server exit - commit changes, close socket, and exit gracefully.
   close(SERVER);
   #$dbh->commit or die $dbh->errstr;
   $dbh->disconnect;
   exit;
 }

 sub ListenForMessages {
   my $message;
   # Wait for messages
   while (my $cli = accept(CLIENT, SERVER)) {
     my($port, $packed_ip) = sockaddr_in($cli);
     my $dotted_quad = inet_ntoa($packed_ip);

     # reset emergency timeout - if we haven"t heard anything in $timeout
     # seconds, there is probably something wrong, so we should clean up
     # and let another process try.
     alarm $timeout;
     # Make sure we"re only receiving local connections
     if ($dotted_quad ne "127.0.0.1") {
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

     # Check to make sure DB connection is still valid
     InitConnection unless $dbh->ping;

     # Log message
     $sth->execute(
      $$message{timestamp},
      $$message{id},
      $$message{size},
      $$message{from},
      $$message{from_domain},
      $$message{to},
      $$message{to_domain},
      $$message{subject},
      $$message{clientip},
      $$message{archiveplaces},
      $$message{isspam},
      $$message{ishigh},
      $$message{issaspam},
      $$message{isrblspam},
      $$message{spamwhitelisted},
      $$message{spamblacklisted},
      $$message{sascore},
      $$message{spamreport},
      $$message{virusinfected},
      $$message{nameinfected},
      $$message{otherinfected},
      $$message{reports},
      $$message{ismcp},
      $$message{ishighmcp},
      $$message{issamcp},
      $$message{mcpwhitelisted},
      $$message{mcpblacklisted},
      $$message{mcpsascore},
      $$message{mcpreport},
      $$message{hostname},
      $$message{date},
      $$message{"time"},
      $$message{headers},
      $$message{quarantined});

    # this doesn't work in the event we have no connection by now ?
    if (!$sth) {
     MailScanner::Log::WarnLog("$$message{id}: MailWatch SQL Cannot insert row: %s", $sth->errstr);
    } else {
     MailScanner::Log::InfoLog("$$message{id}: Logged to MailWatch SQL");
    }
    
    # Unset
    $message = undef;

   }
 }

 sub EndMailWatchLogging {
   # Tell server to shut down.  Another child will start a new server
   # if we are here due to old age instead of administrative intervention
   socket(TO_SERVER, PF_INET, SOCK_STREAM, getprotobyname("tcp"));
   my $addr = sockaddr_in($server_port, $loop);
   connect(TO_SERVER, $addr) or return;

   print TO_SERVER "EXIT\n";
   close TO_SERVER;
 }

 sub MailWatchLogging {
   my($message) = @_;

   # Don't bother trying to do an insert if  no message is passed-in
   return unless $message;

   # Fix duplicate 'to' addresses for Postfix users
   my(%rcpts);
   map { $rcpts{$_}=1; } @{$message->{to}};
   @{$message->{to}} = keys %rcpts;

   # Get rid of control chars and tidy-up SpamAssassin report
   my $spamreport = $message->{spamreport};
   $spamreport =~ s/\n/ /g;
   $spamreport =~ s/\t//g;

   # Same with MCP report
   my $mcpreport = $message->{mcpreport};
   $mcpreport =~ s/\n/ /g;
   $mcpreport =~ s/\t//g;

   # Workaround tiny bug in original MCP code
   my($mcpsascore);
   if (defined $message->{mcpsascore}) {
    $mcpsascore = $message->{mcpsascore};
   } else {
    $mcpsascore = $message->{mcpscore};
   }

   # Set quarantine flag - this only works on 4.43.7 or later
   my($quarantined);
   $quarantined = 0;
   if ( (scalar(@{$message->{quarantineplaces}})) 
      + (scalar(@{$message->{spamarchive}})) > 0 ) 
   {
   	$quarantined = 1;
   }

   # Get timestamp, and format it so it is suitable to use with MySQL
   my($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime();
   my($timestamp) = sprintf("%d-%02d-%02d %02d:%02d:%02d",
                           $year+1900,$mon+1,$mday,$hour,$min,$sec);

   my($date) = sprintf("%d-%02d-%02d",$year+1900,$mon+1,$mday);
   my($time) = sprintf("%02d:%02d:%02d",$hour,$min,$sec);

   # Also print 1 line for each report about this message. These lines
   # contain all the info above, + the attachment filename and text of
   # each report.
   my($file, $text, @report_array);
   while(($file, $text) = each %{$message->{allreports}}) {
     $file = "the entire message" if $file eq "";
     # Use the sanitised filename to avoid problems caused by people forcing
     # logging of attachment filenames which contain nasty SQL instructions.
     $file = $message->{file2safefile}{$file} or $file;
     $text =~ s/\n/ /;  # Make sure text report only contains 1 line
     $text =~ s/\t/ /; # and no tab characters
     push (@report_array, $text);
   }

   # Sanitize reports
   my $reports = join(",",@report_array);

   # Fix the $message->{clientip} for later versions of Exim
   # where $message->{clientip} contains ip.ip.ip.ip.port
   my $clientip = $message->{clientip};
   $clientip =~ s/^(\d+\.\d+\.\d+\.\d+)(\.\d+)$/$1/;

   # Integrate SpamAssassin Whitelist/Blacklist reporting
   if($spamreport =~ /USER_IN_WHITELIST/) {
    $message->{spamwhitelisted} = 1;
   }
   if($spamreport =~ /USER_IN_BLACKLIST/) {
    $message->{spamblacklisted} = 1;
   }

   # Get the first domain from the list of recipients
   my($todomain,@todomain);
   @todomain = @{$message->{todomain}};
   $todomain = $todomain[0];

   # Place all data into %msg
   my %msg;
   $msg{timestamp} = $timestamp;
   $msg{id} = $message->{id};
   $msg{size} = $message->{size};
   $msg{from} = $message->{from};
   $msg{from_domain} = $message->{fromdomain};
   $msg{to} = join(",", @{$message->{to}});
   $msg{to_domain} = $todomain;
   $msg{subject} = fix_latin($message->{utf8subject});
   $msg{clientip} = $clientip;
   $msg{archiveplaces} = join(",", @{$message->{archiveplaces}});
   $msg{isspam} = $message->{isspam};
   $msg{ishigh} = $message->{ishigh};
   $msg{issaspam} = $message->{issaspam};
   $msg{isrblspam} = $message->{isrblspam};
   $msg{spamwhitelisted} = $message->{spamwhitelisted};
   $msg{spamblacklisted} = $message->{spamblacklisted};
   $msg{sascore} = $message->{sascore};
   $msg{spamreport} = $spamreport;
   $msg{ismcp} = $message->{ismcp};
   $msg{ishighmcp} = $message->{ishighmcp};
   $msg{issamcp} = $message->{issamcp};
   $msg{mcpwhitelisted} = $message->{mcpwhitelisted};
   $msg{mcpblacklisted} = $message->{mcpblacklisted};
   $msg{mcpsascore} = $mcpsascore;
   $msg{mcpreport} = $mcpreport;
   $msg{virusinfected} = $message->{virusinfected};
   $msg{nameinfected} = $message->{nameinfected};
   $msg{otherinfected} = $message->{otherinfected};
   $msg{reports} = $reports;
   $msg{hostname} = $hostname;
   $msg{date} = $date;
   $msg{"time"} = $time;
   $msg{headers} = fix_latin(join("\n",@{$message->{headers}}));
   $msg{quarantined} = $quarantined;

   # Prepare data for transmission
   my $f = freeze \%msg;
   my $p = pack("u", $f);

   # Connect to server
   while (1) {
     socket(TO_SERVER, PF_INET, SOCK_STREAM, getprotobyname("tcp"));
     my $addr = sockaddr_in($server_port, $loop);
     connect(TO_SERVER, $addr) and last;
     # Failed to connect - kick off new child, wait, and try again
     InitMailWatchLogging();
     sleep 5;
   }

   # Pass data to server process
   MailScanner::Log::InfoLog("Logging message $msg{id} to SQL");
   print TO_SERVER $p;
   print TO_SERVER "END\n";
   close TO_SERVER;
}

1;
