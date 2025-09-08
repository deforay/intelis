-- Migration file for version 5.3.1
-- Created on 2025-03-21 12:41:17

-- Amit 21-Mar-2025
UPDATE `system_config` SET `value` = '5.3.1' WHERE `system_config`.`name` = 'sc_version';

-- Amit 10-Apr-2025
UPDATE `s_app_menu` SET `show_mode` = 'sts' WHERE link like '/admin/monitoring/sync-status.php';

-- Amit 24-Apr-2025
ALTER TABLE `form_vl` CHANGE `recency_vl` `recency_vl` VARCHAR(10) CHARACTER SET utf8mb4 NULL DEFAULT 'no';
ALTER TABLE `audit_form_vl` CHANGE `recency_vl` `recency_vl` VARCHAR(10) CHARACTER SET utf8mb4 NULL DEFAULT 'no';

-- Amit 28-Apr-2025
INSERT IGNORE INTO roles_privileges_map (role_id, privilege_id)
SELECT 1, privilege_id FROM privileges;

-- Amit 06-May-2025
UPDATE `privileges` SET `privilege_name`= '/vl/results/vl-print-results.php' WHERE `privilege_name` LIKE '/vl/results/vlPrintResult.php';
UPDATE `s_app_menu` SET `link`= '/vl/results/vl-print-results.php' WHERE `link` LIKE '/vl/results/vlPrintResult.php';

-- Amit 21-May-2025
UPDATE `s_app_menu` set icon = "fa-solid fa-table" WHERE `link` = '/admin/monitoring/test-results-metadata.php';
UPDATE `s_app_menu` set icon = "fa-solid fa-file-lines" WHERE `link` = '/admin/monitoring/log-files.php';
UPDATE `s_app_menu` set icon = "fas fa-user-clock" WHERE `link` = '/admin/monitoring/activity-log.php';

-- UPDATE `user_details` set login_id = null where status = 'inactive';
-- UPDATE `user_details` set `status`='inactive', `login_id` = null where hash_algorithm = 'sha1';
-- ALTER TABLE `user_details` ADD UNIQUE(`login_id`);
UPDATE user_details SET user_id = UUID() WHERE user_id IS NULL OR TRIM(user_id) = '';

-- Amit 29-May-2025
ALTER TABLE `r_vl_art_regimen` DROP `nation_identifier`;

-- Amit 31-May-2025
ALTER TABLE `form_vl` CHANGE `vl_result_category` `vl_result_category` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
CREATE INDEX idx_vl_result_category_status ON form_vl (vl_result_category, result_status);

-- Amit 10-Jun-2025
ALTER TABLE `user_details` DROP INDEX `interface_user_name`;
ALTER TABLE `user_details` CHANGE `interface_user_name` `interface_user_name` JSON NULL DEFAULT NULL;

-- Amit 11-Jun-2025
ALTER TABLE `temp_sample_import` CHANGE `import_machine_name` `import_machine_name` VARCHAR(128) NULL DEFAULT NULL;

-- Amit 17-Jun-2025
ALTER TABLE `hold_sample_import` ADD `instrument_id` VARCHAR(128) NULL DEFAULT NULL AFTER `vl_test_platform`;
-- Thana 02-Jul-2025
ALTER TABLE `form_eid` ADD `specific_infant_treatment` VARCHAR(128) NULL DEFAULT NULL AFTER `is_infant_receiving_treatment`;
ALTER TABLE `audit_form_eid` ADD `specific_infant_treatment` VARCHAR(128) NULL DEFAULT NULL AFTER `is_infant_receiving_treatment`;
ALTER TABLE `form_eid` ADD `child_age_in_days` INT NULL DEFAULT NULL AFTER `child_treatment_initiation_date`, ADD `test_request_date` DATE NULL DEFAULT NULL AFTER `child_age_in_days`, ADD `infant_email` VARCHAR(256) NULL DEFAULT NULL AFTER `test_request_date`, ADD `infant_phone` INT NULL DEFAULT NULL AFTER `infant_email`;
ALTER TABLE `audit_form_eid` ADD `child_age_in_days` INT NULL DEFAULT NULL AFTER `child_treatment_initiation_date`, ADD `test_request_date` DATE NULL DEFAULT NULL AFTER `child_age_in_days`, ADD `infant_email` VARCHAR(256) NULL DEFAULT NULL AFTER `test_request_date`, ADD `infant_phone` INT NULL DEFAULT NULL AFTER `infant_email`;

