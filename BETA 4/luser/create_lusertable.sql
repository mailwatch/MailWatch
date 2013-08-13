use mailscanner;

CREATE TABLE `lusers` (
  `lusername` text NOT NULL,
  `password` varchar(32) default NULL,
  PRIMARY KEY  (`lusername`(255))
) TYPE=MyISAM;
