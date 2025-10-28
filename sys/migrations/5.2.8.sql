-- Amit 26-Dec-2023 version 5.2.8
UPDATE `system_config` SET `value` = '5.2.8' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 26-Dec-2023
CREATE TABLE `temp_mail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_type` varchar(25) DEFAULT NULL,
  `samples` varchar(256) DEFAULT NULL,
  `to_mail` varchar(255) DEFAULT NULL,
  `report_email` varchar(256) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `text_message` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` varchar(11) DEFAULT NULL,
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Amit 27-Dec-2023
UPDATE form_vl
SET patient_art_no = REPLACE(patient_art_no, 'string', '')
WHERE patient_art_no like '%string';

-- Jeyabanu 29-Dec-2023
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'generic-results', '/generic-tests/results/email-results.php', '[\"/vl/results/email-results.php\", \"/vl/results/email-results-confirm.php\"\r\n]', 'Email Test Result', NULL, 'always');

INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'generic-tests', NULL, 'no', 'E-mail Test Result', '/generic-tests/results/email-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlResultMailMenu', '62', '177', 'active', CURRENT_TIMESTAMP);


-- Amit 08-Jan-2024
-- Adding these again

ALTER TABLE `audit_form_vl` DROP `is_adherance_poor`;
ALTER TABLE `audit_form_vl` DROP `number_of_enhanced_sessions`;
ALTER TABLE `audit_form_vl` DROP `patient_tb`;
ALTER TABLE `audit_form_vl` DROP `patient_tb_yes`;
ALTER TABLE `audit_form_vl` DROP `patient_drugs_transmission`;
ALTER TABLE `audit_form_vl` DROP `patient_receiving_therapy`;
ALTER TABLE `audit_form_vl` DROP `patient_art_date`;
ALTER TABLE `audit_form_vl` DROP `consultation`;
ALTER TABLE `audit_form_vl` DROP `first_viral_load`;
ALTER TABLE `audit_form_vl` DROP `sample_processed`;
ALTER TABLE `audit_form_vl` DROP `collection_type`;
ALTER TABLE `audit_form_vl` DROP `collection_site`;
ALTER TABLE `audit_form_vl` DROP `requesting_vl_service_sector`;
ALTER TABLE `audit_form_vl` DROP `requesting_category`;
ALTER TABLE `audit_form_vl` DROP `requesting_professional_number`;

ALTER TABLE `form_vl` DROP `sample_code_title`;
ALTER TABLE `audit_form_vl` DROP `sample_code_title`;

ALTER TABLE `form_vl` CHANGE `is_request_mail_sent` `is_request_mail_sent` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_vl` CHANGE `is_request_mail_sent` `is_request_mail_sent` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

ALTER TABLE `form_vl` CHANGE `is_result_sms_sent` `is_result_sms_sent` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_vl` CHANGE `is_result_sms_sent` `is_result_sms_sent` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

ALTER TABLE `form_vl` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_vl` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

ALTER TABLE `form_generic` CHANGE `is_request_mail_sent` `is_request_mail_sent` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_generic` CHANGE `is_request_mail_sent` `is_request_mail_sent` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';


-- Jeyabanu 09-Jan-2024
ALTER TABLE `instruments` ADD `additional_text` VARCHAR(256) NULL DEFAULT NULL AFTER `low_vl_result_text`;

-- Jeyabanu 10-Jan-2024
ALTER TABLE `generic_test_results` ADD `final_result_interpretation` TEXT NULL DEFAULT NULL AFTER `result_unit`;
ALTER TABLE `instruments` ADD `reviewed_by` TEXT NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `instruments` ADD `approved_by` TEXT NULL DEFAULT NULL AFTER `reviewed_by`;
ALTER TABLE `instruments` CHANGE `config_id` `config_id` VARCHAR(50) NOT NULL;

ALTER TABLE `form_vl` ADD `instrument_id` VARCHAR(50) NULL DEFAULT NULL AFTER `vl_test_platform`;
ALTER TABLE `audit_form_vl` ADD `instrument_id` VARCHAR(50) NULL DEFAULT NULL AFTER `vl_test_platform`;
ALTER TABLE `form_eid` ADD `instrument_id` VARCHAR(50) NULL DEFAULT NULL AFTER `eid_test_platform`;
ALTER TABLE `audit_form_eid` ADD `instrument_id` VARCHAR(50) NULL DEFAULT NULL AFTER `eid_test_platform`;
ALTER TABLE `form_hepatitis` ADD `instrument_id` VARCHAR(50) NULL DEFAULT NULL AFTER `hepatitis_test_platform`;
ALTER TABLE `audit_form_hepatitis` ADD `instrument_id` VARCHAR(50) NULL DEFAULT NULL AFTER `hepatitis_test_platform`;
ALTER TABLE `form_tb` ADD `instrument_id` VARCHAR(50) NULL DEFAULT NULL AFTER `tb_test_platform`;
ALTER TABLE `audit_form_tb` ADD `instrument_id` VARCHAR(50) NULL DEFAULT NULL AFTER `tb_test_platform`;
ALTER TABLE `covid19_tests` ADD `instrument_id` VARCHAR(50) NULL DEFAULT NULL AFTER `testing_platform`;

-- Jeyabanu 11-Jan-2024
ALTER TABLE `form_eid` ADD `request_clinician_phone_number` VARCHAR(32) NULL DEFAULT NULL AFTER `clinician_name`;
ALTER TABLE `audit_form_eid` ADD `request_clinician_phone_number` VARCHAR(32) NULL DEFAULT NULL AFTER `clinician_name`;

-- Amit 12-Jan-2024

ALTER TABLE `form_eid` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_eid` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

