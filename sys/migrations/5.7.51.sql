-- Migration file for version 5.7.51
-- Created on 2026-09-03 08:51:24
--
-- Minimum mobile app version. GET /api/v1.1/health reports it as minAppVersion
-- and InteLIS Mobile refuses to run below it. Blank means no minimum, which is
-- the default so no existing app is locked out by this upgrade. Edited on the
-- Global Configuration page.
--
-- Every statement is written so re-running the migration cannot create duplicates.

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_datetime`, `status`)
VALUES ('Minimum Mobile App Version', 'min_app_version', '', 'general', 'yes', CURRENT_TIMESTAMP, 'active')
ON DUPLICATE KEY UPDATE
    `display_name` = VALUES(`display_name`),
    `category` = VALUES(`category`),
    `status` = 'active';

UPDATE `system_config` SET `value` = '5.7.51' WHERE `system_config`.`name` = 'sc_version';
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
