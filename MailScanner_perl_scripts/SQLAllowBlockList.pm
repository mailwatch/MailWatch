#
# MailWatch for MailScanner
# Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
# Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
# Copyright (C) 2014-2024  MailWatch Team (https://github.com/mailwatch/MailWatch/graphs/contributors)
#
#   Custom Module SQLAllowBlockList
#
#   Version 1.8
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

use strict 'vars';
use strict 'refs';
no  strict 'subs'; # Allow bare words for parameter %'s

use vars qw($VERSION);

# Uncommet the folloging line when debugging SQLAllowBlockList.pm
#use Data::Dumper;

### The package version, both in 1.23 style *and* usable by MakeMaker:
$VERSION = '2.0';

use DBI;
use DBD::MariaDB;
my (%Allowlist, %Blocklist);
my ($atime, $btime);
my ($dbh);
my ($sth);
my ($SQLversion);

# Get database information from 00MailWatchConf.pm
use File::Basename;
my $dirname = dirname(__FILE__);
require $dirname.'/MailWatchConf.pm';

my ($db_name) = mailwatch_get_db_name();
my ($db_host) = mailwatch_get_db_host();
my ($db_user) = mailwatch_get_db_user();
my ($db_pass) = mailwatch_get_db_password();

# Get refresh time from from 00MailWatchConf.pm
my ($abl_refresh_time) =  mailwatch_get_ABL_refresh_time();

# Check MySQL/MariaDB version
sub CheckSQLVersion {
    # Prevent dying from failed db connection
    eval { 
        $dbh = DBI->connect("DBI:MariaDB:database=$db_name;host=$db_host",
            $db_user, $db_pass,
            { PrintError => 0, AutoCommit => 1, RaiseError => 1 }
        );
    };
    if ($@ || !$dbh) {
        MailScanner::Log::WarnLog("MailWatch: SQLAllowBlockList:: Unable to initialise database connection: %s", $DBI::errstr);
        return 1;
    }
    $SQLversion = $dbh->{mariadb_serverversion};
    $dbh->disconnect;

    return $SQLversion;
}

#
# Initialise SQL spam allowlist and blocklist
#
sub InitSQLAllowlist {
    MailScanner::Log::InfoLog("MailWatch: Starting up MailWatch SQL Allowlist");
    my $entries = CreateList('allowlist', \%Allowlist);
    MailScanner::Log::InfoLog("MailWatch: Read %d allowlist entries", $entries);
    $atime = time();
}

sub InitSQLBlocklist {
    MailScanner::Log::InfoLog("MailWatch: Starting up MailWatch SQL Blocklist");
    my $entries = CreateList('blocklist', \%Blocklist);
    MailScanner::Log::InfoLog("MailWatch: Read %d blocklist entries", $entries);
    $btime = time();
}

#
# Lookup a message in the by-domain allowlist and blocklist
#
sub SQLAllowlist {
    # Do we need to refresh the data?
    if ((time() - $atime) >= ($abl_refresh_time * 60)) {
        MailScanner::Log::InfoLog("MailWatch: Allowlist refresh time reached");
        InitSQLAllowlist();
    }
    my ($message) = @_;
    return LookupList($message, \%Allowlist);
}

sub SQLBlocklist {
    # Do we need to refresh the data?
    if ((time() - $btime) >= ($abl_refresh_time * 60)) {
        MailScanner::Log::InfoLog("MailWatch: Blocklist refresh time reached");
        InitSQLBlocklist();
    }
    my ($message) = @_;
    return LookupList($message, \%Blocklist);
}

#
# Close down the SQL allowlist and blocklist
#
sub EndSQLAllowlist {
    MailScanner::Log::InfoLog("MailWatch: Closing down MailWatch SQL Allowlist");
}

sub EndSQLBlocklist {
    MailScanner::Log::InfoLog("MailWatch: Closing down MailWatch SQL Blocklist");
}

