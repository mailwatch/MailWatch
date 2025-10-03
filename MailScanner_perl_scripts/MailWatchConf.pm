#
# MailWatch for MailScanner
#

package MailScanner::CustomConfig;

use warnings;
use strict;

# Change the values below to match the MailWatch database settings as set in conf.php
my ($db_name) = 'mailscanner';
my ($db_host) = 'localhost';
my ($db_user) = 'mailwatch';
my ($db_pass) = 'mailwatch';

# Change the value below for SQLSpamSettings.pm (default = 15)
my ($ss_refresh_time) = 15;       # Time in minutes before lists are refreshed

# Change the value below for SQLAllowBlockList.pm (default = 15)
my ($abl_refresh_time) = 15;      # Time in minutes before lists are refreshed

# Change the value below for MailWatch.pm only if the logging connections do not come from 127.0.0.1,
# e.g. in a FreeBSD jail without its own loopback: set it to the IPv4 address of the jail
my ($logging_ip) = '127.0.0.1';


###############################
# don't touch below this line #
###############################

sub mailwatch_get_db_name { return $db_name };
sub mailwatch_get_db_host { return $db_host };
sub mailwatch_get_db_user { return $db_user };
sub mailwatch_get_db_password { return $db_pass };
sub mailwatch_get_ABL_refresh_time { return $abl_refresh_time };
sub mailwatch_get_SS_refresh_time { return $ss_refresh_time };
sub mailwatch_get_logging_ip { return $logging_ip };

1;
