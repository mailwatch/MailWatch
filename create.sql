SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
SET NAMES utf8mb4;

--
-- Database: `mailscanner`
--
CREATE DATABASE IF NOT EXISTS `mailscanner` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
USE `mailscanner`;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `action` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_log_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `autorelease`
--

CREATE TABLE IF NOT EXISTS `autorelease` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `msg_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `uid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocklist`
--

CREATE TABLE IF NOT EXISTS `blocklist` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `to_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `to_domain` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `from_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blocklist_uniq` (`to_address`(100),`from_address`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inq`
--

CREATE TABLE IF NOT EXISTS `inq` (
  `inq_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `cdate` date DEFAULT NULL,
  `ctime` time DEFAULT NULL,
  `from_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `to_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `subject` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `message` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `size` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `priority` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `attempts` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `lastattempt` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `hostname` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`inq_id`),
  KEY `inq_hostname` (`hostname`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maillog`
--

CREATE TABLE IF NOT EXISTS `maillog` (
  `maillog_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NULL,
  `id` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `size` bigint(20) DEFAULT '0',
  `from_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `from_domain` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `to_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `to_domain` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `subject` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `clientip` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `archive` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `isspam` tinyint(1) DEFAULT '0',
  `ishighspam` tinyint(1) DEFAULT '0',
  `issaspam` tinyint(1) DEFAULT '0',
  `isrblspam` tinyint(1) DEFAULT '0',
  `isfp` tinyint(1) DEFAULT '0',
  `isfn` tinyint(1) DEFAULT '0',
  `spamallowlisted` tinyint(1) DEFAULT '0',
  `spamblocklisted` tinyint(1) DEFAULT '0',
  `sascore` decimal(7,2) DEFAULT '0.00',
  `spamreport` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `virusinfected` tinyint(1) DEFAULT '0',
  `nameinfected` tinyint(2) DEFAULT '0',
  `otherinfected` tinyint(1) DEFAULT '0',
  `report` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `ismcp` tinyint(1) DEFAULT '0',
  `ishighmcp` tinyint(1) DEFAULT '0',
  `issamcp` tinyint(1) DEFAULT '0',
  `mcpallowlisted` tinyint(1) DEFAULT '0',
  `mcpblocklisted` tinyint(1) DEFAULT '0',
  `mcpsascore` decimal(7,2) DEFAULT '0.00',
  `mcpreport` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `hostname` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `headers` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `messageid` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `quarantined` tinyint(1) DEFAULT '0',
  `rblspamreport` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `token` CHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `ingestion_id` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `released` tinyint(1) DEFAULT '0',
  `salearn` tinyint(1) DEFAULT '0',
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`maillog_id`),
  UNIQUE KEY `maillog_ingestion_id_uniq` (`ingestion_id`),
  KEY `maillog_datetime_idx` (`date`,`time`),
  KEY `maillog_id_idx` (`id`(20)),
  KEY `maillog_clientip_idx` (`clientip`(20)),
  KEY `maillog_from_idx` (`from_address`(191)),
  KEY `maillog_to_idx` (`to_address`(191)),
  KEY `maillog_host` (`hostname`(30)),
  KEY `from_domain_idx` (`from_domain`(50)),
  KEY `to_domain_idx` (`to_domain`(50)),
  KEY `maillog_quarantined` (`quarantined`),
  KEY `timestamp_idx` (`timestamp`)
  /*!50604 , FULLTEXT KEY `subject_idx` (`subject`) */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mcp_rules`
--

CREATE TABLE IF NOT EXISTS `mcp_rules` (
  `rule` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `rule_desc` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mtalog`
--

CREATE TABLE IF NOT EXISTS `mtalog` (
  `mtalog_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `timestamp` datetime DEFAULT NULL,
  `host` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `type` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `msg_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `relay` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `dsn` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `status` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `delay` time DEFAULT NULL,
  `to_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`mtalog_id`),
  UNIQUE KEY `mtalog_uniq` (`timestamp`,`host`(10),`type`(10),`msg_id`,`relay`(20),`to_address`(64)),
  KEY `mtalog_timestamp` (`timestamp`),
  KEY `mtalog_type` (`type`(10)),
  KEY `msg_id` (`msg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mtalog_ids`
--

CREATE TABLE IF NOT EXISTS `mtalog_ids` (
  `smtpd_id` varchar(20) CHARACTER SET ascii DEFAULT NULL,
  `smtp_id` varchar(20) CHARACTER SET ascii DEFAULT NULL,
  UNIQUE KEY `mtalog_ids_idx` (`smtpd_id`,`smtp_id`),
  KEY `smtpd_id` (`smtpd_id`),
  KEY `mtalog_ids_smtp_id` (`smtp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `outq`
--

CREATE TABLE IF NOT EXISTS `outq` (
  `outq_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `cdate` date DEFAULT NULL,
  `ctime` time DEFAULT NULL,
  `from_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `to_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `subject` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `message` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `size` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `priority` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `attempts` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `lastattempt` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `hostname` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`outq_id`),
  KEY `outq_hostname` (`hostname`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_filters`
--

CREATE TABLE IF NOT EXISTS `saved_filters` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `col` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `operator` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `username` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_filters` (`name`(20),`col`(20),`operator`(20),`value`(20),`username`(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sa_rules`
--

CREATE TABLE IF NOT EXISTS `sa_rules` (
  `rule` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `rule_desc` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `fullname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `type` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'U',
  `quarantine_report` tinyint(1) DEFAULT '0',
  `spamscore` float DEFAULT '0',
  `highspamscore` float DEFAULT '0',
  `noscan` tinyint(1) DEFAULT '0',
  `quarantine_rcpt` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `resetid` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `resetexpire` bigint(20) DEFAULT NULL,
  `lastreset` bigint(20) DEFAULT NULL,
  `login_expiry` bigint(20) DEFAULT '-1',
  `last_login` bigint(20) DEFAULT '-1',
  `login_timeout` smallint(5) DEFAULT '-1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_uniq` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_filters`
--

CREATE TABLE IF NOT EXISTS `user_filters` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `filter` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `verify_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `active` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `user_filters_username_idx` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Table structure for table `allowlist`
--

CREATE TABLE IF NOT EXISTS `allowlist` (
  `id` bigint(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `to_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `to_domain` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `from_address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `allowlist_uniq` (`to_address`(100),`from_address`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
