<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2018  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
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
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171122114900 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\',
  `ip_address` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\',
  `action` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $this->addSql('CREATE TABLE IF NOT EXISTS `autorelease` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `msg_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');

        $this->addSql('CREATE TABLE IF NOT EXISTS `blacklist` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `to_address` mediumtext COLLATE utf8mb4_unicode_ci,
  `to_domain` mediumtext COLLATE utf8mb4_unicode_ci,
  `from_address` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blacklist_uniq` (`to_address`(100),`from_address`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $this->addSql('CREATE TABLE IF NOT EXISTS `inq` (
  `inq_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id` longtext COLLATE utf8mb4_unicode_ci,
  `cdate` date DEFAULT NULL,
  `ctime` time DEFAULT NULL,
  `from_address` longtext COLLATE utf8mb4_unicode_ci,
  `to_address` longtext COLLATE utf8mb4_unicode_ci,
  `subject` longtext COLLATE utf8mb4_unicode_ci,
  `message` longtext COLLATE utf8mb4_unicode_ci,
  `size` longtext COLLATE utf8mb4_unicode_ci,
  `priority` longtext COLLATE utf8mb4_unicode_ci,
  `attempts` longtext COLLATE utf8mb4_unicode_ci,
  `lastattempt` longtext COLLATE utf8mb4_unicode_ci,
  `hostname` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`inq_id`),
  KEY `inq_hostname` (`hostname`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $this->addSql("CREATE TABLE IF NOT EXISTS `maillog` (
  `maillog_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NULL DEFAULT NULL,
  `id` longtext COLLATE utf8mb4_unicode_ci,
  `size` bigint(20) DEFAULT '0',
  `from_address` longtext COLLATE utf8mb4_unicode_ci,
  `from_domain` longtext COLLATE utf8mb4_unicode_ci,
  `to_address` longtext COLLATE utf8mb4_unicode_ci,
  `to_domain` longtext COLLATE utf8mb4_unicode_ci,
  `subject` longtext COLLATE utf8mb4_unicode_ci,
  `clientip` longtext COLLATE utf8mb4_unicode_ci,
  `archive` longtext COLLATE utf8mb4_unicode_ci,
  `isspam` tinyint(1) DEFAULT '0',
  `ishighspam` tinyint(1) DEFAULT '0',
  `issaspam` tinyint(1) DEFAULT '0',
  `isrblspam` tinyint(1) DEFAULT '0',
  `isfp` tinyint(1) DEFAULT '0',
  `isfn` tinyint(1) DEFAULT '0',
  `spamwhitelisted` tinyint(1) DEFAULT '0',
  `spamblacklisted` tinyint(1) DEFAULT '0',
  `sascore` decimal(7,2) DEFAULT '0.00',
  `spamreport` longtext COLLATE utf8mb4_unicode_ci,
  `virusinfected` tinyint(1) DEFAULT '0',
  `nameinfected` tinyint(2) DEFAULT '0',
  `otherinfected` tinyint(1) DEFAULT '0',
  `report` longtext COLLATE utf8mb4_unicode_ci,
  `ismcp` tinyint(1) DEFAULT '0',
  `ishighmcp` tinyint(1) DEFAULT '0',
  `issamcp` tinyint(1) DEFAULT '0',
  `mcpwhitelisted` tinyint(1) DEFAULT '0',
  `mcpblacklisted` tinyint(1) DEFAULT '0',
  `mcpsascore` decimal(7,2) DEFAULT '0.00',
  `mcpreport` longtext COLLATE utf8mb4_unicode_ci,
  `hostname` longtext COLLATE utf8mb4_unicode_ci,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `headers` longtext COLLATE utf8mb4_unicode_ci,
  `quarantined` tinyint(1) DEFAULT '0',
  `rblspamreport` longtext COLLATE utf8mb4_unicode_ci,
  `token` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `released` tinyint(1) DEFAULT '0',
  `salearn` tinyint(1) DEFAULT '0',
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`maillog_id`),
  KEY `maillog_datetime_idx` (`date`,`time`),
  KEY `maillog_id_idx` (`id`(20)),
  KEY `maillog_clientip_idx` (`clientip`(20)),
  KEY `maillog_from_idx` (`from_address`(191)),
  KEY `maillog_to_idx` (`to_address`(191)),
  KEY `maillog_host` (`hostname`(30)),
  KEY `from_domain_idx` (`from_domain`(50)),
  KEY `to_domain_idx` (`to_domain`(50)),
  KEY `maillog_quarantined` (`quarantined`),
  KEY `timestamp_idx` (`timestamp`),
  FULLTEXT KEY `subject_idx` (`subject`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->addSql("CREATE TABLE IF NOT EXISTS `mcp_rules` (
  `rule` char(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `rule_desc` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->addSql('CREATE TABLE IF NOT EXISTS `mtalog` (
  `mtalog_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` datetime DEFAULT NULL,
  `host` longtext COLLATE utf8mb4_unicode_ci,
  `type` longtext COLLATE utf8mb4_unicode_ci,
  `msg_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relay` longtext COLLATE utf8mb4_unicode_ci,
  `dsn` longtext COLLATE utf8mb4_unicode_ci,
  `status` longtext COLLATE utf8mb4_unicode_ci,
  `delay` time DEFAULT NULL,
  PRIMARY KEY (`mtalog_id`),
  UNIQUE KEY `mtalog_uniq` (`timestamp`,`host`(10),`type`(10),`msg_id`,`relay`(20)),
  KEY `mtalog_timestamp` (`timestamp`),
  KEY `mtalog_type` (`type`(10)),
  KEY `msg_id` (`msg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $this->addSql('CREATE TABLE IF NOT EXISTS `mtalog_ids` (
  `smtpd_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  UNIQUE KEY `mtalog_ids_idx` (`smtpd_id`,`smtp_id`),
  KEY `smtpd_id` (`smtpd_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $this->addSql('CREATE TABLE IF NOT EXISTS `outq` (
  `outq_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id` longtext COLLATE utf8mb4_unicode_ci,
  `cdate` date DEFAULT NULL,
  `ctime` time DEFAULT NULL,
  `from_address` longtext COLLATE utf8mb4_unicode_ci,
  `to_address` longtext COLLATE utf8mb4_unicode_ci,
  `subject` longtext COLLATE utf8mb4_unicode_ci,
  `message` longtext COLLATE utf8mb4_unicode_ci,
  `size` longtext COLLATE utf8mb4_unicode_ci,
  `priority` longtext COLLATE utf8mb4_unicode_ci,
  `attempts` longtext COLLATE utf8mb4_unicode_ci,
  `lastattempt` longtext COLLATE utf8mb4_unicode_ci,
  `hostname` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`outq_id`),
  KEY `outq_hostname` (`hostname`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $this->addSql("CREATE TABLE IF NOT EXISTS `sa_rules` (
  `rule` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `rule_desc` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->addSql('CREATE TABLE IF NOT EXISTS `saved_filters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `col` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `operator` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_filters` (`name`(20),`col`(20),`operator`(20),`value`(20),`username`(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $this->addSql("CREATE TABLE IF NOT EXISTS `user_filters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `filter` longtext COLLATE utf8mb4_unicode_ci,
  `verify_key` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `active` enum('N','Y') COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `user_filters_username_idx` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->addSql("
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fullname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `type` enum('A','D','U','R','H') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'U',
  `quarantine_report` tinyint(1) DEFAULT '0',
  `spamscore` float DEFAULT '0',
  `highspamscore` float DEFAULT '0',
  `noscan` tinyint(1) DEFAULT '0',
  `quarantine_rcpt` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resetid` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resetexpire` bigint(20) DEFAULT NULL,
  `lastreset` bigint(20) DEFAULT NULL,
  `login_expiry` bigint(20) DEFAULT '-1',
  `last_login` bigint(20) DEFAULT '-1',
  `login_timeout` smallint(5) DEFAULT '-1',
  PRIMARY KEY (`username`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->addSql('CREATE TABLE IF NOT EXISTS `whitelist` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `to_address` longtext COLLATE utf8mb4_unicode_ci,
  `to_domain` longtext COLLATE utf8mb4_unicode_ci,
  `from_address` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whitelist_uniq` (`to_address`(100),`from_address`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->addSql('DROP TABLE IF EXISTS `audit_log`;');
        $this->addSql('DROP TABLE IF EXISTS `autorelease`;');
        $this->addSql('DROP TABLE IF EXISTS `blacklist`;');
        $this->addSql('DROP TABLE IF EXISTS `inq`;');
        $this->addSql('DROP TABLE IF EXISTS `maillog`;');
        $this->addSql('DROP TABLE IF EXISTS `mcp_rules`;');
        $this->addSql('DROP TABLE IF EXISTS `mtalog`;');
        $this->addSql('DROP TABLE IF EXISTS `mtalog_ids`;');
        $this->addSql('DROP TABLE IF EXISTS `outq`;');
        $this->addSql('DROP TABLE IF EXISTS `sa_rules`;');
        $this->addSql('DROP TABLE IF EXISTS `saved_filters`;');
        $this->addSql('DROP TABLE IF EXISTS `user_filters`;');
        $this->addSql('DROP TABLE IF EXISTS `users`;');
        $this->addSql('DROP TABLE IF EXISTS `whitelist`;');
    }
}
