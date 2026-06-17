-- Migration file for version 5.5.17
-- Created on 2026-06-17 14:56:46

-- Per-session identifier for the activity log (EPT-style): a short, opaque hash of
-- the PHP session id stored on every activity_log row, so all actions performed in
-- one login session can be filtered together (the fingerprint chip in the UI).
ALTER TABLE `activity_log` ADD `session_hash` VARCHAR(16) NULL DEFAULT NULL AFTER `ip_address`;
ALTER TABLE `activity_log` ADD INDEX `idx_activity_log_session_hash` (`session_hash`);

UPDATE `system_config` SET `value` = '5.5.17' WHERE `system_config`.`name` = 'sc_version';

