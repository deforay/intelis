-- Migration file for version 5.7.61
-- Created on 2026-09-07
--
-- Amit 07-Sep-2026
--
-- Re-assert every column 5.2.9 adds, so an installation stranded part-way
-- through it is repaired wherever it stopped.
--
-- 5.2.9 stamps its own version on line 2 of 750 and then carries seven months
-- of changes appended to an already-closed version. An installation that
-- upgraded while the file was shorter ran what existed then, recorded 5.2.9 as
-- done, and can never replay it: the runner does not re-run a version it has
-- recorded. Everything appended afterwards is missing there, permanently, with
-- no error to say so -- the application simply fails on an unknown column.
--
-- 5.7.59 repaired four of those columns. It could not have repaired the rest,
-- and there is no single cut-off to repair up to: every installation stopped
-- wherever its own upgrade happened to fall, so the only safe assumption is
-- that any of them may be missing. All 43 are re-asserted here.
--
-- Every statement is an ADD of a column that today's sql/init.sql declares, so
-- this adds nothing a current schema does not already have, and the runner
-- skips each one it finds present. 5.2.9 drops no column, so nothing here can
-- remove anything.
--
-- One action per statement, deliberately. A multi-action ALTER used to be
-- judged by its first action alone and the rest silently dropped -- that is
-- fixed in the runner now, and this file does not depend on the fix.
--
-- The audit_form_* copies of these columns are left out: those tables were
-- replaced by audit_log in 5.5.3 and no longer exist, and 5.7.59 leaves them
-- out for the same reason.
--
-- Two columns 5.2.9 adds are renamed by 5.7.59 and are NOT re-added here, or
-- this would put the old names back:
--   form_generic.sample_received_at_testing_lab_datetime -> sample_received_at_lab_datetime
--   lab_storage.lab_storage_status                       -> storage_status

-- form_cd4
ALTER TABLE `form_cd4` ADD COLUMN `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;
ALTER TABLE `form_cd4` ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_cd4` ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

-- form_eid
ALTER TABLE `form_eid` ADD COLUMN `second_dbs_requested_reason` VARCHAR(256) NULL DEFAULT NULL AFTER `second_dbs_requested`;
ALTER TABLE `form_eid` ADD COLUMN `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL AFTER `child_gender`;
ALTER TABLE `form_eid` ADD COLUMN `is_mother_alive` VARCHAR(50) NULL DEFAULT NULL AFTER `request_clinician_phone_number`;
ALTER TABLE `form_eid` ADD COLUMN `child_age_in_weeks` INT NULL DEFAULT NULL AFTER `child_age`;
ALTER TABLE `form_eid` ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_eid` ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

-- form_vl
ALTER TABLE `form_vl` ADD COLUMN `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL AFTER `patient_gender`;
ALTER TABLE `form_vl` ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_vl` ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `form_vl` ADD COLUMN `result_sent_to_external` TEXT NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `form_vl` ADD COLUMN `result_sent_to_external_datetime` TEXT NULL DEFAULT NULL AFTER `result_sent_to_external`;

-- form_covid19
ALTER TABLE `form_covid19` ADD COLUMN `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL AFTER `patient_gender`;
ALTER TABLE `form_covid19` ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_covid19` ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

-- lab_storage
ALTER TABLE `lab_storage` ADD COLUMN `data_sync` INT NOT NULL DEFAULT '0' AFTER `updated_datetime`;

-- batch_details
ALTER TABLE `batch_details` ADD COLUMN `control_names` JSON NULL DEFAULT NULL AFTER `label_order`;
ALTER TABLE `batch_details` ADD COLUMN `created_by` VARCHAR(500) NULL DEFAULT NULL AFTER `control_names`;
ALTER TABLE `batch_details` ADD COLUMN `batch_attributes` JSON NULL DEFAULT NULL AFTER `batch_status`;
ALTER TABLE `batch_details` ADD COLUMN `lab_assigned_batch_code` VARCHAR(64) NULL DEFAULT NULL AFTER `machine`;
ALTER TABLE `batch_details` ADD COLUMN `printed_datetime` DATETIME NULL DEFAULT NULL AFTER control_names;

-- r_generic_test_reasons
ALTER TABLE `r_generic_test_reasons` ADD COLUMN `parent_reason` INT NULL DEFAULT NULL AFTER `test_reason`;

-- instrument_controls
ALTER TABLE `instrument_controls` ADD COLUMN `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `number_of_calibrators`;

-- s_vlsm_instance
ALTER TABLE `s_vlsm_instance` ADD COLUMN `last_vldash_sync` DATETIME NULL DEFAULT NULL;
ALTER TABLE `s_vlsm_instance` ADD COLUMN `last_lab_metadata_sync` DATETIME NULL DEFAULT NULL AFTER `last_vldash_sync`;
ALTER TABLE `s_vlsm_instance` ADD COLUMN `sts_token` VARCHAR(64) NULL DEFAULT NULL AFTER `instance_facility_logo`;

-- lab_storage_history
ALTER TABLE `lab_storage_history` ADD COLUMN `date_out` DATE NULL DEFAULT NULL AFTER `sample_status`;
ALTER TABLE `lab_storage_history` ADD COLUMN `comments` TEXT NULL DEFAULT NULL AFTER `date_out`;
ALTER TABLE `lab_storage_history` ADD COLUMN `sample_removal_reason` INT NULL DEFAULT NULL AFTER `comments`;

-- global_config
ALTER TABLE `global_config` ADD COLUMN `instance_id` VARCHAR(50) NULL DEFAULT NULL AFTER `value`;

-- user_details
ALTER TABLE `user_details` ADD COLUMN `user_attributes` JSON NULL DEFAULT NULL AFTER `user_signature`;

-- form_generic
ALTER TABLE `form_generic` ADD COLUMN `is_encrypted` varchar(10) DEFAULT 'no' AFTER `patient_address`;
ALTER TABLE `form_generic` ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_generic` ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

-- form_hepatitis
ALTER TABLE `form_hepatitis` ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_hepatitis` ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

-- form_tb
ALTER TABLE `form_tb` ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_tb` ADD COLUMN `is_patient_pregnant` VARCHAR(3) CHARACTER SET utf8mb4 NULL DEFAULT NULL AFTER `patient_gender`;
ALTER TABLE `form_tb` ADD COLUMN `is_patient_breastfeeding` VARCHAR(3) CHARACTER SET utf8mb4 NULL DEFAULT NULL AFTER `is_patient_pregnant`;

-- facility_details
ALTER TABLE `facility_details` ADD COLUMN `sts_token` VARCHAR(64) NULL DEFAULT NULL AFTER `facility_type`;
ALTER TABLE `facility_details` ADD COLUMN `sts_token_expiry` DATETIME NULL DEFAULT NULL AFTER `sts_token`;

UPDATE `system_config` SET `value` = '5.7.61' WHERE `system_config`.`name` = 'sc_version';
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
