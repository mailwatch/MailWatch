#!/bin/bash
#
################################################################################
#
# mailwatch_ldap_sync.sh: A shell script to import Microsoft Exchange Users from
#                         Active Directory into the MailWatch user database.
#
# Version:                1.1
#
# Copyright (C) 2012  Daniel Himler  <d.himler@netsense.at>
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
################################################################################
#
# CHANGES
# =======
#
# Version 1.1
# -----------
# - Concatenate multiline results returned by ldapsearch
# - Decode Base64 strings returned by ldapsearch
# - Fix lookups of DNs with commas in their name
# - Exclude Exchange 2010 system mailboxes
# - Use mktemp for temporary file creation
#
# Version 1.0
# -----------
# - Initial Release

#################
# Configuration #
################################################################################

LDAP_URI="ldaps://gc.example.com:3269"
LDAP_BASE="DC=example,DC=com"
LDAP_USER="LDAPProxy@example.com"
LDAP_PASS="secret"
MYSQL_HOST="localhost"
MYSQL_PORT="3306"
MYSQL_NAME="mailscanner"
MYSQL_USER="mailwatch"
MYSQL_PASS="secret"

##################### DON'T TOUCH ANYTHING BELOW THIS LINE #####################

TEMPFILE="$(mktemp)"

LDAP_USERS="$(ldapsearch -LLL -H "$LDAP_URI" -D "$LDAP_USER" -w "$LDAP_PASS" -x -b "$LDAP_BASE" \
		"(&
		  (objectClass=user)
		  (proxyAddresses=*)
		 )" \
		"proxyAddresses" |
		sed -n '1 {h;$!d}; ${x;s/\n //g;p}; /^ /{H;d}; /^ /!{x;s/\n //g;p}' |
		sed -ne "s/^proxyAddresses: SMTP:\(.*\)/\1/p" |
		grep -Ev "DiscoverySearchMailbox|FederatedEmail|SystemMailbox" | sort)"

