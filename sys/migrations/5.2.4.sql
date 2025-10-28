

-- Jeyabanu 7-Sep-2023
ALTER TABLE `form_vl` ADD `cv_number` INT NULL DEFAULT NULL AFTER `request_clinician_phone_number`;
ALTER TABLE `audit_form_vl` ADD `cv_number` INT NULL DEFAULT NULL AFTER `request_clinician_phone_number`;

ALTER TABLE `form_eid` ADD `mother_hiv_test_date` DATE NULL DEFAULT NULL AFTER `child_prophylactic_arv_other`;
ALTER TABLE `audit_form_eid` ADD `mother_hiv_test_date` DATE NULL DEFAULT NULL AFTER `child_prophylactic_arv_other`;

ALTER TABLE `form_eid` ADD `serological_test` VARCHAR(11) NULL DEFAULT NULL AFTER `rapid_test_result`;
ALTER TABLE `audit_form_eid` ADD `serological_test` VARCHAR(11) NULL DEFAULT NULL AFTER `rapid_test_result`;

ALTER TABLE `form_eid` ADD `pcr_1_test_date` DATE NULL DEFAULT NULL AFTER `serological_test`;
ALTER TABLE `form_eid` ADD `pcr_1_test_result` VARCHAR(50) NULL DEFAULT NULL AFTER `pcr_1_test_date`;
ALTER TABLE `form_eid` ADD `pcr_2_test_date` DATE NULL DEFAULT NULL AFTER `pcr_1_test_result`;
ALTER TABLE `form_eid` ADD `pcr_2_test_result` VARCHAR(50) NULL DEFAULT NULL AFTER `pcr_2_test_date`;
ALTER TABLE `form_eid` ADD `pcr_3_test_date` DATE NULL DEFAULT NULL AFTER `pcr_2_test_result`;
ALTER TABLE `form_eid` ADD `pcr_3_test_result` VARCHAR(50) NULL DEFAULT NULL AFTER `pcr_3_test_date`;

ALTER TABLE `audit_form_eid` ADD `pcr_1_test_date` DATE NULL DEFAULT NULL AFTER `serological_test`;
ALTER TABLE `audit_form_eid` ADD `pcr_1_test_result` VARCHAR(50) NULL DEFAULT NULL AFTER `pcr_1_test_date`;
ALTER TABLE `audit_form_eid` ADD `pcr_2_test_date` DATE NULL DEFAULT NULL AFTER `pcr_1_test_result`;
ALTER TABLE `audit_form_eid` ADD `pcr_2_test_result` VARCHAR(50) NULL DEFAULT NULL AFTER `pcr_2_test_date`;
ALTER TABLE `audit_form_eid` ADD `pcr_3_test_date` DATE NULL DEFAULT NULL AFTER `pcr_2_test_result`;
ALTER TABLE `audit_form_eid` ADD `pcr_3_test_result` VARCHAR(50) NULL DEFAULT NULL AFTER `pcr_3_test_date`;

ALTER TABLE `form_eid` ADD `is_sample_recollected` VARCHAR(11) NULL DEFAULT NULL AFTER `sample_collection_date`;
ALTER TABLE `audit_form_eid` ADD `is_sample_recollected` VARCHAR(11) NULL DEFAULT NULL AFTER `sample_collection_date`;

-- Jeyabanu 8-Sep-2023
ALTER TABLE `form_vl` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sms_sent_datetime`;
ALTER TABLE `audit_form_vl` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sms_sent_datetime`;

ALTER TABLE `form_eid` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_datetime`;
ALTER TABLE `audit_form_eid` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_datetime`;

ALTER TABLE `form_covid19` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_datetime`;
ALTER TABLE `audit_form_covid19` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_datetime`;

ALTER TABLE `form_hepatitis` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_datetime`;
ALTER TABLE `audit_form_hepatitis` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_datetime`;

ALTER TABLE `form_tb` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_datetime`;
ALTER TABLE `audit_form_tb` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_datetime`;


ALTER TABLE `form_vl` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;
ALTER TABLE `audit_form_vl` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;

ALTER TABLE `form_eid` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;
ALTER TABLE `audit_form_eid` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;

ALTER TABLE `form_covid19` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;
ALTER TABLE `audit_form_covid19` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;

ALTER TABLE `form_hepatitis` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;
ALTER TABLE `audit_form_hepatitis` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;

ALTER TABLE `form_tb` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;
ALTER TABLE `audit_form_tb` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;

ALTER TABLE `form_generic` ADD `result_printed_on_sts_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_datetime`;
ALTER TABLE `form_generic` ADD `result_printed_on_lis_datetime` DATETIME NULL DEFAULT NULL AFTER `result_printed_on_sts_datetime`;

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Default Phone Prefix', 'default_phone_prefix', NULL, 'general', 'no', NULL, NULL, 'active'), ('Minimum Length of Phone Number', 'min_phone_length', NULL, 'general', 'no', NULL, NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Maximum Length of Phone Number', 'max_phone_length', NULL, 'general', 'no', NULL, NULL, 'active');

