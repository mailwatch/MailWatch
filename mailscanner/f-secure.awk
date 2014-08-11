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
# 2003-11-27
# F-Secure status by Carl Boberg modified from Sophos status by Steve Freegard
# 2011-09-22
# Modified by Kevin Miller to work with F-Secure version 9.10

BEGIN {
 FS = ": ";
 print "<table class=\"sophos\" cellpadding=\"1\" cellspacing=\"1\">";
 print " <tr>";
 print "  <th>F-Secure Information</th>";
 print " </tr>";
}

/F-Secure Linux Security/ {
  print " <tr><td>"$1"</td></tr>";
  print " <tr><td>&nbsp;</td></tr>";
}

/F-Secure Security Platform/||/Command/||/Daemon/ {
    print " <tr>";
    print " <tr><td>"$1"</td></tr>";
    print " </tr>";
}

/Scanner Engine/ {
 print " <tr><td>&nbsp;</td></tr>";
 print " <tr>";
 print "  <th>"$1"</th>";
 print " </tr>";
}
/Libra engine/ {
 print " <tr>";
 print "  <td>"$1"</td>";
 print " </tr>";
}
/Libra database/ {
  print " <tr>";
  print "  <td>"$1"</td>";
  print " </tr>";
  print " <tr><td>&nbsp;</td></tr>";
}

/Orion engine/ {
  print " <tr>";
  print "  <td>"$1"</td>";
  print " </tr>";
}
/Orion database/ {
  print " <tr>";
  print "  <td>"$1"</td>";
  print " </tr>";
  print " <tr><td>&nbsp;</td></tr>";
}

/FPI Engine engine/ {
  print " <tr>";
  print "  <td>"$1"</td>";
  print " </tr>";
}
/FPI Engine database/ {
  print " <tr>";
  print "  <td>"$1"</td>";
  print " </tr>";
}
/Hydra engine/ {
  print " <tr>";
  print "  <td>"$1"</td>";
  print " </tr>";
}
/Hydra database/ {
  print " <tr>";
  print "  <td>"$1"</td>";
  print " </tr>";
}
/Aquarius engine/ {
  print " <tr>";
  print "  <td>"$1"</td>";
  print " </tr>";
}
/Aquarius database/ {
  print " <tr>";
  print "  <td>"$1"</td>";
  print " </tr>";
}
END { print "</table>" }
