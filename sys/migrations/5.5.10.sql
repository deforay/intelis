-- Migration file for version 5.5.10
--
-- Per-test workflow columns on generic_test_results (Custom Tests multi-test).
--
-- Custom Tests are being modeled on the flexible TB workflow: one sample can
-- carry several test results, each performed at the SAME or a DIFFERENT lab,
-- each with its own receipt / rejection / tested / reviewed / approved chain --
-- exactly what tb_tests already holds per row. generic_test_results gains the
-- columns it is missing relative to tb_tests so each result row can stand on its
-- own (lab, rejection, review/approval, revision history, comments).
--
-- Purely ADDITIVE and backward compatible: every column is nullable (data_sync
-- defaults 0), so existing rows keep their values and simply read NULL here; the
-- updated form backfills a row's lab / workflow fields from the parent
-- form_generic when they are NULL (i.e. for results entered before this release).
-- Re-running is safe -- migrate.php treats duplicate-column (1060) as benign.

ALTER TABLE `generic_test_results` ADD COLUMN `lab_id` INT NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `specimen_type` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `is_sample_rejected` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `reason_for_sample_rejection` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `rejection_on` DATETIME NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `result_reviewed_by` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `result_reviewed_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `result_approved_by` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `result_approved_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `revised_by` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `revised_on` DATETIME NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `reason_for_result_change` TEXT NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `comments` MEDIUMTEXT NULL DEFAULT NULL;
ALTER TABLE `generic_test_results` ADD COLUMN `data_sync` INT NOT NULL DEFAULT 0;

ALTER TABLE `generic_test_results` ADD INDEX `idx_generic_test_results_lab` (`lab_id`);

UPDATE `system_config` SET `value` = '5.5.10' WHERE `system_config`.`name` = 'sc_version';
