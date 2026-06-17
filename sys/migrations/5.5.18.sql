-- Migration file for version 5.5.18
-- Created on 2026-06-17 15:17:32

-- Raw user agent on each activity_log row, so the activity feed can show the
-- actor's browser/OS (parsed client-side, EPT-style). Populated for new rows only.
ALTER TABLE `activity_log` ADD `user_agent` VARCHAR(512) NULL DEFAULT NULL AFTER `session_hash`;

UPDATE `system_config` SET `value` = '5.5.18' WHERE `system_config`.`name` = 'sc_version';

