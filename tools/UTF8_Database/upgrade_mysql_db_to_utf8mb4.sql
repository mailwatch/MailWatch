ALTER DATABASE `mailscanner` DEFAULT CHARACTER SET `utf8mb4` DEFAULT COLLATE `utf8mb4_unicode_ci`;

ALTER TABLE `audit_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `blacklist` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `inq` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `maillog` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `mcp_rules` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `mtalog` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `outq` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `sa_rules` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `saved_filters` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `spamscores` CHANGE `user` `user` VARCHAR(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE `spamscores` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `user_filters` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `users` CHANGE `username` `username` VARCHAR(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `whitelist` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;
