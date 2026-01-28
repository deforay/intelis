-- Migration file for version 5.4.0
-- Created on 2025-11-19 08:39:49


UPDATE `system_config` SET `value` = '5.4.0' WHERE `system_config`.`name` = 'sc_version';

-- Amit 16-Dec-2025
ALTER TABLE `form_tb` CHANGE `xpert_mtb_result` `xpert_mtb_result` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `sample_code_format` `sample_code_format` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `remote_sample_code_format` `remote_sample_code_format` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `is_specimen_reordered` `is_specimen_reordered` ENUM('yes','no','') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `is_patient_initiated_on_tb_treatment` `is_patient_initiated_on_tb_treatment` ENUM('yes','no','') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;

ALTER TABLE `form_tb` ADD `tb_lam_result` VARCHAR(64) NULL DEFAULT NULL AFTER `xpert_mtb_result`;

-- Add api_token column to track_api_requests for bearer token search
ALTER TABLE `track_api_requests` ADD `api_token` VARCHAR(255) NULL DEFAULT NULL AFTER `data_format`;
ALTER TABLE `track_api_requests` ADD INDEX `api_token` (`api_token`);


-- Amit 27-Jan-2026


INSERT IGNORE INTO `privileges` 
(`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES 
(NULL, 'tb-results', '/tb/results/tb-referral-list.php', NULL, 'Refer to another lab', '2', 'always');

INSERT IGNORE INTO `privileges` 
(`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES 
(NULL, 'tb-results', '/tb/results/add-tb-referral.php', NULL, 'Add Referral', '2', 'always');

UPDATE `s_app_menu` SET `display_text` = 'Refer to another lab' WHERE link = '/tb/results/tb-referral-list.php';
UPDATE `s_app_menu` SET `display_text` = 'Add Referral' WHERE link = '/tb/results/add-tb-referral.php';

-- Amit 27-Jan-2026 - Change tests_requested from JSON to VARCHAR for Rwanda forms
ALTER TABLE `form_tb` CHANGE `tests_requested` `tests_requested` VARCHAR(512) NULL DEFAULT NULL;
ALTER TABLE `audit_form_tb` CHANGE `tests_requested` `tests_requested` VARCHAR(512) NULL DEFAULT NULL;

-- Amit 27-Jan-2026 - Add affiliated_district_hospital column for Rwanda forms
ALTER TABLE `form_tb` ADD `affiliated_district_hospital` VARCHAR(255) NULL DEFAULT NULL AFTER `affiliated_lab_id`;
ALTER TABLE `audit_form_tb` ADD `affiliated_district_hospital` VARCHAR(255) NULL DEFAULT NULL AFTER `affiliated_lab_id`;

-- Amit 27-Jan-2026 - Change patient_type from JSON to VARCHAR for Rwanda forms (single select)
ALTER TABLE `form_tb` CHANGE `patient_type` `patient_type` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `audit_form_tb` CHANGE `patient_type` `patient_type` VARCHAR(255) NULL DEFAULT NULL;

-- Amit 28-Jan-2026 - Add global config for duplicate detection
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`)
VALUES ('Enable Duplicate Detection', 'enable_duplicate_detection', 'yes', 'general')
ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`);
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