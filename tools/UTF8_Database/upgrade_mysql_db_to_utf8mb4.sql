ALTER DATABASE `mailscanner` DEFAULT CHARACTER SET `utf8mb4` DEFAULT COLLATE `utf8mb4_unicode_ci`;

ALTER TABLE `audit_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `audit_log`;
OPTIMIZE TABLE `audit_log`;

ALTER TABLE `blacklist` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `blacklist`;
OPTIMIZE TABLE `blacklist`;

ALTER TABLE `inq` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `inq`;
OPTIMIZE TABLE `inq`;

ALTER TABLE `maillog` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `maillog`;
OPTIMIZE TABLE `maillog`;

ALTER TABLE `mcp_rules` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `mcp_rules`;
OPTIMIZE TABLE `mcp_rules`;

ALTER TABLE `mtalog` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `mtalog`;
OPTIMIZE TABLE `mtalog`;

ALTER TABLE `outq` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `outq`;
OPTIMIZE TABLE `outq`;

ALTER TABLE `sa_rules` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `sa_rules`;
OPTIMIZE TABLE `sa_rules`;

ALTER TABLE `saved_filters` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `saved_filters`;
OPTIMIZE TABLE `saved_filters`;

ALTER TABLE `spamscores` CHANGE `user` `user` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE `spamscores` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `spamscores`;
OPTIMIZE TABLE `spamscores`;

ALTER TABLE `user_filters` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `user_filters`;
OPTIMIZE TABLE `user_filters`;

ALTER TABLE `users` CHANGE `username` `username` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE `users` CHANGE `username` `password` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE `users` CHANGE `username` `fullname` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `users`;
OPTIMIZE TABLE `users`;

ALTER TABLE `whitelist` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
REPAIR TABLE `whitelist`;
OPTIMIZE TABLE `whitelist`;
