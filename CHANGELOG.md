# Changelog

## Unreleased

## 1.2.18
### Improvements
- Add DB_PORT config value to specify a non-standard MySQL server port (#1213)
- Add new config flag to permit IMAP login without full email as username (#1211)
- Add support for F-Secure 12 antivirus
- Add support for ESET File Security antivirus

### Fixes
- Fix errors on importing messages from MailScanner containing UTF8 chars in messageid and reports (#1208)
- Fix upgrade script

## 1.2.17
### Improvements
- Log failed login attempts to php error log with clients ip (#1202)

### Fixes
- Upgrade rule_desc column length to 512 chars in sa_rules and mcp_rules tables
- Fix mailwatch_sendmail_relay.php coding problems (#1206)
- Quarantine reports to include multiple recipients (#1194)
- Fix undefined offset in reports page (#1200)
- Convert special html chars to html entities when importing SpamAssassin rules description
- Improve terminology used
- Fix SpamAssassin rule descriptions sorting
- Fix tools/Sendmail-Exim_queue/mailq.crontab and updated related documentation

## 1.2.16
### Compatibility
- Permit Sendmail 15-chars-long MessageId in input validation (#652)

### Fixes
- Fix PHP 7 compatibility in mailwatch_quarantine_report script (#1167)
- Add missing translation entry for Latin American Spanish
- Fix quote handling in mailwatch_milter_relay.php (#1170)
- Use Digest::SHA instead of Digest:SHA1 in MailWatch.pm (#1190)

### Improvements
- Add support for IPV6 clients in audit_log table (#1178)
- Better handling of queuedir (#1173)
- Add row hover effect in rep_total_mail_by_date report
- Add tooltips on truncated fields

## 1.2.15
### Compatibility
- Add MaxMind License Key support (#1166) 

### Fixes
- Adjust avast virus regex (#1133)
- Set RPC Server internal and response charset encoding to UTF-8 (#1156)

### Improvements
- Added Avast support (#1117)

## 1.2.14
### Fixes
- Allows domain admins to view black and whitelist for filtered domains (#1154)
- Allow passing the argument --max-size 0 to sa-learn (#1160) 

### Improvements
- More user friendly way to deal with token failures across the board

## 1.2.13
### Security
- Clear password in database on imap auto created users during upgrade

### Improvements
- Add notice if session timed out while on login page (#1120) 
- Show email release status on quarantine page(#1123)
- MailScanner milter relay stats support (#1124) 
- Hide Administrator user type to Domain Admins when creating or editing users (#276)
- Add Latin American Spanish translation

### Fixes
- Fix group by in total_mail_by_date (#1127)
- Fix php notice on GeoIP update cronjob (#1131)
- Fix PHP notice on checklogin.php when using Imap login

## 1.2.12
### Security
- Fix clear password display when user is autocreated by IMAP auth

### Fixes
- Add client IP in sorting input validation

## 1.2.11
### Improvements
- Milter enhancement for MailWatch (#1106)
- Big update to Brazilian translation
- Better count of Postfix processes
- Upgrade pear/Mail_Mime to 1.10.2

### Fixes
- Fix printing multiple mails on detail.php (#1100)
- Truncate long Top Virus output (#1112)
- Fix long queue id validation (#1105)
- Redirect if login page session expired (#1111)
- Fix printing multiple mails on detail.php (#1100)
- Remove empty column on rep_total_mail_by_date.php (#1095, #1096)

## 1.2.10
### Compatibility
- Disable geoip for php <5.4 (#1075)

### Fixes
- Fix version display for geoip (#1063)
- Improve MaxMindDB version detection
- Fix schema for user table to not allow empty type (#1067
- Simplify ClamAV version detection (#1060)
- Detect failed collation change on db connect and set manually (#1078)
- Fix mail filter for normal users (#1084)
- Fix missing translation in quarantine report emails (#1090)

## 1.2.9
### Compatibility
- Minimal PHP version is now 5.4. Older versions of 5.3.x or lower do not work correctly with GeoLite2 (#1073)

### Improvements
- Use new GeoLite2 database to replace deprecated GeoLite Legacy databases

### Fixes
- Parse correctly GPG signed e-mail (#1053)
- Send empty quarantine reports over user_manager (#1054)

## 1.2.8
### Improvements
- Add ldap debug script
- Update documentation
- Improve sudo config to work with Postfix and Exim
- Add mailwatch_update_sarules.php cronjob script
- Use MAILWATCH_SMTP_HOSTNAME if defined on Release from quarantine (#1038)
- Enable use of dn field as username field (#427, #1029)

### Fixes
- Fix Postfix log processor (#1021)
- Make msre reload script work on systemd
- Fix error on undefined $_SERVER['HTTPS'] index
- Fix logout process (#1044)
- Fix email validation for username (#1042)

## 1.2.7
### Security
- More restricted access to library and public available files (#990)
- Fix email blacklist bypass when an email with more than 2 normal recipient is processed (#255, #992)

### Improvements
- Add check for path of postconf, exim and sendmail executables in sf_version.php (#948)
- Enable use of '&' in username (#964)
- Add f-prot 6 virus scanner support
- Enlarge localhost ip detection to full 127.0.0.0/8 class
- Add IMAP auth support (#961)
- Better support for Hebrew charset
- Add Japanese language translation
- Updated translations
- Some code refactoring

### Fixes
- Uniform use of IMAGES_DIR as a relative path instead of an absolute path (#944)
- Fix admins editing domain admins
- Fix LDAP sAMAccountname not being used for login (#955)
- Fix domain admins not being able to change own password
- Fix ONLY_FULL_GROUP_BY MySQL error (#733)
- Fix UTF8 headers in viewmail
- Update sudoers file to use mailq to match conf.php.example

## 1.2.6
### Security
- Restrict domain admin permission so that they can only modify/create/delete regular users. Also, emails must be used for all non-admin accounts (#940)

### Improvements
- Add entries counter on white and black list (#509)
- Changed character set used in quarantine release email to UTF-8 (#910)
- Upgrade.php alters tables only if needed
- ### Fixes for dangerous content display on detail.php (#939)

### Fixes
- Fix upgrade script for ### Compatibility with MySQL 5.7 and 8.0 strict SQL mode
- Fix PHP header warning for cli scripts
- Fix invalid colors for multiple y-axes in a line graph (#926)
- Remove wrong alter table on audit_log.user which revert length to 255 from 191
- Convert delay from seconds to mysql time format in MTA log processor (#924, #941)

## 1.2.5
### Improvements
- Support for multiple virus scanners and multiple top viruses (#874)
- Add detection of MySQL or MariaDB in upgrade process (#873)
- Prevents Mail Queue summary from duplicating mail count when local server is defined in RPC list (#904, #905)
- Enhance upgrade.php with check for conf.php syntax and MailScanner.conf existence
- Better UI on tables and graphs
- Improved translations

### Fixes
- ### Fixes issue when sanitization causes ampersand to be html-ified (#882)
- Fix mailwatch-sendmail-relay init script (#881)
- Fix code for php 5.3 ### Compatibility (#889) and php 7.0 ### Compatibility (#897)
- Fix path to mtalogprocessor file in senmail_relay (#912)
- Remove on update/default value for timestamp in maillog table (#915)
- Permit up to 20 chars in first part of Postfix msgid (#652)

## 1.2.4
### Improvements
- Converted remaining graph to Chart.js and removed JpGraph dependency
- Add OS detection in Software Version page
- Add geoip_update cron script
- New graph: Messages per Hour for the last 24 hours
- Recognition of Message/Partial as attachment in view mail
- Beautify error message on database exception
- Add visual display for released and learned messages
- Add a comunity code of conduct
- Localization updates

### Fixes
- Fix wrong MAILWATCH_SMTP_HOSTNAME defined check
- Correct some graph generation
- Fix timestamp field autoupdating in maillog table
- Fix status mail queues in MailWatch cluster

## 1.2.3
### Improvements
- Better ### Compatibility for MySQL 5.7 (ONLY_FULL_GROUP_BY)
- Disable broken VIRUS_INFO per default
- Better handling of Postfix message ids
- Allow plus sign in username
- Add ability to send quarantine report to single user
- Enable users to immediately send his own quarantine report
- Add ability for users to select the language of the gui
- Converted some graph to Chart.js
- Warn about installing PHP XML extension where not present
- Add option to specify HELO hostname for SMTP transactions
- Improved Sendmail queue code 
- Allow blacklisting and whitelisting an entire TLD
- Use domain admin username as domain filter
- Database driven session enhancements
- Add per user session timeout
- Provides visual display of release and learned messages
- Enhanced detail page printing
- Enhanced upgrade.php
- Localization updates
- Code refactoring to clean up duplication and code smell

### Fixes
- Ignores MailScanner config files (conf.d/*) in subfolders and hidden files
- Options in messages operations do not select all the lines when clicking S/H/F/R
- Fix issue where Mail Queue on status page displayed intermittently
- Fix sa-learning in languages containing special chars in submit text (e.g. German)
- Fix session conflict and multitab surfing
- Fix broken html with certain virus names
- Fix duplication of default rule in msre_edit.php
- Fix errors with /etc/cron.daily/mailwatch scripts
- Create.sql can be run on MySQL <= 5.5 again
- Fix &amp; encoding in links
- Fix SA-Learn blocking apache server

## 1.2.2
### Fixes
- Regression in SA Learning and Releasing not working in detail page

## 1.2.1
### Improvements
- Show RBL list in Message Detail
- Better decoding of sender and subject in Mail Queue
- Disable browser caching to prevent token mismatch
- Add unique token to logged emails
- Clean orphaned filter in upgrade.php
- Add optional skinning of web interface
- Moved upgrade doc to online documentation
- Improved performance
- Enhanced session expiration
- Prevents domain admins from editing their own filters
- Allows domain admins to view,edit,add,delete users in their filter domains
- Code refactoring to clean up duplication and code smell

### Fixes
- Fix regression on some input validation (the Bad Dog Biting Bug™)
- Rename 00MailWatchConf.pm to MailWatchConf.pm, which is failing on some perl versions
- Fix Audit log access
- Don't permit logged in Admin/Domain admin to delete his/her user
- Fix MailWatchConf.pm loading on some platforms
- Remove double ldap_escape on username
- Fix upgrade.php failing on some MariaDB versions
- Fix MIME part visualization
- Fix session loop

## 1.2.0
- Multiple vulnerability fixed
- Fixed invalid mime message parsing
- Improved upgrade.php script
- Update filters when user is deleted or renamed
- Provides administrator the ability to combine quarantine reports into a single report
- Better support for remote postfix queue count
- Code refactoring and fixes

## 1.2.0 - RC5
 - Add the ability for domain admins to create/edit/delete users of the same domain
 - Reorganization of Tools directory
 - Add filter on audit report
 - Add the possibility to 'Clear' bayes database in Bayes Info page
 - Add password reset
 - Move database setup in separate perl module (00MailWatchConf.pm)
 - Improved upgrade.php script 
 - Improve compatibility with MailScanner V5
 - Improve IPv6 support
 - Improve UTF8 compatibility and add support for utf8mb4 in perl module
 - Improve LDAP compatibility
 - Add better Exim queue count
 - Fix printing in some reports
 - Fix for auto/unknown Virus scanner
 - A lot of cosmetic enhancement
 - Code cleanup
 - Updated translations
 
## 1.2.0 - RC4
 - Move to MySQLi PHP extension
 - PHP 7 compatibility
 - Use utf8mb4 on capable systems (MySQL >= 5.5.3)
 - Fix geoip function dereclaration
 - Warn on missing sa-learn binary
 - Add SA_PREFS to list of SpamAssassin rules directory
 - Improve session cookie security
 - Upgrade pear packages
 - Filter LDAP username and password before passing them to LDAP server
 - Improve LDAP compatibility with server other than Active Directory
 - Remove password change functionality for LDAP Domain Admin and User
 - Permit LDAP login with password containing special characters
 - Code cleanup
 - Updated translations

## 1.2.0 - RC3
 - Update postfix relay documentation (GH #320)
 - Update JpGraph to 4.0.1 (GH #289)
 - Fix Reports.php and Lists.php when used in languages other than English (GH #307, GH #288)
 - Proper expansion multiple %var% references in MailScanner parameters (GH #311)
 - Updated traslation
 - Add HIDE_UNKNOWN config option (GH #240, GH #254)
 - Add Autorelease feature (one click release of quarantined emails) (GH #260)
 - Fix per user spam score defaults (GH #263)

## 1.2.0 - RC2
 - Fix name collision in queries (GH #243)
 - Fix loading of SpamAssassin rule description if it starts with a space (GH #242)
 - Fix login bypass on LDAP introduced in RC1 (GH #246, GH #248)
 - Improve rep_total_mail_by_date report (GH #249, GH #250, GH #251)
 - Fix LDAP search for 'mail' prefix (GH #252)
 - Try to not encode multiple times a string that is already UTF8 (GH #225)
 
## 1.2.0 - RC1
 - Display load average if /proc/loadavg doesn't exists but /usr/bin/uptime does
 - Improve loggings for connections not coming from 127.0.0.1
 - Add hide High Spam and High MCP options
 - Update geoip.inc to v1.15
 - Fix path in the install manual
 - Add Microsoft Active Directory compatibility support
 - Fix timezone warning on sf_version and viewmail
 - Check GeoIP data file size before using GeoIP functions
 - Add hide Non Spam option
 - Quick hack on fixing duplicate header issue (#154)
 - Fix reports graph color management
 - A better sendmail_relay init file
 - Fix GeoIP extension and php libary conflict on constants definition
 - get_conf_truefalse returns true if value is a string
 - Enable LDAP over SSL
 - Fix virus count sorting on "Virus Report"
 - Remove additional slashes in "SpamAssassin Rule Description Update"
 - Adding translation to user interface
 - Add LOGO path in conf.php.example and changes done in corresponding files.
 - Layout changes in quarantine.php, other.php, sf_version.php
 - Removed duplicate PHP function in tools/Cron_jobs/quarantine_report.php (is in functions.php)
 - Changes in tools/Sendmail_relay/INSTALL (to be accurate with Debian/Ubuntu)
 - Changes in tools/Sendmail_relay/sendmail_relay.init (change maillog to mail.log)
 - Background color change in login.php
 - Add clamav and spamassassin version in sf_version.php
 - Changes in INSTALL and UPGRADING (to be accurate with Debian/Ubuntu)
 - Modified footer function in functions.php (page footer and DEBUG for page_creation_timer)
 - Add a check for 'subtests=' to add space after comma (to fit the screen) in sa_lint.php
 - Upgrade password hash function from MD5 to a crypt() compatible one
 - Add option to enable/disable IP address resolution on status page
 - Improving upgrade.php script
 - Sanitize user input on reports
 - Rewrite of GeoIP file download procedure
 - Decline login as LDAP group
 - Move luser add-on to another git repository
 - Better logging of UTF8 subjects

## 1.2.0 - Beta 8
 - Correct unexpected behaviour in viewpart.php if one or more headers are not set
 - Refactor message part viewer
 - Sanitize user input
 - Hide MCP-related fields if MCP is not enabled in MailScanner

## 1.2.0 - Beta 7
 - Fix documentation regarding magic_quotes_gpc
 - Redirect to original link after login (e.g. from quarantine report)
 - Fix to GPL v2 licensing problems
 - Updated MailScanner default path to comply with version 4.85.2-1
 - Add `--max-size` support to `sa-learn` if spamassassin >= 3.4.0
 - Separate release action in Message Operations from Spam/Ham/Forget radio button
 - Domain Administrator get all domain emails if their username is either an email address or a domain name

## 1.2.0 - Beta 6
 - Enhanced MailScanner.conf parser to catch variable's value even if there is no space before and/or after = sign
 - Added DISPLAY_IP option to show sender's IP Address in Quarantine listings and message lists
 - Enhanced SQL Black/Whitelist to allow matching 1, 2, or 3 IP address octets
 - Enhanced SQL Black/Whitelist to allow matching of subdomains
 - Upgraded PEAR packages to last stable versions (some trunk commit was used)
 - Enable UTF-8 subject encoding
 - Fix conflict with GeoIP PHP extension

## 1.2.0 - Beta 5
 - Reorganized all libraries to lib directory, removed fpdf (which was not used) and removed lib's symlinks
 - Moved to GeoIP binary data file, dropped CSV import to database
 - Added a layer of security to cronjob, which don't execute if needed variables aren't set
 - Improved compatibility with PHP 5.5
 - New login form design
 - Added LDAP mail field variable to be compatible with more systems
 - Corrected free disk space calculation
 - Fixed Domain User operation on white/black lists
 - Added the ability to show/hide the software version page
 - Added MailScanner Rule Editor functionality from http://msre.sourceforge.net/
   Fixes over original MSRE:
   -  Requires authenticated MailWatch Admin user
   -  Rules with 'action' of 0 are now correctly handled (as in size rules).
   -  Rule descriptions start immediately after the # character. This stops MSRE from chopping off 1st character of comments which weren't generated by MSRE.
   -  Treat blank lines in rules as comments
   -  Sort rule file names into alphabetical order before listing
   -  Allow "FromAndTo:" keyword in rule files
   -  Allow "Virus:" keyword in rule files
   -  Fix very broken 'and' handling
   -  Ensure that the 'default' rule stays the default rule forever
   -  Fix handling of escape characters in posted form
   -  Strip spaces from entered Target and AndTarget fields
   -  Fix case-sensitivity in keywords like "FromOrTo:"
   -  Use MailScanner's rule keyword matching algorithm
   -  Tighten up input validation based on fixes in the original MSRE 0.2.3 CVS
   -  Tighten up rule parsing to ensure that generated rules are complete
 - Fixed AutoCommit error on MailWatch.pm
 - Better logout process
 - Hiding chroot mounts from drives list
 - Better directory traversal prevention
 - Fixed CVE-2008-5991
 - Corrected html and javascript errors
 - Removed W3C and SourceForge logos from footer
 - Fixed spelling mistakes/typos
 - Code cleanup

## 1.2.0 - Beta 4 patch 4
 - Fixed create.sql for some incorrect parts
 - Fixed incorrect word in INSTALL file
 - Fixed deprecated mysql_escape_string for users with newer php versions, files: sa_rules_update.php,mcp_rules_update.php,
   rpcserver.php,postfix_relay.php,quarantine.php,filter.inc,user_manager.php,viewpart.php,viewmail.php,functions.php,
   lists.php
 - Updated functions.php

## 1.2.0 - Beta 4
 - Fix the mime.php due to a mistype
 - Fix geoip_update.php for a mistype

## 1.2.0 - Beta 3
 - Fix for XSS issue

## 1.2.0 - Beta 2
 - Fix for CentOS 6 not respecting issues with ./

## 1.2.0 - Beta 1
 - Fixed db_clean.php to remove items correctly.
 - Add software version page
 - Added current user and system time table
 - Values from the MailScanner include directory are respected throughout the product
 - Added LDAP Support
 - Corrected issues for PHP5
 - New pagination function that is cleaner and faster
 - Now using MDB2
 - HTML4.0 Strict
 - Changed the way the black and white list work
 - Code Cleaned up

## 1.1.5.1
 - Fixed viewpart.php to correct multitenancy issues
 - Fixed all pages that use nl2br to be more compatiable with older versions of php
 - Fixed geoip_update.php to use ./temp again and to clean up variables
 - Added IPv6 support to geoip_update.php

## 1.1.5
 - Fixed a syntax issue on rep_virus.php and quarantine_action.php
 - Add some checking to detail.php to see if the mtalog_ids are being used with postfix.
 - Permissions check on geoip_update.php were added to verify that it can download and untar the geoip Zip file.
 - Change geoip_update.php to use /tmp rather than the mailscanner temp folder.
 - Corrected permissions to only check to see if postfix has read permissions on the queue directories
 - Fixed all html for 4.01 transitional for prep to move to html5
 - Fixed page caching issue
 - Fixed an issue with the db_clean script
 - Navigation bar change

## 1.1.4
 - Fixed DB clean to clean the mtalog and mtalog_ids
 - Added better error reporting for the Reports page.
 - Fixed a table issue on reports.php
 - Changed geoip_update.php to curl for security and functionality

## 1.1.3
 - Changed login check to required for security reasons
 - Applied fix from Kia for id= on postfix_relay.php, mailscanner_relay.php, and sendmail_relay.php
 - Changed login.function.php to require_once from include.
 - Old bugs fixed
 - Removed dbclose() from login page since a DB call is never made BUG#3458400
 - Fixed issue where non-admin users could see the av status BUG#3457391
 - Fixed issue of get_mail_relay function not accepting IPv6 BUG#2775557
 - Fixed an issue where non admins could see Today's Totals system wide rather than for just there filter
 - Add drive space left for Admins
 - Added permissions check to Queue Status

## 1.1.2
 - Reports update
 - Time Zone variable added conf.php
 - MailScanner log and Mail log variables added to conf.php
 - reports updated to actually create the image
 - cache image clean up after 60 seconds
 - The reports now use the date format set in the conf.php
 - JPgraph fixed and update correctly
 - Geoip corrected
 - functions.php footer functions has been updated to remove depercated html functions
 - License and Copyright updated on all of the pages
 - pear DB updated
 - More files have been Documented
 - Code cleaned up

## 1.1.1
 - Cleaning up the code and fixing report bugs
 - Adding documentation to the code
 - Postfix relay support for the detailed page
 - Mcafee 6 support
 - SQLBlackWhitelist.pm to include per user filtering
 - Quarantine page updated to remove none numeric folder names
 - Code to close to the DB connection on everypage
 - Authenication fixed on everypage
 - Auditting will now pull the name of the username
 - db_clean interval updated with number of days to keep in conf.php
 - Split out install information for tools

## 1.1
 - MailWatch can now monitor postfix
 - Mailscanner --lint form the MailWatch
 - new jpgraph
 - updated xmlrpc
 - new auth verification
 - login page
 - Some code was streamlined

## 1.0.1
 - Better error reporting in MailWatch.pm (thanks to Paddy for this).
 - SECURITY UPDATE - updated XMLRPC libraries due to security vulnerability -
   see http://www.gulftech.org/?node=research&article_id=00088-07022005.
 - Bug fixes in the tools

## 1.0
 - MCP Support
 - User Management (create users GUI) with better filtering to allow per-user/per-domain support.
 - Audit logging
 - XML-RPC web services for running multiple MailScanner/MailWatch boxes with a single database allowing quarantine
   view/release from any front-end.
 - Enhanced reporting of MTA deliveries/rejections - the total mail by date will report unkown user, RBL, unknown domain
   rejection (sendmail only).
 - Better query builder for reports - allows you to select the same row more than once to do things like
   (date >= yyyy-mm-dd AND date <= yyyy-mm-dd), you can also use MySQL functions by putting a '!' in front e.g. DATE is
   equal to !CURRENT_DATE() will always return today's date. You can also save common queries for reuse again.
 - Quarantine Report (similar to the Fortress Systems scripts except generated using the MailWatch database and gives
   links to MailWatch to view/release.)
 - Integrated Blacklist/Whitelist - allow you to maintain per-user/per-domain/global blacklist/whitelists.
 - New MailWatch.pm to overcome newer DBI/DBD-MySQL problems present in the previous version (thanks for Walker Aumann
   for this).
 - GeoIP country lookups in message detail and reports.
 - **Lots** of fixes/updates

## 0.5
 - Updated indexes for much greater performance (again!).
 - Added preliminary support for per-user filters (see USER_FILTERS file).
 - Added the ability to view quarantined items.
 - All tables now enable a pager when returning more than 50 rows and allow ordering by any of the displayed columns.
 - New tool to run SpamAssassin --lint and time the output for debugging SA.
 - New F-Secure status page (like Sophos).
 - Required PEAR modules now included.
 - Added reporting of Blacklisted mails.
 - Integrated the reporting of SpamAssassin Blacklisted/Whitelisted e-mails.
 - Quoted printable strings are now automatically decoded before display.
 - Configuration options moved from functions.php into conf.php
 - Automatically works out VIRUS_REGEX by using the first value in MailScanner.conf - e.g. 'Virus Scanners = sophossavi
   clamavmodule' would activate the regexp for SophosSAVI.
 - New 'Virus Report' allows comparison of multiple scanners (if you run more than one) and allows you to see 1st
   detection date/time of each virus by each scanner.
 - Integration with Fortress Systems Secure Mail Gateway.

 ### Fixes
 - Multiple clean-ups of mailq.php to make it more robust.
 - Greatly improved debugging of SQL statments.
 - Quarantine now correctly looks in the non-spam quarantine directories.
 - SA Rules Description Update now reads custom rules as well.
 - sendmail_relay.php now works across log rotations.
 - Increased memory_limit to 128M for quarantine functions.

## 0.4
 - Changed index on maillog table for *much* greater performance.
 - Fixed quarantine release where if message was large would cause PHP to display a memory exhausted error.
 - Fixed a bug in mailq.php that caused it not to work on earlier PHP versions.
 - Updated rep_total_mail_by_date.php to include spam and virus percentage.
 - Fixed a bug in MailWatch.pm when database ping fails and reconnection is attempted.
 - Added new config variable SA_PREFS which allow you to specify the location of spam.assassin.prefs.conf if it isn't in
   the usual place.
 - Changed MySQL 'INSERT DELAYED' statement in MailWatch.pm back to the original 'INSERT' as this caused MailScanner to
   'hang' with 99.9% CPU occasionally (MySQL 3.2) on one of my systems - those who are running MySQL >4 would probably
   be okay to change this back to 'INSERT DELAYED' if you have a high-volume site and wish to spare a few MailScanner
   cycles.
 - Exim reports the connecting MTA IP Address as ip.ip.ip.ip.port - updated MailWatch.pm to strip off the port as
   necessary before insert to the DB.
 - Changed the Message Detail page to display the IP Addresses of each MTA recorded in the 'Received' headers and allow
   checks of each with OpenRBL.
 - Added a new setting: DISTRIBUTED_SETUP which when set 'hides' any items that do not work in a distributed environment
   (several servers recording to one MailWatch database) - thanks to Stijn Jonker for this.
 - Added a new tool for sendmail users (patches for other MTA's are welcome) which watches the maillog for message relay
   information and records it to a new table named 'relay' - this information is then displayed on the Message Detail
   screen and shows the Date/Time, Relayed From, Relayed To and Status of each relay that handled a message - thanks to
   Chris Campbell for the idea (it *very* handy for Helpdesks).
 - Added new quarantine settings - QUARANTINE_FROM_ADDR, QUARANTINE_SUBJECT and QUARANTINE_MSG_BODY for a released quarantine message.
 - Changed the SA-Learn option in the quarantine to be more verbose when an error is returned.
 - Added the display of Load Average to the page headers - this will work automatically on any system that has /proc/load_avg available.
 - Fixed the regex that picks out the SpamAssassin rules from the spam report to be case-insensitive as this didn't work for some because of this.
 - Fixed the display of the spam report if SpamAssasin timed-out when processing a message (it was not displayed).
 - Fixed msconfig.php to expand MailScanner.conf %var% - thanks to Chris Maynard for the patch for this.
 - Speeded up the display and order of data in quarantine.php.
 - Message Listing report now uses a Google-style pager.
 - Fixed SQL statement in Top Virus Report speeding it up slightly.
 - Fixed the SQL ORDER BY on the Recent Messages page.
 - Removed the display of the Message ID on the Recent Messages page to allow more space for the other fields.
 - Removed the display of Host (the host that processed the message) when not running in DISTRIBUTED_SETUP mode to allow more space for the other fields.
 - Changed the display colour of High Scoring spam on the Recent Messages page.

## 0.3beta
 - New MailWatch.pm file that contains the MailWatch SQL Logging code.
 - Changed the SQL Logging procedure names from SQLLogging to MailWatchLogging to save confusion as to which versions
   people are using.
 - Updated MailWatchLogging procedures to better handle MySQL death and subsequent restart without needing to restart
   MailScanner.
 - Message headers now displayed on the Message Detail page.
 - OpenRBL lookup address fixed (OpenRBL had updated their site).
 - Spam Action(s) displayed next to Spam/High Scoring Spam on the Message Detail page.
 - New 'Quarantine Manager' allows quarantined messages to be released to recipient(s), deleted or learnt/unlearnt by
   SpamAssassin as Spam or Ham.
 - Major speed-ups on page display.
 - Added extra Virus regular expressions and modified the existing to drop the requirement of 'Include Scanner Name in
   Reports' in MailScanner.conf.
 - New Sendmail inbound/outbound queue display.
 - Fixed the display of the 'Blocked Files' percentage in Today's Totals.
 - Fixed the volume display in the reports to use the average over the reporting period e.g. if you receive 500Mb of mail
   on average per day, but you occasionally spike at 1Gb - the reports will display the volume in Mb.
 - Added new 'MySQL status' page to the 'Other' page.
 - Fixed 'SpamAssassin Rule Hits' report not display any data under some installations of MailScanner.
 - New reports 'Top Mail Relays' and 'Top Sender Domains by Quantity/Volume'.
 - Added 'hostname' to the list of available filters to allow people with multiple scanners report only on a specific one.

## 0.2
 - Renamed project to 'MailWatch for MailScanner' - thanks to Ryan Pitt for the new name suggestion.
 - Added FROMTO_MAXLEN and SUBJECT_MAXLEN constants to functions.php to limit the size of the From, To and Subject
   fields on the 'Recent Messages' screen.
 - Added constant MAX_RESULTS to functions.php to change the results size on the Recent Message screen and Message
   Listing report.
 - Added STATUS_REFRESH constant to functions.php to change the default refresh interval for the Recent Messages screen.
 - Added a 'hostname' column, improved CustomConfig.pm with variables for db host, username and password and added new
   documentation for running the database on a seperate machine with multiple mailscanners logging to it
   (see Remote_DB.txt).
 - Fixed hardcoded database connection parameters in functions.php and rep_message_listing.php
 - Fixed INSTALL documentation typos and ommisions.

## 0.1
 - Initial version
