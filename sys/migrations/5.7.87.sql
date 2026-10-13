-- Migration file for version 5.7.87
-- Created on 2026-10-13 12:02:08

-- How far this lab's sample storage history has reached the STS, by the lab's own
-- history_id. The history was sent with the rest of the lab metadata: by time after
-- the first sync, but whole on the first sync and on every forced one, and it gains a
-- row each time a sample is stored or moved. On a lab that stores many samples that
-- was hundreds of megabytes held in memory at once during an upgrade. It now goes a
-- capped batch per run, like the instrument activity.
ALTER TABLE `s_vlsm_instance` ADD `last_lab_storage_history_id` INT NULL DEFAULT NULL AFTER `last_instrument_usage_sync`;

-- Start each lab where it already is: just below the first row its last metadata sync
-- had not sent, so everything after the watermark is still to go. Not the newest row
-- that sync sent: a clock set back gives a later row an earlier time, and a row the
-- sync never sent can then sit below one it did. A lab with nothing unsent starts after
-- its newest row; a lab that has never synced starts from the beginning.
UPDATE `s_vlsm_instance` i
   SET i.`last_lab_storage_history_id` = COALESCE(
       (SELECT MIN(h.`history_id`) - 1
          FROM `lab_storage_history` h
         WHERE h.`updated_datetime` > i.`last_lab_metadata_sync`),
       (SELECT MAX(h.`history_id`) FROM `lab_storage_history` h)
   )
 WHERE i.`last_lab_metadata_sync` IS NOT NULL
   AND i.`last_lab_storage_history_id` IS NULL;

UPDATE `system_config` SET `value` = '5.7.87' WHERE `system_config`.`name` = 'sc_version';
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