sub CreateList {
    my ($type, $AllowBlock) = @_;
    my ($sql, $to_address, $from_address, $count, $filter);

    # Check if MySQL/MariaDB is >= 5.3.3
    my $version = CheckSQLVersion();

    # Database connection failed, so return 0 (count) and exit early
    if ($version == 1) {
        return 0;
    }

    eval {
        $dbh = DBI->connect("DBI:MariaDB:database=$db_name;host=$db_host",
            $db_user, $db_pass,
            { PrintError => 0, AutoCommit => 1, RaiseError => 1 }
        );
    };
    if ($@ || !$dbh) {
        MailScanner::Log::WarnLog("MailWatch: SQLAllowBlockList::CreateList::: Unable to initialise database connection: %s", $DBI::errstr);
        return 0;
    }
    $dbh->do('SET NAMES utf8mb4');

    # Uncommet the folloging line when debugging SQLAllowBlockList.pm
    #MailScanner::Log::WarnLog("MailWatch: DEBUG SQLAllowBlockList: CreateList: %s", Dumper($AllowBlock));

    # Remove old entries
    for (keys %$AllowBlock) {
        delete $AllowBlock->{$_};
    }
    
    $sql = "SELECT to_address, from_address FROM $type";
    $sth = $dbh->prepare($sql);
    $sth->execute;
    $sth->bind_columns(undef, \$to_address, \$from_address);
    $count = 0;

    while($sth->fetch()) {
        $AllowBlock->{lc($to_address)}{lc($from_address)} = 1; # Store entry
        $count++;
    }

    $sql = "SELECT filter, from_address FROM $type INNER JOIN user_filters ON $type.to_address = user_filters.username";
    $sth = $dbh->prepare($sql);
    $sth->execute;
    $sth->bind_columns(undef, \$filter, \$from_address);
    while($sth->fetch()) {
        $AllowBlock->{lc($filter)}{lc($from_address)} = 1; # Store entry
        $count++;
    }

    # Uncommet the folloging line when debugging SQLAllowBlockList.pm
    #MailScanner::Log::WarnLog("MailWatch: DEBUG SQLAllowBlockList: CreateList: %s", Dumper($AllowBlock));
    
    # Close connections
    $sth->finish();
    $dbh->disconnect();

    return $count;
}

#
# Based on the address it is going to, choose the right spam allow/blocklist.
# Return 1 if the "from" address is allow/blocklisted, 0 if not.
#
sub LookupList {
    my ($message, $AllowBlock) = @_;

    return 0 unless $message; # Sanity check the input

    # Find the "from" address and the first "to" address
    my ($from, $fromdomain, $toAdd, $todomainAdd, @todomain, $todomain, @to, $to, $ip, $ip1, $ip1c, $ip2, $ip2c, $ip3, $ip3c, $subdom, $i, @keys, @subdomains);
    $from = $message->{from};
    $fromdomain = $message->{fromdomain};
    # Create a array of subdomains for subdomain and tld wildcard matching
    #   e.g. me@this.that.example.com generates subdomain/tld list of ('that.example.com', 'example.com', 'com')
    $subdom = $fromdomain;
    @subdomains = ();
    while ($subdom =~ /.*?\.(.*)/) {
        $subdom = $1;
        push (@subdomains, "*.".$subdom);
    }

    @keys = ('default');
    @todomain = @{$message->{todomain}};
    @to = @{$message->{to}};
    foreach $toAdd (@to) {
        push (@keys, $toAdd);
    }
    foreach $todomainAdd (@todomain) {
        push (@keys, $todomainAdd);
    }
    $ip = $message->{clientip};
    
    # Match on leading 3, 2, or 1 octets
    $ip =~ /(\d{1,3}\.)(\d{1,3}\.)(\d{1,3}\.)/;  # get 1st three octets of IP
    $ip3 = "$1$2$3";
    $ip3c = substr($ip3, 0, - 1);
    $ip2 = "$1$2";
    $ip2c = substr($ip2, 0, - 1);
    $ip1 = $1;
    $ip1c = substr($ip1, 0, - 1);

    # $ip1, $ip2, $ip3 all end in a trailing "."

    # Precompute local part once
    my ($localpart) = split /@/, $from;

    # Precompute bounce@*.subdomain patterns
    my @patterns = map { $localpart . '@' . $_ } @subdomains;

    # It is in the list if either the exact address is listed,
    # the domain is listed,
    # the IP address is listed,
    # the first 3, 2, or 1 octets of the ipaddress are listed with or without a trailing dot
    # or a subdomain match of the form *.subdomain.example.com is listed
    foreach (@keys) {
        $i = $_;
        return 1 if $AllowBlock->{$i}{$from};
        return 1 if $AllowBlock->{$i}{$fromdomain};
        return 1 if $AllowBlock->{$i}{'@'.$fromdomain};
        return 1 if $AllowBlock->{$i}{$ip};
        return 1 if $AllowBlock->{$i}{$ip3};
        return 1 if $AllowBlock->{$i}{$ip3c};
        return 1 if $AllowBlock->{$i}{$ip2};
        return 1 if $AllowBlock->{$i}{$ip2c};
        return 1 if $AllowBlock->{$i}{$ip1};
        return 1 if $AllowBlock->{$i}{$ip1c};
        return 1 if $AllowBlock->{$i}{'default'};
        foreach my $sub (@subdomains) {
            return 1 if $AllowBlock->{$i}{$sub};            # *.subdomain
            return 1 if $AllowBlock->{$i}{$localpart.'@'.$sub}; # bounce@*.subdomain
        }
    }

    # It is not in the list
    return 0;
}

1;
