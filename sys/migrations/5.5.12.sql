-- Migration file for version 5.5.12
--
-- Backup key recovery -- release flow (Phase B, STS-side).
--
-- Adds the one-time release-token machinery to the STS key store so an admin can
-- approve a key release for a lab and hand the operator a short token, which the
-- replacement machine exchanges for the key at /remote/v2/backup-key-release.php.
-- The `release_status` column already exists (5.5.11); this adds the token hash,
-- its expiry, and an audit stamp.
--
-- Purely additive and idempotent (ADD COLUMN -> 1060 treated as benign by
-- migrate.php), so re-running and existing installs are unaffected. Timestamps are
-- plain DATETIME stamped from PHP, never DEFAULT/ON UPDATE CURRENT_TIMESTAMP (the
-- migration parser mangles those -- see 5.5.11).

ALTER TABLE `s_lis_backup_key_recovery` ADD COLUMN `release_token_hash` CHAR(64) NULL DEFAULT NULL;
ALTER TABLE `s_lis_backup_key_recovery` ADD COLUMN `release_token_expires` DATETIME NULL DEFAULT NULL;
ALTER TABLE `s_lis_backup_key_recovery` ADD COLUMN `released_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `s_lis_backup_key_recovery` ADD COLUMN `release_note` VARCHAR(255) NULL DEFAULT NULL;

UPDATE `system_config` SET `value` = '5.5.12' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
