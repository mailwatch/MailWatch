--
-- Table structure for table `mtalog_ids`
--

CREATE TABLE `mtalog_ids` (
`smtpd_id` VARCHAR( 20 ) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL ,
`smtp_id` VARCHAR( 20 ) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL 
) ENGINE = MYISAM ;

ALTER TABLE `mtalog_ids` ADD UNIQUE `mtalog_ids_idx` ( `smtpd_id` , `smtp_id` );
ALTER TABLE `mtalog_ids` ADD INDEX ( `smtpd_id` );
ALTER TABLE `mtalog` ADD INDEX ( `msg_id` );