-- Amit 21-Sep-2023 version 5.2.4
UPDATE `system_config` SET `value` = '5.2.4' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 29-Sep-2023
ALTER TABLE `form_eid` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `province_id`;
ALTER TABLE `audit_form_eid` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `province_id`;

ALTER TABLE `form_eid` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;
ALTER TABLE `audit_form_eid` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;

ALTER TABLE `form_covid19` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `province_id`;
ALTER TABLE `audit_form_covid19` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `province_id`;

ALTER TABLE `form_covid19` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;
ALTER TABLE `audit_form_covid19` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;

ALTER TABLE `form_hepatitis` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `province_id`;
ALTER TABLE `audit_form_hepatitis` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `province_id`;

ALTER TABLE `form_hepatitis` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;
ALTER TABLE `audit_form_hepatitis` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;

ALTER TABLE `form_tb` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `other_referring_unit`;
ALTER TABLE `audit_form_tb` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `other_referring_unit`;

ALTER TABLE `form_tb` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;
ALTER TABLE `audit_form_tb` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Viral Load Export Format', 'vl_excel_export_format', 'default', 'VL', 'no', NULL, '', 'active');
-- Amit 04-Oct-2023
DELETE FROM global_config WHERE `global_config`.`name` = 'sync_path';
INSERT INTO `global_config`
(`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES
('Date Format', 'gui_date_format', 'd-M-Y', 'general', 'no', null, null, 'active');


-- Jeyabanu 06-Oct-2023
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES
('Display Encrypt PII Option', 'display_encrypt_pii_option', 'no', 'general', 'no', NULL, null, 'active');

-- Thana 09-Oct-2023
UPDATE `privileges` SET `resource_id` = 'generic-results' WHERE `privileges`.`privilege_id` = 260;
UPDATE `privileges` SET `shared_privileges`='["/generic-tests/mail/mail-generic-tests-results.php","/generic-tests/mail/generic-tests-result-mail-confirm.php"]' WHERE `privilege_name` = '/generic-tests/results/generic-print-result.php';
INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'generic-tests', NULL, 'no', 'Send Result Mail', '/generic-tests/mail/mail-generic-tests-results.php', '/generic-tests/mail/generic-tests-result-mail-confirm.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericTestResultMenu', '62', '88', 'active', CURRENT_TIMESTAMP);

-- Thana 10-Oct-2023
INSERT INTO `global_config`
(`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES
('Training Mode', 'training_mode', 'no', 'common', 'no', CURRENT_TIMESTAMP, null, 'active'),
('Training Mode Text', 'training_mode_text', 'TRAINING SERVER', 'common', 'no', CURRENT_TIMESTAMP, null, 'active');

-- Thana 12-Oct-2023
UPDATE `privileges` SET `shared_privileges` = '[\"/generic-tests/configuration/add-test-type.php\",\"/generic-tests/configuration/edit-test-type.php\",\"/generic-tests/configuration/clone-test-type.php\"]' WHERE `privileges`.`privilege_name` = '/generic-tests/configuration/test-type.php';
UPDATE `s_app_menu` SET `inner_pages` = '/generic-tests/configuration/add-test-type.php,/generic-tests/configuration/edit-test-type.php,/generic-tests/configuration/clone-test-type.php' WHERE `s_app_menu`.`link` = '/generic-tests/configuration/test-type.php';


-- Jeyabanu 16-Oct-2023
ALTER TABLE `form_vl` CHANGE `patient_art_no` `patient_art_no` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_vl` CHANGE `patient_first_name` `patient_first_name` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_vl` CHANGE `patient_middle_name` `patient_middle_name` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_vl` CHANGE `patient_last_name` `patient_last_name` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

ALTER TABLE `form_eid` CHANGE `child_id` `child_id` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_eid` CHANGE `child_name` `child_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_eid` CHANGE `child_surname` `child_surname` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

-- Amit 17-Oct-2023
CREATE TABLE `scheduled_jobs` (
 `job_id` int NOT NULL AUTO_INCREMENT,
 `job` text COLLATE utf8mb4_general_ci,
 `requested_on` datetime DEFAULT CURRENT_TIMESTAMP,
 `requested_by` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `scheduled_on` datetime DEFAULT NULL,
 `run_once` varchar(3) COLLATE utf8mb4_general_ci NULL DEFAULT 'no',
 `completed_on` datetime DEFAULT NULL,
 `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
 PRIMARY KEY (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Amit 18-Oct-2023 version 5.2.5
UPDATE `system_config` SET `value` = '5.2.5' WHERE `system_config`.`name` = 'sc_version';
