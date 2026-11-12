-- Migration file for version 5.8.7
-- Created on 2026-11-12 12:27:15


UPDATE `system_config` SET `value` = '5.8.7' WHERE `system_config`.`name` = 'sc_version';

-- A facility name now has to be unique only within its province and district,
-- not across the whole country. The check is done in the application
-- (FacilitiesService::isFacilityNameTakenInArea), so the global unique index goes.
-- 5.7.58 already dropped the `facility_name_2` copy; it is repeated here so an
-- install that skipped it is cleared too. Re-running cannot fail (1091 and 1061
-- are benign), and the plain index keeps lookups by name fast.
ALTER TABLE `facility_details` DROP INDEX `facility_name`;
ALTER TABLE `facility_details` DROP INDEX `facility_name_2`;
ALTER TABLE `facility_details` ADD INDEX `idx_facility_name` (`facility_name`);
