#
# MailWatch for MailScanner
# Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
# Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
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
# 2005-10-07
# McAfee status by Hubert Nachbaur modified from Sophos status by Steve Freegard

BEGIN {
 FS = ":: ";
 print "<table class=\"sophos\" cellpadding=\"1\" cellspacing=\"1\">";
 print " <tr>";
 print "  <th colspan=\"4\">McAfee Information</th>";
 print " </tr>";
}

{
  print " <tr><td>"$1"</td></tr>";
}

END { print "</table>" }

