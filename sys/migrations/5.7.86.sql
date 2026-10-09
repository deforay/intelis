-- Migration file for version 5.7.86
-- Created on 2026-10-08

-- The sample each row of an imported result file was matched to, within the lab the
-- file was imported for. A sample code alone can belong to several samples, so the
-- review screen shows, and the import writes to, this sample only.
ALTER TABLE `temp_sample_import` ADD `matched_sample_id` INT NULL DEFAULT NULL AFTER `sample_details`;


UPDATE `system_config` SET `value` = '5.7.86' WHERE `system_config`.`name` = 'sc_version';
