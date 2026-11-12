-- Migration file for version 5.8.6
-- Created on 2026-11-06 12:47:25


UPDATE `system_config` SET `value` = '5.8.6' WHERE `system_config`.`name` = 'sc_version';


ALTER TABLE `instruments` ADD COLUMN `instrument_make` VARCHAR(50) NULL DEFAULT NULL AFTER `machine_name`;

-- When each instrument was added, for the list's newest-first order. updated_datetime
-- changes on every save, so instruments added before this take it as the nearest known.
ALTER TABLE `instruments` ADD COLUMN `added_on` DATETIME NULL DEFAULT NULL AFTER `status`;
UPDATE `instruments` SET `added_on` = `updated_datetime` WHERE `added_on` IS NULL;
ALTER TABLE `instruments` MODIFY `added_on` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
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