[ -f "$TEMPFILE" ] && rm -f "$TEMPFILE"
for USER in ${LDAP_USERS}; do
	unset DN FULLNAME ALIASES GROUP_ALIASES TYPE REPORT SPAMSCORE HIGHSPAMSCORE NOSCAN RECIPIENT RESULT
	USER="$(echo "$USER" | sed -e "s/\(.*\)/\L\1/")"
	RESULT="$(ldapsearch -LLL -H "$LDAP_URI" -D "$LDAP_USER" -w "$LDAP_PASS" -x -b "$LDAP_BASE" \
		"(proxyAddresses=SMTP:$USER)" \
		"dn" "displayName" "proxyAddresses" | sed -n '1 {h;$!d}; ${x;s/\n //g;p}; /^ /{H;d}; /^ /!{x;s/\n //g;p}')"
	echo "$RESULT" | grep -qs "dn:: "
	if [ "$?" -eq "0" ]; then
		DN="$(echo "$RESULT" | sed -ne "s/^dn:: \(.*\)/\1/p" | base64 -d)"
	else
		DN="$(echo "$RESULT" | sed -ne "s/^dn: \(.*\)/\1/p")"
	fi
	DN="$(echo "$DN" | sed -e "s/\\\,/\\\\\\\\,/g")"
	echo "$RESULT" | grep -qs "displayName:: "
	if [ "$?" -eq "0" ]; then
		FULLNAME="$(echo "$RESULT" | sed -ne "s/^displayName:: \(.*\)/\1/p" | base64 -d)"
	else
		FULLNAME="$(echo "$RESULT" | sed -ne "s/^displayName: \(.*\)/\1/p")"
	fi
	ALIASES="$(echo "$RESULT" | sed -ne "s/^proxyAddresses: smtp:\(.*\)/\1/p")"
	GROUP_ALIASES="$(ldapsearch -LLL -H "$LDAP_URI" -D "$LDAP_USER" -w "$LDAP_PASS" -x -b "$LDAP_BASE" \
		"(&(objectClass=group)(proxyAddresses=*)(member=$DN))" \
		"proxyAddresses" | sed -n '1 {h;$!d}; ${x;s/\n //g;p}; /^ /{H;d}; /^ /!{x;s/\n //g;p}' | sed -ne "s/^proxyAddresses: [sS][mM][tT][pP]:\(.*\)/\1/p")"
	eval $(echo "SELECT type, quarantine_report, spamscore, highspamscore, noscan, quarantine_rcpt FROM users WHERE username = '$USER';" |
		mysql -B -N --host="$MYSQL_HOST" --port="$MYSQL_PORT" --user="$MYSQL_USER" --password="$MYSQL_PASS" "$MYSQL_NAME" |
		sed -e "s/\(\S*\)\s\(\S*\)\s\(\S*\)\s\(\S*\)\s\(\S*\)\s\(\S*\)/TYPE=\1 REPORT=\2 SPAMSCORE=\"\3\" HIGHSPAMSCORE=\"\4\" NOSCAN=\"\5\" RECIPIENT=\"\6\"/")
	USER="$(echo "$USER" | sed -e "s/@/\\\@/g")"
	[ -n "$TYPE" ] || TYPE="U"
	[ -n "$REPORT" ] || REPORT="0"
	[ -n "$SPAMSCORE" ] || SPAMSCORE="0"
	[ -n "$HIGHSPAMSCORE" ] || HIGHSPAMSCORE="0"
	[ -n "$NOSCAN" ] || NOSCAN="0"
	[ -n "$RECIPIENT" ] || RECIPIENT="NULL"
	[ "$RECIPIENT" != "NULL" ] && RECIPIENT="'$RECIPIENT'"
	RECIPIENT="$(echo "$RECIPIENT" | sed -e "s/@/\\\@/g")"
	echo "REPLACE INTO users (username, password, fullname, type, quarantine_report, spamscore, highspamscore, noscan, quarantine_rcpt)
	VALUES ('$USER', NULL, '$FULLNAME', '$TYPE', $REPORT, $SPAMSCORE, $HIGHSPAMSCORE, $NOSCAN, $RECIPIENT);" >> "$TEMPFILE"
	echo "DELETE FROM user_filters WHERE username = '$USER';" >> "$TEMPFILE"
	for ALIAS in ${ALIASES} ${GROUP_ALIASES}; do
		ALIAS="$(echo "$ALIAS" | sed -e "s/\(.*\)/\L\1/" -e "s/@/\\\@/g")"
		echo "INSERT INTO user_filters (username, filter, active) VALUES ('$USER', '$ALIAS', 'Y');" >> "$TEMPFILE"
	done
done
MYSQL_USERS="$(echo "SELECT username FROM users WHERE password IS NULL;" |
	mysql --host "$MYSQL_HOST" \
		--port="$MYSQL_PORT" \
		--user="$MYSQL_USER" \
		--password="$MYSQL_PASS" \
		--skip-column-names "$MYSQL_NAME")"
for USER in ${MYSQL_USERS}; do
	unset DN RESULT
	RESULT="$(ldapsearch -LLL -H "$LDAP_URI" -D "$LDAP_USER" -w "$LDAP_PASS" -x -b "$LDAP_BASE" "(proxyAddresses=SMTP:$USER)" "dn" | sed -n '1 {h;$!d}; ${x;s/\n //g;p}; /^ /{H;d}; /^ /!{x;s/\n //g;p}')"
	echo "$RESULT" | grep -qs "dn:: "
	if [ "$?" -eq "0" ]; then
		DN="$(echo "$RESULT" | sed -ne "s/^dn:: \(.*\)/\1/p" | base64 -d)"
	else
		DN="$(echo "$RESULT" | sed -ne "s/^dn: \(.*\)/\1/p")"
	fi
	DN="$(echo "$DN" | sed -e "s/\\\,/\\\\\\\\,/g")"
	if [ "$DN" == "" ]; then
		echo "DELETE FROM user_filters WHERE username = '$USER';" >> "$TEMPFILE"
		echo "DELETE FROM users WHERE username = '$USER';" >> "$TEMPFILE"
	fi
done

mysql --host="$MYSQL_HOST" --port="$MYSQL_PORT" --user="$MYSQL_USER" --password="$MYSQL_PASS" "$MYSQL_NAME" < "$TEMPFILE"
RETVAL="$?"

rm -f "$TEMPFILE"

exit "$RETVAL"
