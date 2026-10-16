-- Migration file for version 5.7.88
-- Created on 2026-10-13 12:38:37

-- Which lab a row of sample storage history came from, and that lab's own id for it.
-- The STS stored each lab's rows under the lab's history_id, which every lab numbers
-- from 1, so one lab's row 5 overwrote another's. On the STS a row is now identified by
-- the lab and the lab's id; history_id is the STS's own, and the lab's id is what orders
-- one lab's rows. A lab leaves both empty. Not lab_id: pages select form_vl.* next to
-- this table's columns, and the sample's lab_id would be overwritten.
ALTER TABLE `lab_storage_history` ADD `source_lab_id` INT NULL DEFAULT NULL AFTER `history_id`;

ALTER TABLE `lab_storage_history` ADD `lab_history_id` INT NULL DEFAULT NULL AFTER `source_lab_id`;

-- The rows the STS already holds stay as they are, unclaimed: a row a lab sent cannot be
-- told from one written on the STS by looking at it. A lab claims its own when it sends
-- them again below (LabMetadataService::storeStorageHistory).
ALTER TABLE `lab_storage_history` ADD UNIQUE KEY `uniq_lab_storage_history_lab_row` (`source_lab_id`, `lab_history_id`);

-- On a lab, send the whole history again, a batch per sync: the STS claims each row it
-- still holds for the lab, and the rows another lab overwrote come back.
UPDATE `s_vlsm_instance` SET `last_lab_storage_history_id` = NULL;

UPDATE `system_config` SET `value` = '5.7.88' WHERE `system_config`.`name` = 'sc_version';
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