-- Thana 17-Jul-2025
ALTER TABLE `form_vl` ADD `test_request_date` VARCHAR(128) NULL DEFAULT NULL AFTER `test_requested_on`;
ALTER TABLE `audit_form_vl` ADD `test_request_date` VARCHAR(128) NULL DEFAULT NULL AFTER `test_requested_on`;

-- Thana 23-Jul-2025
ALTER TABLE `form_vl` ADD `location_of_sample_collection` VARCHAR(20) NULL DEFAULT NULL AFTER `specimen_type`;
ALTER TABLE `audit_form_vl` ADD `location_of_sample_collection` VARCHAR(20) NULL DEFAULT NULL AFTER `specimen_type`;
ALTER TABLE `form_eid` ADD `location_of_sample_collection` VARCHAR(20) NULL DEFAULT NULL AFTER `specimen_type`;
ALTER TABLE `audit_form_eid` ADD `location_of_sample_collection` VARCHAR(20) NULL DEFAULT NULL AFTER `specimen_type`;


-- Amit 18-Aug-2025
ALTER TABLE `form_vl`
  ADD INDEX `idx_sample_code_facility` (`sample_code`, `facility_id`);

ALTER TABLE `form_eid`
  ADD INDEX `idx_sample_code_facility` (`sample_code`, `facility_id`);

ALTER TABLE `form_covid19`
  ADD INDEX `idx_sample_code_facility` (`sample_code`, `facility_id`);

ALTER TABLE `form_hepatitis`
  ADD INDEX `idx_sample_code_facility` (`sample_code`, `facility_id`);

ALTER TABLE `form_tb`
  ADD INDEX `idx_sample_code_facility` (`sample_code`, `facility_id`);

ALTER TABLE `form_cd4`
  ADD INDEX `idx_sample_code_facility` (`sample_code`, `facility_id`);

ALTER TABLE `form_generic`
  ADD INDEX `idx_sample_code_facility` (`sample_code`, `facility_id`);



-- VL
ALTER TABLE `form_vl` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;
ALTER TABLE `audit_form_vl` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;


ALTER TABLE `form_vl`
  ADD INDEX `idx_result_pulled_via_api` (`result_pulled_via_api_datetime`);



-- EID

ALTER TABLE `form_eid` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_eid` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;

ALTER TABLE `form_eid` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;
ALTER TABLE `audit_form_eid` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;

ALTER TABLE `form_eid`
  ADD INDEX `idx_result_pulled_via_api` (`result_pulled_via_api_datetime`);


-- CD4
ALTER TABLE `form_cd4` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;
ALTER TABLE `audit_form_cd4` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;

ALTER TABLE `form_cd4`
  ADD INDEX `idx_result_pulled_via_api` (`result_pulled_via_api_datetime`);

-- COVID-19
ALTER TABLE `form_covid19` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;
ALTER TABLE `audit_form_covid19` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;

ALTER TABLE `form_covid19`
  ADD INDEX `idx_result_pulled_via_api` (`result_pulled_via_api_datetime`);

-- Hepatitis
ALTER TABLE `form_hepatitis` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;
ALTER TABLE `audit_form_hepatitis` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;

ALTER TABLE `form_hepatitis`
  ADD INDEX `idx_result_pulled_via_api` (`result_pulled_via_api_datetime`);


-- TB
ALTER TABLE `form_tb` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;
ALTER TABLE `audit_form_tb` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;

ALTER TABLE `form_tb`
  ADD INDEX `idx_result_pulled_via_api` (`result_pulled_via_api_datetime`);

-- Generic Tests

ALTER TABLE `form_generic` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_generic` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;

ALTER TABLE `form_generic` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;
ALTER TABLE `audit_form_generic` ADD COLUMN `result_pulled_via_api_datetime` DATETIME NULL AFTER `result_sent_to_source_datetime`;

ALTER TABLE `form_generic`
  ADD INDEX `idx_result_pulled_via_api` (`result_pulled_via_api_datetime`);


