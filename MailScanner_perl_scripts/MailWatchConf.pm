#
# MailWatch for MailScanner
#

package MailScanner::CustomConfig;

use warnings;
use strict;

# Change the value below to match the MailWatch API base URL and API key
my ($api_base_url) = 'https://mailwatch.example.com'; # no trailing slash
my ($api_key) = 'my-api-key';
# Change the values below for retry logic (defaults: 5 retries, 5 seconds delay)
my ($api_max_retries) = 5;
my ($api_retry_delay) = 5;

# Change the values below to match the MailWatch database settings as set in conf.php
my ($db_name) = 'mailscanner';
my ($db_host) = 'localhost';
my ($db_user) = 'mailwatch';
my ($db_pass) = 'mailwatch';

# Change the value below for SQLSpamSettings.pm (default = 15)
my ($ss_refresh_time) = 15;       # Time in minutes before lists are refreshed

# Change the value below for SQLAllowBlockList.pm (default = 15)
my ($abl_refresh_time) = 15;      # Time in minutes before lists are refreshed


###############################
# don't touch below this line #
###############################

sub mailwatch_get_api_base_url { return $api_base_url };
sub mailwatch_get_api_key { return $api_key };
sub mailwatch_get_api_max_retries { return $api_max_retries };
sub mailwatch_get_api_retry_delay { return $api_retry_delay };

sub mailwatch_get_db_name { return $db_name };
sub mailwatch_get_db_host { return $db_host };
sub mailwatch_get_db_user { return $db_user };
sub mailwatch_get_db_password { return $db_pass };
sub mailwatch_get_ABL_refresh_time { return $abl_refresh_time };
sub mailwatch_get_SS_refresh_time { return $ss_refresh_time };

1;
