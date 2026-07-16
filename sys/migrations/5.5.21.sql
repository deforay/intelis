-- Migration file for version 5.5.21
--
-- Independent Interface API connection foundation. These tables deliberately
-- do not reuse STS identity or credentials: each Interface Tool installation
-- has its own stable source identity, server identity, scopes, and revocable
-- credential. The API remains disabled until explicitly enabled.

CREATE TABLE IF NOT EXISTS `interface_installations` (
  `installation_id` CHAR(36) NOT NULL,
  `source_installation_id` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `facility_id` INT NOT NULL,
  `display_name` VARCHAR(150) NOT NULL,
  -- Nullable until a source first seen through an LIS relay is claimed directly.
  `credential_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `credential_scopes` JSON NULL DEFAULT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'observed',
  `claimed_at` DATETIME NULL DEFAULT NULL,
  `last_seen_at` DATETIME NULL DEFAULT NULL,
  `revoked_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`installation_id`),
  UNIQUE KEY `uniq_interface_source_installation` (`source_installation_id`),
  KEY `idx_interface_installation_facility_status` (`facility_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `interface_activation_codes` (
  `activation_code_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `facility_id` INT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `used_by_installation_id` CHAR(36) NULL DEFAULT NULL,
  `revoked_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`activation_code_id`),
  UNIQUE KEY `uniq_interface_activation_code_hash` (`code_hash`),
  KEY `idx_interface_activation_facility` (`facility_id`),
  KEY `idx_interface_activation_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `global_config`
  (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `status`)
VALUES
  ('Interface API Enabled', 'interface_api_enabled', 'no', 'interfacing', 'no', 'active');

UPDATE `system_config` SET `value` = '5.5.21' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
