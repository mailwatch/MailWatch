<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2016  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * In addition, as a special exception, the copyright holder gives permission to link the code of this program with
 * those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
 * that use the same license as those files), and distribute linked combinations including the two.
 * You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 * PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 * your version of the program, but you are not obligated to do so.
 * If you do not wish to do so, delete this exception statement from your version.
 *
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/* languages/de.php */
/* v0.2.6 */

return array(
    // 01-login.php
    'username' => 'Benutzername',
    'password' => 'Passwort',
    'mwloginpage01' => 'MailWatch Login Page',
    'mwlogin01' => 'MailWatch Login',
    'badup01' => 'Bad Username or Password',
    'emptypassword01' => 'Password cannot be empty',
    'errorund01' => 'An undefined error occurred',
    'login01' => 'Login',
    
    // 03-funtions.php
    'jumpmessage03' => 'Zu Nachricht gehen:',
    'cuser03' => 'Benutzer',
    'cst03' => 'Systemzeit',
    'colorcodes03' => 'Farbkodierungen',
    'badcontentinfected03' => 'Schlechter Inhalt/Infiziert',
    'whitelisted03' => 'Auf Weisser Liste',
    'blacklisted03' => 'Auf Schwarzer Liste',
    'notverified03' => 'Nicht überprüft',
    'mailscanner03' => 'Mailscanner:',
    'none03' => 'None',
    'comma03' => ':',
    'yes03' => 'YES',
    'no03' => 'NO',
    'status03' => 'Status',
    'message03' => 'Message',
    'tries03' => 'Tries',
    'last03' => 'Last',
    'loadaverage03' => 'Load Average:',
    'mailqueue03' => 'Mail Queues',
    'inbound03' => 'Inbound:',
    'outbound03' => 'Oubound:',
    'clean03' => 'Sauber:',
    'topvirus03' => 'Top Virus:',
    'freedspace03' => 'Freier Plattenplatz',
    'todaystotals03' => 'Heutige Zahlen',
    'processed03' => 'Verarbeitet:',
    'cleans03' => 'Sauber:',
    'viruses03' => 'Viren:',
    'blockedfiles03' => 'Blockierte Dateien:',
    'others03' => 'Andere:',
    'spam03' => 'Spam:',
    'spam103' => 'Spam',
    'hscospam03' => 'Hoch bewerteter Spam:',
    'hscomcp03' => 'Hoch bewerteter MCP:',
    'recentmessages03' => 'Aktuelle Nachrichten',
    'lists03' => 'Listen',
    'quarantine03' => 'Quarantäne',
    'datetime03' => 'Datum/Zeit',
    'from03' => 'Von',
    'to03' => 'An',
    'size03' => 'Größe',
    'subject03' => 'Betreff',
    'sascore03' => 'SA Bewertung',
    'mcpscore03' => 'MCP Bewertung',
    'found03' => 'gefunden',
    'highspam03' => 'High Spam',
    'mcp03' => 'MCP',
    'highmcp03' => 'High MCP',
    
    // 04-details.php
    'receivedon04' => 'Empfangen um:',
    'receivedby04' => 'Empfangen durch:',
    'receivedfrom04' => 'Empfangen von:',
    'receivedvia04' => 'Emfangen über:',
    'msgheaders04' => 'Nachrichten Metadaten:',
    'from04' => 'Von:',
    'to04' => 'An:',
    'size04' => 'Größe:',
    'subject04' => 'Betreff:',
    'hdrantivirus04' => 'Anti-Viren/Schutz vor gefährlichem Inhalt',
    'blkfile04' => 'Geblockte Datei:',
    'otherinfec04' => 'Andere Infektion:',
    'hscospam04' => 'Hoch bewerteter Spam:',
    'listedrbl04' => 'In RBL enthalten:',
    'spamwl04' => 'SPAM auf Weisser Liste:',
    'spambl04' => 'SPAM auf Schwarzer Liste:',
    'saautolearn04' => 'Spamassassin Automatisches Lernen:',
    'sascore04' => 'Spamassassin Bewertung:',
    'spamrep04' => 'Spam Bericht:',
    'hdrmcp04' => 'Schutz von Nachrichteninhalt (Message Content Protection MCP)',
    'highscomcp04' => 'Hoch bewerteter MCP:',
    'mcpwl04' => 'MCP auf Weisser Liste:',
    'mcpbl04' => 'MCP auf Schwarzer Liste:',
    'mcpscore04' => 'MCP Bewertung:',
    'mcprep04' => 'MCP Bericht:',
    'ipaddress04' => 'IP Adresse',
    'country04' => 'Land',
    'all04' => 'Alle',
    'addwl04' => 'Zur Weissen Liste hinzufügen',
    'addbl04' => 'Zur Schwarzen Liste hinzufügen',
    'release04' => 'Freigeben',
    'delete04' => 'Löschen',
    'salearn04' => 'SA Trainieren',
    'file04' => 'Datei',
    'type04' => 'Typ',
    'path04' => 'Pfad',
    'dang04' => 'Gefährlich',
    'altrecip04' => 'Alternative(r) Empfänger',
    'submit04' => 'Bestätigen',
    'actions04' => 'Aktion(en)',
    'quarcmdres04' => 'Ergebnisse des Quarantänebefehls',
    'resultmsg04' => 'Detailierte Ergebnisse',
    'id04' => 'ID:',
    'virus04' => 'Virus:',
    'spam04' => 'Spam:',
    'spamassassinspam04' => 'SpamAssassin Spam:',

    // 05-status.php
    'recentmsg05' => 'Aktuelle Nachrichten',
    'last05' => 'Letzte',
    'messages05' => 'Nachrichten',
    'refevery05' => 'Aktualisiere alle',
    'seconds05' => 'Sekunden',

    // 06-viewmail.php
    'msgviewer06' => 'Nachrichtenbetrachter',
    'releasemsg06' => 'Diese Nachricht freigeben',
    'deletemsg06' => 'Diese Nachricht löschen',
    'actions06' => 'Aktionen',
    'date06' => 'Datum:',
    'from06' => 'Von:',
    'to06' => 'An:',
    'subject06' => 'Betreff:',
    
    // Added in 2015-06-22

    // 03-functions.php
    'reports03' => 'Berichte',
    'toolslinks03' => 'Werkzeuge/Verknüpfungen',
    'softwareversions03' => 'Softwareversionen',
    'documentation03' => 'Dokumentation',
    'logout03' => 'Abmelden',
    'pggen03' => 'Seite wurde erzeugt in',
    'seconds03' => 'Sekunden',
    'disppage03' => 'Seite zeigen',
    'of03' => 'von',
    'records03' => 'Einträge',
    'to0203' => 'bis',// This means to as "from numer x to number Y". The first to03 means "from mail ... to mail ..."

    // 07-lists.php
    'addwlbl07' => 'Zu Weisser/Schwarzer Liste hinzufügen',
    'from07' => 'Von',
    'to07' => 'An',
    'list07' => 'Liste',
    'action07' => 'Aktion',
    'wl07' => 'Weisse Liste',
    'bl07' => 'Schwarze Liste',
    'reset07' => 'Zurücksetzen',
    'add07' => 'Hinzufügen',
    'delete07' => 'Löschen',

    // 08-quarantine.php
    'folder08' => 'Ordner',
    'folder_0208' => 'Ordner',
    'items08' => 'Einträge',

    // 09-filter.inc.php
    'activefilters09' => 'Aktive Filter',
    'none09' => 'Keiner',
    'addfilter09' => 'Filter hinzufügen',
    'loadsavef09' => 'Filter öffnen/speichern',
    'tosetdate09' => 'Für das Datum muss das Format JJJJ-mm-tt verwendet werden',
    'oldrecord09' => 'Oldest record:',
    'newrecord09' => 'Newest record:',
    'messagecount09' => 'Message count:',
    'stats09' => 'Statistics (Filtered)',
    'add09' => 'Add',
    'load09' => 'Load',
    'save09' => 'Save',
    'delete09' => 'Delete',
    'none09' => 'None',

    // Added in 2015-06-23
    // 04-details.php
    'quarantine04' => 'Quarantäne',

    // 03-functons.php
    'score03' => 'Bewertung',
    'matrule03' => 'Zutreffende Regel',
    'description03' => 'Beschreibung',

    // 10-other.php
    'tools10' => 'Werkzeuge',
    'usermgnt10' => 'Benutzerverwaltung',
    'avsophosstatus10' => 'Sophos Status',
    'avfsecurestatus10' => 'F-Secure Status',
    'avclamavstatus10' => 'ClamAV Status',
    'avmcafeestatus10' => 'McAfee Status',
    'avfprotstatus10' => 'F-Prot Status',
    'mysqldatabasestatus10' => 'MySQL Database Status',
    'viewconfms10' => 'MailScanner Einstellungen betrachten',
    'editmsrules10' => 'MailScanner Regeln bearbeiten',
    'spamassassinbayesdatabaseinfo10' => 'SpamAssassin Bayes Database Info',
    'updatesadesc10' => 'Aktualisiere die Beschreibungen der SpamAssassin Regeln',
    'updatemcpdesc10' => 'Aktualisiere die Beschreibungen der MCP Regeln',
    'updategeoip10' => 'Aktualisiere die GeoIP Datenbank',
    'links10' => 'Links',

    // 11-sf_versions.php
    'softver11' => 'Softwareversionen',
    'nodbdown11' => 'Keine Datenbank heruntergeladen',
    'version11' => 'Version',

    // 12-user_manager.php
    'usermgnt12' => 'Benutzerverwaltung',
    'username12' => 'Benutzername',
    'fullname12' => 'Voller Name',
    'type12' => 'Typ',
    'spamcheck12' => 'Spam Prüfung',
    'spamscore12' => 'Spam Bewertung',
    'spamhscore12' => 'Hohe Spam Bewertung',
    'action12' => 'Aktionen',
    'edit12' => 'Bearbeiten',
    'delete12' => 'Löschen',
    'filters12' => 'Filter',
    'newuser12' => 'Neuer Benutzer',
    'forallusers12' => 'Für alle Benutzer ausser dem Administrator muss der Benutzername eine E-Mail Adresse sein',
    'username0212' => 'Benutzername:',
    'name12' => 'Name:',
    'password12' => 'Passwort:',
    'usertype12' => 'Benutzertyp:',
    'user12' => 'Benutzer',
    'domainadmin12' => 'Domänen Administrator',
    'admin12' => 'Administrator',
    'quarrep12' => 'Quarantäne Bericht:',
    'senddaily12' => 'Täglichen Bericht senden?:',
    'quarreprec12' => 'Empfänger des Quarantäne Berichts:',
    'overrec12' => 'Empfangadresse für Quarantäne Bericht überschreiben?<BR>(falls leer, wird der Benutzername verwendet)',
    'scanforspam12' => 'Auf Spam prüfen:',
    'scaneforspam12' => 'E-Mail auf Spam prüfen?',
    'pontspam12' => 'Spam Bewertung:',
    'hpontspam12' => 'Hohe Spam Bewertung:',
    'usedefault12' => 'Voreinstellungen benutzen',
    'action_0212' => 'Aktion:',
    'reset12' => 'Zurücksetzen',
    'areusuredel12' => 'Soll der Benutzer tatsächlich gelöscht werden',
    'errorpass12' => 'Passwörter stimmen nicht überein',
    'edituser12' => 'Edit User',
    'create12' => 'Create',
    'userregex12' => 'User (Regexp)',
    'update12' => 'Update',
    'userfilter12' => 'User Filters for',
    'filter12' => 'Filter',
    'add12' => 'Add',
    'active12' => 'Active',
    'yes12' => 'Yes',
    'no12' => 'No',
    'questionmark12' => '?',
    'toggle12' => 'Activate/Deactivate',
    'sure12' => 'Are you sure?',
    'unknowtype12' => 'Unknown Type',
    'yesshort12' => 'Y',
    'noshort12' => 'N',

    // Added in 2015-06-25
    
    // 03-funtions.php
    'footer01' => 'MailWatch for MailScanner v',

    // 08-quarantine.php
    'folder_0308' => 'Quarantine Folder',

    // 13-sa_rules_update.php
    'input13' => 'Run Now',
    'updatesadesc13' => 'Update SpamAssassin Rule Descriptions',
    'updategeoip15' => 'Update GeoIP Database',
    'message113' => 'This utility is used to update the SQL database with up-to-date descriptions of the SpamAssassin rules which are displayed on the Message Detail screen.',
    'message213' => 'This utility should generally be run after a SpamAssassin update, however it is safe to run at any time as it only replaces the existing values and inserts only new values in the table (therefore preserving descriptions from potentially deprecated or removed rules).',
        
    // 14-reports.php
    'messlisting14' => 'Message Listing',
    'messop14' => 'Message Operations',
    'messdate14' => 'Total Messages by Date',
    'topmailrelay14' => 'Top Mail Relays',
    'topvirus14' => 'Top Viruses',
    'virusrepor14' => 'Virus Report',
    'topsendersqt14' => 'Top Senders by Quantity',
    'topsendersvol14' => 'Top Senders by Volume',
    'toprecipqt14' => 'Top Recipients by Quantity',
    'toprecipvol14' => 'Top Recipients by Volume',
    'topsendersdomqt14' => 'Top Sender Domains by Quantity',
    'topsendersdomqt14' => 'Top Sender Domains by Volume',
    'toprecipdomqt14' => 'Top Recipient Domains by Quantity',
    'toprecipdomvol14' => 'Top Recipient Domains by Volume',
    'assassinscoredist14' => 'SpamAssassin Score Distribution',
    'assassinrulhit14' => 'SpamAssassin Rule Hits',
    'auditlog14' => 'Audit Log',
    'mrtgreport14' => 'MRTG Style Report',
    'mcpscoredist14' => 'MCP Score Distribution',
    'mcprulehit14' => 'MCP Rule Hit',

    // 15-geoip_update.php
    'input15' => 'Run Now',
    'updategeoip15' => 'Update GeoIP Database',
    'message115' => 'This utility is used to download the GeoIP database files (which are updated on the first Tuesday of each month) from',
    'message215' => 'which is used to work out the country of origin for any given IP address and is displayed on the Message Detail page.',
    'downfile15' => 'Downloading file, please wait...',
    'geoipv415' => 'GeoIP IPv4 data file',
    'geoipv615' => 'GeoIP IPv6 data file',
    'downok15' => 'successfully downloaded',
    'downbad15' => 'Error occurred while downloading',
    'downokunpack15' => 'Download complete, unpacking files...',
    'message315' => 'Unable to download GeoIP data file (tried CURL and fsockopen).',
    'message415' => 'Install either cURL extension (preferred) or enable fsockopen in your php.ini',
    'unpackok15' => 'successfully unpacked',
    'extractnotok15' => 'Unable to extract',
    'extractok15' => 'successfully extracted',
    'message515' => 'Unable to extract GeoIP data file.',
    'message615' => 'Enable Zlib in your PHP installation or install gunzip executable.',
    'processok' => 'Process completed!',
    'norread15' => 'Unable to read or write to the',
    'message715' => 'Files still exist for some reason.',
    'message815' => 'Delete them manually from',
    'directory15' => 'directory',

    // 16-rep_message_listing.php
    'messlisting16' => 'Message Listing',

    // 17-rep_message_ops.php
    'messageops17' => 'Message Operations',
                        
    //auto-release.php
    'msgnotfound1' => 'Message not found.  You may have already released this message or the link may have expired.',
    'msgnotfound2' => 'Please contact your email administrator and provide them with this message ID: ',
    'msgnotfound3' => 'if you need this message released',
    'msgreleased1' => 'Message released<br>It may take a few minutes to appear in your inbox.',
    'tokenmismatch1' => 'Error releasing message - token mismatch',
    'notallowed99' => 'You are not allowed to be here!',
    'dberror99' => 'Something went wrong - please contact support',
    'arview01' => 'View',
    'arrelease01' => 'Release',
    
    // 99 - General
    'i18_missing' => 'Keine deutsche Übersetzung',
    'cannot_read_conf' => "Kann conf.php nicht lesen - bitte die Datei conf.php.example kopieren, und die Parameter entsprechend anpassen.",

);
