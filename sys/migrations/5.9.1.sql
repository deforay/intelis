-- Migration file for version 5.9.1
-- Created on 2026-11-27 16:18:31

-- Activity log entries say what changed and what they are about, not only that
-- something happened.
--
--   entity_type, entity_id, entity_label  the record the entry is about, such
--       as a user, facility or VL sample type. The label keeps a readable name
--       after the record is renamed or deleted.
--   details     JSON: changed fields with before and after values, supporting
--               facts, and lists such as the facilities added to a user.
--   important   1 for changes that can alter results or what a user can access.
--   request_id  groups the entries written by one request.
--   route       HTTP method and path, or the CLI script.
--   channel     web, api or cli.
--
-- Appended without AFTER so MySQL adds each column in place, without a table
-- rebuild. One action per statement: migrate.php treats duplicate column and
-- duplicate key errors as already applied.

ALTER TABLE `activity_log` ADD COLUMN `entity_type` VARCHAR(32) NULL DEFAULT NULL;
ALTER TABLE `activity_log` ADD COLUMN `entity_id` VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE `activity_log` ADD COLUMN `entity_label` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `activity_log` ADD COLUMN `details` JSON NULL DEFAULT NULL;
ALTER TABLE `activity_log` ADD COLUMN `important` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `activity_log` ADD COLUMN `request_id` CHAR(16) NULL DEFAULT NULL;
ALTER TABLE `activity_log` ADD COLUMN `route` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `activity_log` ADD COLUMN `channel` VARCHAR(16) NULL DEFAULT NULL;

ALTER TABLE `activity_log` ADD INDEX `idx_activity_log_entity` (`entity_type`, `entity_id`);
ALTER TABLE `activity_log` ADD INDEX `idx_activity_log_important` (`important`, `date_time`);
ALTER TABLE `activity_log` ADD INDEX `idx_activity_log_request` (`request_id`);

UPDATE `system_config` SET `value` = '5.9.1' WHERE `system_config`.`name` = 'sc_version';
