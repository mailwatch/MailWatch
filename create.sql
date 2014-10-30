SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `mailscanner`
--
CREATE DATABASE IF NOT EXISTS `mailscanner` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE `mailscanner`;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE IF NOT EXISTS `audit_log` (
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `ip_address` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `action` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE IF NOT EXISTS `blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_address` text COLLATE utf8_unicode_ci,
  `to_domain` text COLLATE utf8_unicode_ci,
  `from_address` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blacklist_uniq` (`to_address`(100),`from_address`(100))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `inq`
--

CREATE TABLE IF NOT EXISTS `inq` (
  `id` mediumtext COLLATE utf8_unicode_ci,
  `cdate` date DEFAULT NULL,
  `ctime` time DEFAULT NULL,
  `from_address` mediumtext COLLATE utf8_unicode_ci,
  `to_address` mediumtext COLLATE utf8_unicode_ci,
  `subject` mediumtext COLLATE utf8_unicode_ci,
  `message` mediumtext COLLATE utf8_unicode_ci,
  `size` mediumtext COLLATE utf8_unicode_ci,
  `priority` mediumtext COLLATE utf8_unicode_ci,
  `attempts` mediumtext COLLATE utf8_unicode_ci,
  `lastattempt` mediumtext COLLATE utf8_unicode_ci,
  `hostname` mediumtext COLLATE utf8_unicode_ci,
  KEY `inq_hostname` (`hostname`(50))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maillog`
--

CREATE TABLE IF NOT EXISTS `maillog` (
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id` mediumtext COLLATE utf8_unicode_ci,
  `size` bigint(20) DEFAULT '0',
  `from_address` mediumtext COLLATE utf8_unicode_ci,
  `from_domain` mediumtext COLLATE utf8_unicode_ci,
  `to_address` mediumtext COLLATE utf8_unicode_ci,
  `to_domain` mediumtext COLLATE utf8_unicode_ci,
  `subject` mediumtext COLLATE utf8_unicode_ci,
  `clientip` mediumtext COLLATE utf8_unicode_ci,
  `archive` mediumtext COLLATE utf8_unicode_ci,
  `isspam` tinyint(1) DEFAULT '0',
  `ishighspam` tinyint(1) DEFAULT '0',
  `issaspam` tinyint(1) DEFAULT '0',
  `isrblspam` tinyint(1) DEFAULT '0',
  `isfp` tinyint(1) DEFAULT '0',
  `isfn` tinyint(1) DEFAULT '0',
  `spamwhitelisted` tinyint(1) DEFAULT '0',
  `spamblacklisted` tinyint(1) DEFAULT '0',
  `sascore` decimal(7,2) DEFAULT '0.00',
  `spamreport` mediumtext COLLATE utf8_unicode_ci,
  `virusinfected` tinyint(1) DEFAULT '0',
  `nameinfected` tinyint(1) DEFAULT '0',
  `otherinfected` tinyint(1) DEFAULT '0',
  `report` mediumtext COLLATE utf8_unicode_ci,
  `ismcp` tinyint(1) DEFAULT '0',
  `ishighmcp` tinyint(1) DEFAULT '0',
  `issamcp` tinyint(1) DEFAULT '0',
  `mcpwhitelisted` tinyint(1) DEFAULT '0',
  `mcpblacklisted` tinyint(1) DEFAULT '0',
  `mcpsascore` decimal(7,2) DEFAULT '0.00',
  `mcpreport` mediumtext COLLATE utf8_unicode_ci,
  `hostname` mediumtext COLLATE utf8_unicode_ci,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `headers` mediumtext COLLATE utf8_unicode_ci,
  `quarantined` tinyint(1) DEFAULT '0',
  KEY `maillog_datetime_idx` (`date`,`time`),
  KEY `maillog_id_idx` (`id`(20)),
  KEY `maillog_clientip_idx` (`clientip`(20)),
  KEY `maillog_from_idx` (`from_address`(200)),
  KEY `maillog_to_idx` (`to_address`(200)),
  KEY `maillog_host` (`hostname`(30)),
  KEY `from_domain_idx` (`from_domain`(50)),
  KEY `to_domain_idx` (`to_domain`(50)),
  KEY `maillog_quarantined` (`quarantined`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mcp_rules`
--

CREATE TABLE IF NOT EXISTS `mcp_rules` (
  `rule` char(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `rule_desc` char(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rule`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mtalog`
--

CREATE TABLE IF NOT EXISTS `mtalog` (
  `timestamp` datetime DEFAULT NULL,
  `host` mediumtext COLLATE utf8_unicode_ci,
  `type` mediumtext COLLATE utf8_unicode_ci,
  `msg_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `relay` mediumtext COLLATE utf8_unicode_ci,
  `dsn` mediumtext COLLATE utf8_unicode_ci,
  `status` mediumtext COLLATE utf8_unicode_ci,
  `delay` time DEFAULT NULL,
  UNIQUE KEY `mtalog_uniq` (`timestamp`,`host`(10),`type`(10),`msg_id`,`relay`(20)),
  KEY `mtalog_timestamp` (`timestamp`),
  KEY `mtalog_type` (`type`(10)),
  KEY `msg_id` (`msg_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mtalog_ids`
--

CREATE TABLE IF NOT EXISTS `mtalog_ids` (
  `smtpd_id` varchar(20) CHARACTER SET ascii DEFAULT NULL,
  `smtp_id` varchar(20) CHARACTER SET ascii DEFAULT NULL,
  UNIQUE KEY `mtalog_ids_idx` (`smtpd_id`,`smtp_id`),
  KEY `smtpd_id` (`smtpd_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `outq`
--

CREATE TABLE IF NOT EXISTS `outq` (
  `id` mediumtext COLLATE utf8_unicode_ci,
  `cdate` date DEFAULT NULL,
  `ctime` time DEFAULT NULL,
  `from_address` mediumtext COLLATE utf8_unicode_ci,
  `to_address` mediumtext COLLATE utf8_unicode_ci,
  `subject` mediumtext COLLATE utf8_unicode_ci,
  `message` mediumtext COLLATE utf8_unicode_ci,
  `size` mediumtext COLLATE utf8_unicode_ci,
  `priority` mediumtext COLLATE utf8_unicode_ci,
  `attempts` mediumtext COLLATE utf8_unicode_ci,
  `lastattempt` mediumtext COLLATE utf8_unicode_ci,
  `hostname` mediumtext COLLATE utf8_unicode_ci,
  KEY `outq_hostname` (`hostname`(50))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_filters`
--

CREATE TABLE IF NOT EXISTS `saved_filters` (
  `name` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `col` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `operator` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `username` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `unique_filters` (`name`(20),`col`(20),`operator`(20),`value`(20),`username`(20))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sa_rules`
--

CREATE TABLE IF NOT EXISTS `sa_rules` (
  `rule` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `rule_desc` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rule`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spamscores`
--

CREATE TABLE IF NOT EXISTS `spamscores` (
  `user` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `lowspamscore` decimal(10,0) NOT NULL DEFAULT '0',
  `highspamscore` decimal(10,0) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `username` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `password` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fullname` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `type` enum('A','D','U','R','H') COLLATE utf8_unicode_ci DEFAULT NULL,
  `quarantine_report` tinyint(1) DEFAULT '0',
  `spamscore` tinyint(4) DEFAULT '0',
  `highspamscore` tinyint(4) DEFAULT '0',
  `noscan` tinyint(1) DEFAULT '0',
  `quarantine_rcpt` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_filters`
--

CREATE TABLE IF NOT EXISTS `user_filters` (
  `username` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `filter` mediumtext COLLATE utf8_unicode_ci,
  `verify_key` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `active` enum('N','Y') COLLATE utf8_unicode_ci DEFAULT 'N',
  KEY `user_filters_username_idx` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whitelist`
--

CREATE TABLE IF NOT EXISTS `whitelist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_address` mediumtext COLLATE utf8_unicode_ci,
  `to_domain` mediumtext COLLATE utf8_unicode_ci,
  `from_address` mediumtext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whitelist_uniq` (`to_address`(100),`from_address`(100))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
