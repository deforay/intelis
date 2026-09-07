-- Migration file for version 5.7.59
-- Created on 2026-09-07
--
-- Lands four columns that 5.2.9 was supposed to deliver and did not deliver
-- everywhere, and lands two of them as renames so no install pays for the
-- repair with its data.
--
-- 5.2.9 is one file carrying seven months of changes: its statements are dated
-- 26-Mar-2024 through 25-Oct-2024, appended to the same already-released
-- version as they were written. An installation that upgraded through 5.2.9 in
-- the spring ran the March half and recorded the version as done; the October
-- half was added afterwards and, because the runner never replays a version it
-- has recorded, that half can never run there. The columns are missing for
-- good, on every instance that upgraded before the file stopped growing. Two
-- DRC training instances are in that state today, three versions and eighteen
-- months later, and one of them is missing `form_generic`.`sample_received_at_
-- lab_datetime`, which the request pages read on every generic sample.
--
-- Two of the four are not new columns at all -- 5.2.9 renamed an existing one:
--
--   lab_storage.lab_storage_status                   -> storage_status
--   form_generic.sample_received_at_testing_lab_dt.. -> sample_received_at_lab_datetime
--
-- On an instance that missed the rename the old column is still there holding
-- the values. Adding the new name instead of renaming would leave the data
-- behind in a column nothing reads any more and present an empty one to the
-- app: every stored received-at-lab date silently gone. `intelis check` reports
-- these as missing columns and prints an ADD for each, which is exactly that
-- mistake, so the repair has to be a migration rather than the pasted remedy.
--
-- Each column is written as a rename followed by a guarded add so all three
-- starting states converge:
--
--   old name present    the rename runs; the add is skipped as already there
--   new name present    the rename is recognised as applied and skipped, as is
--                       the add -- a fresh install from init.sql, or one that
--                       did run 5.2.9's October half
--   neither             cannot occur: init.sql declares the new name and 4.4.9
--                       declares the old, so a form_generic or lab_storage that
--                       exists at all has one of the two
--
-- The two form_vl columns are genuine additions with no earlier name, so they
-- are plain adds. `audit_form_vl` had the same four statements in 5.2.9 and is
-- deliberately not repaired: the per-table audit copies were replaced by
-- audit_log in 5.5.3 and the table is gone.

ALTER TABLE `form_generic` CHANGE `sample_received_at_testing_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `form_generic` ADD COLUMN `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL AFTER `sample_received_at_hub_datetime`;

ALTER TABLE `lab_storage` CHANGE `lab_storage_status` `storage_status` VARCHAR(10) NOT NULL DEFAULT 'active';
ALTER TABLE `lab_storage` ADD COLUMN `storage_status` VARCHAR(10) NOT NULL DEFAULT 'active' AFTER `lab_id`;

ALTER TABLE `form_vl` ADD COLUMN `result_sent_to_external` TEXT NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `form_vl` ADD COLUMN `result_sent_to_external_datetime` TEXT NULL DEFAULT NULL AFTER `result_sent_to_external`;

ALTER TABLE `s_vlsm_instance` ADD COLUMN `sts_token` VARCHAR(64) NULL DEFAULT NULL AFTER `instance_facility_logo`;

UPDATE `system_config` SET `value` = '5.7.59' WHERE `system_config`.`name` = 'sc_version';
