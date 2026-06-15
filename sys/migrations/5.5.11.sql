-- Migration file for version 5.5.11
--
-- Backup encryption key recovery -- Increment 1 (plumbing only).
--
-- Each LIS instance gets its own random backup encryption key. The key lives on
-- the machine (sodium-encrypted at var/backup-key.storage) for db-tools to use,
-- and a copy is saved to the STS so a replacement machine can recover it --
-- operators manage no passphrase. This migration adds the STS-side key store, the
-- LIS-side recovery-status flags, and the two feature flags. Backup encryption
-- itself stays OFF in this increment (backup_encryption_enabled = 'no'); only the
-- key + recovery plumbing ships, so we never create encrypted backups we can't yet
-- restore.
--
-- All statements are idempotent: CREATE TABLE IF NOT EXISTS, ADD COLUMN (migrate.php
-- treats duplicate-column 1060 as benign), and INSERT IGNORE (global_config PK is
-- `name`), so re-running the migrator is safe.

-- STS key store: holds each lab's backup key, encrypted at rest with the STS's own
-- instance key. `release_status` is unused this increment but lets Phase B add the
-- admin-approval gate without another migration.
CREATE TABLE IF NOT EXISTS `s_lis_backup_key_recovery` (
  `id`                INT           NOT NULL AUTO_INCREMENT,
  `facility_id`       INT           NOT NULL,
  `vlsm_instance_id`  VARCHAR(64)   NULL DEFAULT NULL,
  `key_version`       INT           NOT NULL DEFAULT 1,
  `encrypted_key`     TEXT          NOT NULL,
  `fingerprint`       CHAR(64)      NOT NULL,
  `release_status`    ENUM('stored','release_approved','released')
                                    NOT NULL DEFAULT 'stored',
  -- Timestamps are stamped explicitly from PHP (the receiver sets them), NOT via
  -- DEFAULT/ON UPDATE CURRENT_TIMESTAMP: the migrator re-serializes every statement
  -- through phpmyadmin/sql-parser, whose build() mangles those clauses into invalid
  -- SQL. Keep these plain.
  `saved_at`          DATETIME      NULL DEFAULT NULL,
  `updated_datetime`  DATETIME      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_facility_version` (`facility_id`, `key_version`),
  KEY `idx_facility` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- LIS-side recovery status (one instance row).
ALTER TABLE `s_vlsm_instance` ADD COLUMN `backup_key_saved_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `s_vlsm_instance` ADD COLUMN `backup_key_recovery_ready` TINYINT(1) NOT NULL DEFAULT 0;

-- Feature flags (both default OFF). Set on the STS and synced STS->LIS via the
-- metadata receiver, so the fleet can be enabled centrally.
INSERT IGNORE INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `status`)
VALUES ('Backup Key Recovery Enabled', 'backup_key_recovery_enabled', 'no', 'backup', 'no', 'active');
INSERT IGNORE INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `status`)
VALUES ('Backup Encryption Enabled', 'backup_encryption_enabled', 'no', 'backup', 'no', 'active');

UPDATE `system_config` SET `value` = '5.5.11' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