-- Thana 03-Aug-2025
ALTER TABLE `tb_tests` 
ADD `lab_id` INT NULL DEFAULT NULL AFTER `tb_id`, 
ADD `specimen_type` VARCHAR(255) NULL DEFAULT NULL AFTER `lab_id`, 
ADD `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL AFTER `specimen_type`, 
ADD `is_sample_rejected` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_received_at_lab_datetime`, 
ADD `reason_for_sample_rejection` VARCHAR(255) NULL DEFAULT NULL AFTER `is_sample_rejected`, 
ADD `rejection_on` DATETIME NULL DEFAULT NULL AFTER `reason_for_sample_rejection`, 
ADD `test_type` VARCHAR(255) NULL DEFAULT NULL AFTER `rejection_on`, 
ADD `sample_tested_datetime` DATETIME NULL DEFAULT NULL AFTER `test_type`, 
ADD `result_reviewed_by` VARCHAR(255) NULL DEFAULT NULL AFTER `test_result`, 
ADD `result_reviewed_datetime` DATETIME NULL DEFAULT NULL AFTER `result_reviewed_by`, 
ADD `result_approved_by` VARCHAR(255) NULL DEFAULT NULL AFTER `result_reviewed_datetime`, 
ADD `result_approved_datetime` DATETIME NULL DEFAULT NULL AFTER `result_approved_by`;

-- Thana 04-Aug-2025
ALTER TABLE `form_tb` ADD `risk_factors` VARCHAR(256) NULL DEFAULT NULL AFTER `reason_for_tb_test`, ADD `purpose_of_test` VARCHAR(256) NULL DEFAULT NULL AFTER `risk_factors`;
ALTER TABLE `audit_form_tb` ADD `risk_factors` VARCHAR(256) NULL DEFAULT NULL AFTER `reason_for_tb_test`, ADD `purpose_of_test` VARCHAR(256) NULL DEFAULT NULL AFTER `risk_factors`;
ALTER TABLE `tb_tests` CHANGE `sample_received_at_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL, CHANGE `is_sample_rejected` `is_sample_rejected` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `reason_for_sample_rejection` `reason_for_sample_rejection` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `rejection_on` `rejection_on` DATETIME NULL DEFAULT NULL, CHANGE `test_type` `test_type` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `sample_tested_datetime` `sample_tested_datetime` DATETIME NULL DEFAULT NULL;

-- Thana 08-Aug-2025
ALTER TABLE `form_tb` DROP `risk_factors`;
ALTER TABLE `audit_form_tb` DROP `risk_factors`;

ALTER TABLE `form_tb` 
  ADD `affiliated_lab_id` INT NULL DEFAULT NULL AFTER `lab_id`, 
  ADD `etb_tracker_number` VARCHAR(24) NULL DEFAULT NULL AFTER `external_sample_code`, 
  ADD `is_patient_initiated_on_tb_treatment` ENUM('yes','no','','') NULL DEFAULT NULL AFTER `hiv_status`,
  ADD `date_of_treatment_initiation` DATE NULL DEFAULT NULL AFTER `is_patient_initiated_on_tb_treatment`, 
  ADD `current_regimen` VARCHAR(24) NULL DEFAULT NULL AFTER `date_of_treatment_initiation`, 
  ADD `date_of_initiation_of_current_regimen` DATE NULL DEFAULT NULL AFTER `current_regimen`, 
  ADD `risk_factors` VARCHAR(36) NULL DEFAULT NULL AFTER `date_of_initiation_of_current_regimen`, 
  ADD `risk_factor_other` VARCHAR(36) NULL DEFAULT NULL AFTER `risk_factors`; 

ALTER TABLE `audit_form_tb` 
  ADD `affiliated_lab_id` INT NULL DEFAULT NULL AFTER `lab_id`, 
  ADD `etb_tracker_number` VARCHAR(24) NULL DEFAULT NULL AFTER `external_sample_code`, 
  ADD `is_patient_initiated_on_tb_treatment` ENUM('yes','no','','') NULL DEFAULT NULL AFTER `hiv_status`,
  ADD `date_of_treatment_initiation` DATE NULL DEFAULT NULL AFTER `is_patient_initiated_on_tb_treatment`, 
  ADD `current_regimen` VARCHAR(24) NULL DEFAULT NULL AFTER `date_of_treatment_initiation`, 
  ADD `date_of_initiation_of_current_regimen` DATE NULL DEFAULT NULL AFTER `current_regimen`, 
  ADD `risk_factors` VARCHAR(36) NULL DEFAULT NULL AFTER `date_of_initiation_of_current_regimen`, 
  ADD `risk_factor_other` VARCHAR(36) NULL DEFAULT NULL AFTER `risk_factors`; 

