-- MySQL dump 8.23
--
-- Host: localhost    Database: mailscanner

-- Server version	3.23.58

--
-- Current Database: mailscanner
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ mailscanner;

USE mailscanner;

--
-- Table structure for table `audit_log`
--

CREATE TABLE audit_log (
  timestamp timestamp NOT NULL,
  user varchar(20) NOT NULL default '',
  ip_address varchar(15) NOT NULL default '',
  action text NOT NULL
) ENGINE=MyISAM;

--
-- Table structure for table `blacklist`
--

CREATE TABLE blacklist (
  id int(11) NOT NULL auto_increment,
  to_address text,
  to_domain text,
  from_address text,
  PRIMARY KEY  (id),
  UNIQUE KEY blacklist_uniq (to_address(100),from_address(100))
) ENGINE=MyISAM;

--
-- Table structure for table `geoip_country`
--

CREATE TABLE geoip_country (
  begin_ip varchar(15) default NULL,
  end_ip varchar(15) default NULL,
  begin_num bigint(20) default NULL,
  end_num bigint(20) default NULL,
  iso_country_code char(2) default NULL,
  country text,
  KEY geoip_country_begin (begin_num),
  KEY geoip_country_end (end_num)
) ENGINE=MyISAM;

--
-- Table structure for table `inq`
--

CREATE TABLE inq (
  id text,
  cdate date default NULL,
  ctime time default NULL,
  from_address text,
  to_address text,
  subject text,
  message text,
  size text,
  priority text,
  attempts text,
  lastattempt text,
  hostname text,
  KEY inq_hostname (hostname(50))
) ENGINE=MyISAM;

--
-- Table structure for table `maillog`
--

CREATE TABLE maillog (
  timestamp timestamp NOT NULL,
  id text,
  size bigint(20) default '0',
  from_address text,
  from_domain text,
  to_address text,
  to_domain text,
  subject text,
  clientip text,
  archive text,
  isspam tinyint(1) default '0',
  ishighspam tinyint(1) default '0',
  issaspam tinyint(1) default '0',
  isrblspam tinyint(1) default '0',
  isfp tinyint(1) default '0',
  isfn tinyint(1) default '0',
  spamwhitelisted tinyint(1) default '0',
  spamblacklisted tinyint(1) default '0',
  sascore decimal(7,2) default '0.00',
  spamreport text,
  virusinfected tinyint(1) default '0',
  nameinfected tinyint(1) default '0',
  otherinfected tinyint(1) default '0',
  report text,
  ismcp tinyint(1) default '0',
  ishighmcp tinyint(1) default '0',
  issamcp tinyint(1) default '0',
  mcpwhitelisted tinyint(1) default '0',
  mcpblacklisted tinyint(1) default '0',
  mcpsascore decimal(7,2) default '0.00',
  mcpreport text,
  hostname text,
  date date default NULL,
  time time default NULL,
  headers text,
  quarantined tinyint(1) default '0',
  KEY maillog_datetime_idx (date,time),
  KEY maillog_id_idx (id(20)),
  KEY maillog_clientip_idx (clientip(20)),
  KEY maillog_from_idx (from_address(200)),
  KEY maillog_to_idx (to_address(200)),
  KEY maillog_host (hostname(30)),
  KEY from_domain_idx (from_domain(50)),
  KEY to_domain_idx (to_domain(50)),
  KEY maillog_quarantined (quarantined)
) ENGINE=MyISAM;

--
-- Table structure for table `mcp_rules`
--

CREATE TABLE mcp_rules (
  rule char(100) NOT NULL default '',
  rule_desc char(200) NOT NULL default '',
  PRIMARY KEY  (rule)
) ENGINE=MyISAM;

--
-- Table structure for table `mtalog`
--

CREATE TABLE mtalog (
  timestamp datetime default NULL,
  host text,
  type text,
  msg_id varchar(20) default NULL,
  relay text,
  dsn text,
  status text,
  delay time default NULL,
  UNIQUE KEY mtalog_uniq (timestamp,host(10),type(10),msg_id,relay(20)),
  KEY mtalog_timestamp (timestamp),
  KEY mtalog_type (type(10))
) ENGINE=MyISAM;

--
-- Table structure for table `outq`
--

CREATE TABLE outq (
  id text,
  cdate date default NULL,
  ctime time default NULL,
  from_address text,
  to_address text,
  subject text,
  message text,
  size text,
  priority text,
  attempts text,
  lastattempt text,
  hostname text,
  KEY outq_hostname (hostname(50))
) ENGINE=MyISAM;

--
-- Table structure for table `sa_rules`
--

CREATE TABLE sa_rules (
  rule varchar(100) NOT NULL default '',
  rule_desc varchar(200) NOT NULL default '',
  PRIMARY KEY  (rule)
) ENGINE=MyISAM;

--
-- Table structure for table `saved_filters`
--

CREATE TABLE saved_filters (
  name text NOT NULL,
  col text NOT NULL,
  operator text NOT NULL,
  value text NOT NULL,
  username text NOT NULL,
  UNIQUE KEY unique_filters (name(20),col(20),operator(20),value(20),username(20))
) ENGINE=MyISAM;

--
-- Table structure for table `spamscores`
--

CREATE TABLE spamscores (
  user varchar(40) NOT NULL default '',
  lowspamscore decimal(10,0) NOT NULL default '0',
  highspamscore decimal(10,0) NOT NULL default '0',
  PRIMARY KEY  (user)
) ENGINE=MyISAM;

--
-- Table structure for table `user_filters`
--

CREATE TABLE user_filters (
  username varchar(60) NOT NULL default '',
  filter text,
  verify_key varchar(32) NOT NULL default '',
  active enum('N','Y') default 'N',
  KEY user_filters_username_idx (username)
) ENGINE=MyISAM;

--
-- Table structure for table `users`
--

CREATE TABLE users (
  username varchar(60) NOT NULL default '',
  password varchar(32) default NULL,
  fullname varchar(50) NOT NULL default '',
  type enum('A','D','U','R','H') default NULL,
  quarantine_report tinyint(1) default '0',
  spamscore tinyint(4) default '0',
  highspamscore tinyint(4) default '0',
  noscan tinyint(1) default '0',
  quarantine_rcpt varchar(60) default NULL,
  PRIMARY KEY  (username)
) ENGINE=MyISAM;

--
-- Table structure for table `whitelist`
--

CREATE TABLE whitelist (
  id int(11) NOT NULL auto_increment,
  to_address text,
  to_domain text,
  from_address text,
  PRIMARY KEY  (id),
  UNIQUE KEY whitelist_uniq (to_address(100),from_address(100))
) ENGINE=MyISAM;

