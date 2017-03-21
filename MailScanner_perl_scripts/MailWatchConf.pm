#
# MailWatch for MailScanner
# Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
# Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
# Copyright (C) 2014-2017  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
#
#   Custom Module MailWatch
#
#   Version 1.5
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
# As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
# as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
# JpGraph.
#
# You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
# Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#

package MailScanner::CustomConfig;

use strict 'vars';
use strict 'refs';
no  strict 'subs'; # Allow bare words for parameter %'s

use vars qw($VERSION);

### The package version, both in 1.23 style *and* usable by MakeMaker:
$VERSION = substr q$Revision: 1.5 $, 10;

# Change the values below to match the MailWatch database settings
# as set in conf.php
my ($db_name) = 'mailscanner';
my ($db_host) = 'localhost';
my ($db_user) = 'mailwatch';
my ($db_pass) = 'mailwatch';

# Change the value below for SQLSpamSettings.pm (default = 15)
my ($ss_refresh_time) = 15;       # Time in minutes before lists are refreshed

# Change the value below for SQLBlackWhiteList.pm (default = 15)
my ($bwl_refresh_time) = 15;      # Time in minutes before lists are refreshed


###################################
#   Don't touch below this line   #
###################################

sub mailwatch_get_db_name { return $db_name };
sub mailwatch_get_db_host { return $db_host };
sub mailwatch_get_db_user { return $db_user };
sub mailwatch_get_db_password { return $db_pass };
sub mailwatch_get_BWL_refresh_time { return $bwl_refresh_time };
sub mailwatch_get_SS_refresh_time { return $ss_refresh_time };

1;
