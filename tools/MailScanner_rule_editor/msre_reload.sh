#!/bin/bash
# $Id: msre_reload.sh,v 1.2 2004/07/29 20:34:53 jofcore Exp $
# 
# msre = MailScanner Ruleset Editor
# (c) 2004 Kevin Hanser
# Released under the GNU GPL: http://www.gnu.org/copyleft/gpl.html#TOC1
#
#    This program is free software; you can redistribute it and/or modify
#    it under the terms of the GNU General Public License as published by
#    the Free Software Foundation; either version 2 of the License, or
#    (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU General Public License for more details.
#
#    You should have received a copy of the GNU General Public License
#    along with this program; if not, write to the Free Software
#    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
if [ -e /tmp/msre_reload ];
then
    if [ -e /etc/init.d/mailscanner ]; then
        /etc/init.d/mailscanner restart >/dev/null
    else
        systemctl restart mailscanner
    fi
    rm -f /tmp/msre_reload
fi