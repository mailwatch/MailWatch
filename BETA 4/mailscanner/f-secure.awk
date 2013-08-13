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
# 2003-11-27
# F-Secure status by Carl Boberg modified from Sophos status by Steve Freegard

BEGIN {
 FS = ": ";
 print "<table class=\"sophos\" cellpadding=\"1\" cellspacing=\"1\">";
 print " <tr>";
 print "  <th colspan=\"4\">F-Secure Information</th>";
 print " </tr>";
}

/F-Secure Anti-Virus Database/||/Copyright/ {
  print " <tr><td colspan=\"4\">"$1"</td></tr>";
}

/Frisk/ {
 print " <tr>";
 print "  <th colspan=\"4\">"$1"</th>";
 print " </tr>";
 print " <tr>";
 print "  <th>File</th>";
 print "  <th>Version</th>";
 print "  <th>Date</th>";
 print "  <th>Status</th>";
 print " </tr>";
}
/def/ {
split($1, array, " ");
 v_name = array[1];
 v_ver = array[2];
 v_date = array[3];
 v_status = array[4];
 print " <tr>";
 print "  <td>"v_name"</td>";
 print "  <td>"v_ver"</td>";
 print "  <td>"v_date"</td>";
 print "  <td>"v_status"</td>";
 print " </tr>";
}

/Kaspersky/ {
  print " <tr>";
  print "  <th colspan=\"4\">"$1"</th>";
  print " </tr>";
  print " <tr>";
  print "  <th>File</th>";
  print "  <th>Version</th>";
  print "  <th>Date</th>";
  print "  <th>Status</th>";
  print " </tr>";
}
/avc/ {
split($1, array, " ");
  v_name = array[1];
  v_ver = array[2];
  v_date = array[3];
  v_status = array[4];
  print " <tr>";
  print "  <td>"v_name"</td>";
  print "  <td>"v_ver"</td>";
  print "  <td>"v_date"</td>";
  print "  <td>"v_status"</td>";
  print " </tr>";
}
/End of/ {
  print " <tr>";
  print "  <td colspan=\"4\">"$1"</td>";
  print " </tr>";
}

/orion engine/ {
  print " <tr>";
  print "  <th colspan=\"4\">"$1"</th>";
  print " </tr>";
  print " <tr>";
  print "  <th>File</th>";
  print "  <th>Version</th>";
  print "  <th>Date</th>";
  print "  <th>Status</th>";
  print " </tr>";
}
/DAT/ {
  split($1, array, " ");
  v_name = array[1];
  v_ver = array[2];
  v_date = array[3];
  v_status = array[4];
  print " <tr>";
  print "  <td>"v_name"</td>";
  print "  <td>"v_ver"</td>";
  print "  <td>"v_date"</td>";
  print "  <td>"v_status"</td>";
  print " </tr>";
}


#End of DupDestroy
#F-Secure Corporation Orion engine version 1.02 build 25
#ORION.DAT version 2003-11-14 valid


END { print "</table>" }

