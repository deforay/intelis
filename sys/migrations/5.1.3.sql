
-- Amit 20-Jan-2023 version 5.1.0
UPDATE `system_config` SET `value` = '5.1.0' WHERE `system_config`.`name` = 'sc_version';

-- Thana 31-Jan-2023
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'specimen-referral-manifest', 'move-manifest.php', 'Move Samples');

-- Amit 06-Feb-2023
ALTER TABLE `package_details` ADD `number_of_samples` INT NULL DEFAULT NULL AFTER `lab_id`;
ALTER TABLE `package_details` ADD `last_modified_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `request_created_datetime`;

UPDATE package_details A
INNER JOIN (SELECT sample_package_code, COUNT(*) sampleCount FROM form_vl GROUP BY sample_package_code) as B
  ON B.sample_package_code = A.package_code
SET A.number_of_samples = B.sampleCount;

UPDATE package_details A
INNER JOIN (SELECT sample_package_code, COUNT(*) sampleCount FROM form_eid GROUP BY sample_package_code) as B
  ON B.sample_package_code = A.package_code
SET A.number_of_samples = B.sampleCount;

UPDATE package_details A
INNER JOIN (SELECT sample_package_code, COUNT(*) sampleCount FROM form_covid19 GROUP BY sample_package_code) as B
  ON B.sample_package_code = A.package_code
SET A.number_of_samples = B.sampleCount;

UPDATE package_details A
INNER JOIN (SELECT sample_package_code, COUNT(*) sampleCount FROM form_hepatitis GROUP BY sample_package_code) as B
  ON B.sample_package_code = A.package_code
SET A.number_of_samples = B.sampleCount;

-- Amit 13-Feb-2023
ALTER TABLE `form_vl` ADD INDEX(`sample_package_id`);
ALTER TABLE `form_eid` ADD INDEX(`sample_package_id`);
ALTER TABLE `form_covid19` ADD INDEX(`sample_package_id`);
ALTER TABLE `form_hepatitis` ADD INDEX(`sample_package_id`);
ALTER TABLE `form_tb` CHANGE `sample_package_id` `sample_package_id` INT NULL DEFAULT NULL;
ALTER TABLE `form_tb` ADD INDEX(`sample_package_id`);


-- Amit 16-Feb-2023 version 5.1.1
UPDATE `system_config` SET `value` = '5.1.1' WHERE `system_config`.`name` = 'sc_version';


-- Amit 01-Mar-2023 version 5.1.2
UPDATE `system_config` SET `value` = '5.1.2' WHERE `system_config`.`name` = 'sc_version';


-- Jeyabanu 10-03-2023
ALTER TABLE `r_eid_results` CHANGE `result_id` `result_id` varchar(256) NOT NULL;

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES ('Show Participant Name in Manifest', 'vl_show_participant_name_in_manifest', 'yes', 'VL', 'no', null, null, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES ('Show Participant Name in Manifest', 'eid_show_participant_name_in_manifest', 'yes', 'EID', 'no', null, null, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES ('Show Participant Name in Manifest', 'covid19_show_participant_name_in_manifest', 'yes', 'COVID19', 'no', null, null, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES ('Show Participant Name in Manifest', 'hepatitis_show_participant_name_in_manifest', 'yes', 'HEPATITIS', 'no', null, null, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES ('Show Participant Name in Manifest', 'tb_show_participant_name_in_manifest', 'yes', 'TB', 'no', null, null, 'active');

ALTER TABLE `form_vl` CHANGE `request_created_datetime` `request_created_datetime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `form_eid` CHANGE `request_created_datetime` `request_created_datetime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `form_covid19` CHANGE `request_created_datetime` `request_created_datetime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `form_hepatitis` CHANGE `request_created_datetime` `request_created_datetime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;


-- Amit 17-Mar-2023
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES ('Interpret and Convert VL Results', 'vl_interpret_and_convert_results', 'no', 'VL', 'yes', null, null, 'active');


-- Jeyabanu 21-Mar-2023
ALTER TABLE `batch_details` ADD `created_by` VARCHAR(256) NULL DEFAULT NULL AFTER `label_order`;

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('VL Report QR Code', 'vl_report_qr_code', 'yes', 'vl', 'no', NULL, NULL, 'active');

ALTER TABLE `form_vl` CHANGE `data_sync` `data_sync` INT NOT NULL DEFAULT 0;

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Key', 'key', null, 'general', 'yes', NULL, NULL, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES
            ('EID Report QR Code', 'eid_report_qr_code', 'yes', 'EID', 'no', NULL, NULL, 'active'),
            ('Hepatitis Report QR Code', 'hepatitis_report_qr_code', 'yes', NULL, NULL, NULL, NULL, 'active');

-- Amit 23-Mar-2023
ALTER TABLE `r_eid_results` CHANGE `result_id` `result_id` varchar(256) NOT NULL;
ALTER TABLE `r_tb_results` CHANGE `result_id` `result_id` varchar(256) NOT NULL;

-- Amit 29-Mar-2023
UPDATE `s_available_country_forms` SET `form_name` = 'Sierra Leone' WHERE `vlsm_country_id` = 2;
UPDATE `s_available_country_forms` SET `form_name` = 'Democratic Republic of the Congo' WHERE `vlsm_country_id` = 3;
UPDATE `s_available_country_forms` SET `form_name` = REPLACE(form_name, "Form", "");
UPDATE `s_available_country_forms` SET `form_name` = REPLACE(form_name, "FORM", "");

-- Jeyabanu 30-Mar-2023
ALTER TABLE `form_vl` ADD `treatment_duration` TEXT NULL DEFAULT NULL AFTER `treatment_initiated_date`;
ALTER TABLE `form_vl` ADD `treatment_indication` TEXT NULL DEFAULT NULL AFTER `treatment_duration`;
ALTER TABLE `form_vl` ADD `patient_has_active_tb` TEXT NULL DEFAULT NULL AFTER `is_patient_breastfeeding`;
ALTER TABLE `form_vl` ADD `patient_active_tb_phase` TEXT NULL DEFAULT NULL AFTER `patient_has_active_tb`;
ALTER TABLE `form_vl` ADD `line_of_treatment_failure_assessed` TEXT NULL DEFAULT NULL AFTER `line_of_treatment`;

ALTER TABLE `audit_form_vl` ADD `treatment_duration` TEXT NULL DEFAULT NULL AFTER `treatment_initiated_date`;
ALTER TABLE `audit_form_vl` ADD `treatment_indication` TEXT NULL DEFAULT NULL AFTER `treatment_duration`;
ALTER TABLE `audit_form_vl` ADD `patient_has_active_tb` TEXT NULL DEFAULT NULL AFTER `is_patient_breastfeeding`;
ALTER TABLE `audit_form_vl` ADD `patient_active_tb_phase` TEXT NULL DEFAULT NULL AFTER `patient_has_active_tb`;
ALTER TABLE `audit_form_vl` ADD `line_of_treatment_failure_assessed` TEXT NULL DEFAULT NULL AFTER `line_of_treatment`;

ALTER TABLE `form_eid` ADD `lab_testing_point` TEXT NULL DEFAULT NULL AFTER `lab_id`;
ALTER TABLE `audit_form_eid` ADD `lab_testing_point` TEXT NULL DEFAULT NULL AFTER `lab_id`;

ALTER TABLE `form_eid` ADD `infant_on_pmtct_prophylaxis` TEXT NULL DEFAULT NULL AFTER `has_infant_stopped_breastfeeding`, ADD `infant_on_ctx_prophylaxis` TEXT NULL DEFAULT NULL AFTER `infant_on_pmtct_prophylaxis`;
ALTER TABLE `audit_form_eid` ADD `infant_on_pmtct_prophylaxis` TEXT NULL DEFAULT NULL AFTER `has_infant_stopped_breastfeeding`, ADD `infant_on_ctx_prophylaxis` TEXT NULL DEFAULT NULL AFTER `infant_on_pmtct_prophylaxis`;

ALTER TABLE `form_eid` ADD `pcr_test_number` INT NULL DEFAULT NULL AFTER `pcr_test_performed_before`;
ALTER TABLE `audit_form_eid` ADD `pcr_test_number` INT NULL DEFAULT NULL AFTER `pcr_test_performed_before`;

ALTER TABLE `form_eid` ADD `reason_for_repeat_pcr_other` TEXT NULL DEFAULT NULL AFTER `reason_for_pcr`;
ALTER TABLE `audit_form_eid` ADD `reason_for_repeat_pcr_other` TEXT NULL DEFAULT NULL AFTER `reason_for_pcr`;

ALTER TABLE `form_eid` ADD `mother_regimen` TEXT NULL DEFAULT NULL AFTER `mother_treatment`;
ALTER TABLE `audit_form_eid` ADD `mother_regimen` TEXT NULL DEFAULT NULL AFTER `mother_treatment`;

ALTER TABLE `form_tb` ADD `previously_treated_for_tb` TEXT NULL DEFAULT NULL AFTER `hiv_status`;
ALTER TABLE `audit_form_tb` ADD `previously_treated_for_tb` TEXT NULL DEFAULT NULL AFTER `hiv_status`;

ALTER TABLE `form_tb` ADD `number_of_sputum_samples` INT NULL DEFAULT NULL AFTER `tests_requested`;
ALTER TABLE `audit_form_tb` ADD `number_of_sputum_samples` INT NULL DEFAULT NULL AFTER `tests_requested`;

ALTER TABLE `form_tb` ADD `first_sputum_samples_collection_date` DATE NULL DEFAULT NULL AFTER `number_of_sputum_samples`;
ALTER TABLE `audit_form_tb` ADD `first_sputum_samples_collection_date` DATE NULL DEFAULT NULL AFTER `number_of_sputum_samples`;

ALTER TABLE `form_tb` ADD `result_date` DATETIME NULL DEFAULT NULL AFTER `tested_by`;
ALTER TABLE `audit_form_tb` ADD `result_date` DATETIME NULL DEFAULT NULL AFTER `tested_by`;

-- Amit 03-Apr-2023
ALTER TABLE `log_result_updates` CHANGE `user_id` `user_id` TEXT NULL DEFAULT NULL;

-- Amit 12-Apr-2023
ALTER TABLE `form_vl` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `form_eid` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `form_covid19` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `form_hepatitis` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `form_tb` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

ALTER TABLE `audit_form_vl` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_eid` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_covid19` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_hepatitis` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_tb` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

UPDATE `form_vl` SET locked = 'no' WHERE locked is null or locked not like 'yes';
UPDATE `form_eid` SET locked = 'no' WHERE locked is null or locked not like 'yes';
UPDATE `form_covid19` SET locked = 'no' WHERE locked is null or locked not like 'yes';
UPDATE `form_hepatitis` SET locked = 'no' WHERE locked is null or locked not like 'yes';
UPDATE `form_tb` SET locked = 'no' WHERE locked is null or locked not like 'yes';


-- ilahir 13-Apr-2023

CREATE TABLE `r_test_types` (
  `test_type_id` int NOT NULL,
  `test_standard_name` varchar(256) DEFAULT NULL,
  `test_generic_name` varchar(256) DEFAULT NULL,
  `test_short_code` varchar(256) DEFAULT NULL,
  `test_loinc_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `test_form_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `test_results_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `test_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `r_test_types`
  ADD PRIMARY KEY (`test_type_id`);

ALTER TABLE `r_test_types`
  MODIFY `test_type_id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('test-type', 'admin', 'Manage Test Type');

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'test-type', 'testType.php', 'Access'), (NULL, 'test-type', 'addTestType.php', 'Add');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'test-type', 'editTestType.php', 'Edit');



-- Amit 18-Apr-2023 version 5.1.3
UPDATE `system_config` SET `value` = '5.1.3' WHERE `system_config`.`name` = 'sc_version';