ALTER TABLE `form_covid19` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_covid19` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

ALTER TABLE `form_tb` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_tb` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

ALTER TABLE `form_hepatitis` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_hepatitis` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

ALTER TABLE `form_generic` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_generic` CHANGE `sample_reordered` `sample_reordered` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';


UPDATE form_vl SET sample_reordered = 'no' WHERE sample_reordered is null;
UPDATE form_eid SET sample_reordered = 'no' WHERE sample_reordered is null;
UPDATE form_covid19 SET sample_reordered = 'no' WHERE sample_reordered is null;
UPDATE form_tb SET sample_reordered = 'no' WHERE sample_reordered is null;
UPDATE form_hepatitis SET sample_reordered = 'no' WHERE sample_reordered is null;
UPDATE form_generic SET sample_reordered = 'no' WHERE sample_reordered is null;



-- Jeyabanu 12-Jan-2024
ALTER TABLE `instruments` CHANGE `config_id` `instrument_id` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE `instrument_controls` CHANGE `config_id` `instrument_id` VARCHAR(50) NOT NULL;
ALTER TABLE `instrument_machines` CHANGE `config_id` `instrument_id` VARCHAR(50) NOT NULL;



-- Amit 18-Jan-2024

CREATE TABLE IF NOT EXISTS s_run_once_scripts_log (
    `script_name` VARCHAR(255) PRIMARY KEY,
    `execution_date` DATETIME,
    `status` VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Jeyabanu 19-Jan-2024

ALTER TABLE `form_eid` ADD `sample_dispatcher_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `sample_dispatched_datetime`, ADD `sample_dispatcher_phone` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `sample_dispatcher_name`;
ALTER TABLE `audit_form_eid` ADD `sample_dispatcher_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `sample_dispatched_datetime`, ADD `sample_dispatcher_phone` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `sample_dispatcher_name`;

-- Jeyabanu 22-Jan-2024

ALTER TABLE `batch_details` CHANGE `machine` `machine` VARCHAR(50) NOT NULL;

-- Jeyabanu 23-Jan-2024
ALTER TABLE `form_vl` DROP Column `sync_patient_identifiers`;
ALTER TABLE `audit_form_vl` DROP Column `sync_patient_identifiers`;

ALTER TABLE `form_eid` DROP Column `sync_patient_identifiers`;
ALTER TABLE `audit_form_eid` DROP Column `sync_patient_identifiers`;

ALTER TABLE `form_covid19` DROP Column `sync_patient_identifiers`;
ALTER TABLE `audit_form_covid19` DROP Column `sync_patient_identifiers`;

ALTER TABLE `form_hepatitis` DROP Column `sync_patient_identifiers`;
ALTER TABLE `audit_form_hepatitis` DROP Column `sync_patient_identifiers`;

ALTER TABLE `form_tb` DROP Column `sync_patient_identifiers`;
ALTER TABLE `audit_form_tb` DROP Column `sync_patient_identifiers`;

ALTER TABLE `form_vl` CHANGE `sample_type` `specimen_type` INT NULL DEFAULT NULL;
ALTER TABLE `audit_form_vl` CHANGE `sample_type` `specimen_type` INT NULL DEFAULT NULL;

ALTER TABLE `form_generic` CHANGE `sample_type` `specimen_type` INT NULL DEFAULT NULL;
ALTER TABLE `audit_form_generic` CHANGE `sample_type` `specimen_type` INT NULL DEFAULT NULL;

-- Brindha 29-Jan-2024
INSERT INTO `s_app_menu` (`id`, `module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES
(NULL, 'generic-tests', 'no', 'Clinic Reports', '/generic-tests/program-management/generic-tests-clinic-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericClinicReport', '63', '92', 'active', NULL);

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'generic-management', 'generic-tests-clinic-report.php', 'Clinic Report');


-- Jeyabanu 30-Jan-2024
ALTER TABLE `form_vl` ADD `key_population` VARCHAR(10) NULL DEFAULT NULL AFTER `patient_gender`;
ALTER TABLE `audit_form_vl` ADD `key_population` VARCHAR(10) NULL DEFAULT NULL AFTER `patient_gender`;



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

