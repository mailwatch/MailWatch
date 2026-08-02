#
# MailWatch for MailScanner
#

package MailScanner::CustomConfig;

use warnings;
use strict;

# Change the value below to match the MailWatch API base URL and API key
my ($api_base_url) = 'https://mailwatch.example.com'; # no trailing slash
my ($api_key) = 'my-api-key';
# Change the values below for retry logic (defaults: 5 retries, 5 seconds initial delay, 60 seconds maximum delay)
my ($api_max_retries) = 5;
my ($api_retry_delay) = 5;
my ($api_max_retry_delay) = 60;
# Failed API deliveries are persisted here and replayed in bounded batches
my ($api_spool_directory) = '/var/spool/MailScanner/mailwatch';
my ($api_spool_max_messages) = 10000;
my ($api_spool_replay_limit) = 10;
# Change the values below for the connection to the local MailWatch logging child
my ($local_logger_max_retries) = 3;
my ($local_logger_retry_delay) = 5;

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
sub mailwatch_get_api_max_retry_delay { return $api_max_retry_delay };
sub mailwatch_get_api_spool_directory { return $api_spool_directory };
sub mailwatch_get_api_spool_max_messages { return $api_spool_max_messages };
sub mailwatch_get_api_spool_replay_limit { return $api_spool_replay_limit };
sub mailwatch_get_local_logger_max_retries { return $local_logger_max_retries };
sub mailwatch_get_local_logger_retry_delay { return $local_logger_retry_delay };

sub mailwatch_get_ABL_refresh_time { return $abl_refresh_time };
sub mailwatch_get_SS_refresh_time { return $ss_refresh_time };

1;
