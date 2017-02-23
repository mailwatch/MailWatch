#!/usr/bin/perl

# Change the values below to match the MailWatch database settings
# as set in conf.php
my ($db_name) = 'mailscanner';
my ($db_host) = 'localhost';
my ($db_user) = 'mailwatch';
my ($db_pass) = 'mailwatch';

###############################
# don't touch below this line #
###############################

sub get_db_name { return $db_name };
sub get_db_host { return $db_host };
sub get_db_user { return $db_user };
sub get_db_password { return $db_pass };
1;
