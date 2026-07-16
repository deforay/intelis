-- Migration file for version 5.5.22
--
-- Facility-level Interface Tool connection management and clean reinstall
-- support. Connection-code purpose is server-owned; reconnect codes point at
-- one existing installation and credential rotation remains installation-local.

ALTER TABLE `interface_activation_codes`
  ADD COLUMN `purpose` VARCHAR(16) NOT NULL DEFAULT 'new' AFTER `facility_id`;

ALTER TABLE `interface_activation_codes`
  ADD COLUMN `target_installation_id` CHAR(36) NULL DEFAULT NULL AFTER `purpose`;

ALTER TABLE `interface_installations`
  ADD COLUMN `credential_version` INT NOT NULL DEFAULT 1 AFTER `credential_scopes`;

ALTER TABLE `interface_installations`
  ADD COLUMN `reconnected_at` DATETIME NULL DEFAULT NULL AFTER `claimed_at`;

UPDATE `system_config` SET `value` = '5.5.22' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
