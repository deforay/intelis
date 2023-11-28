-- Amit 22-Nov-2023 version 5.2.6
UPDATE `system_config` SET `value` = '5.2.6' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 24-Nov-2023
ALTER TABLE `patients` CHANGE `patient_code_key` `patient_code_key` INT NULL DEFAULT NULL;

-- Jeyabanu 27-Nov-2023
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('CSV Delimiter', 'default_csv_delimiter', ',', 'general', 'no', NULL, NULL, 'active'), ('CSV Enclosure', 'default_csv_enclosure', '"', 'general', 'no', NULL, NULL, 'active');

-- Thana 28-Nov-2023
ALTER TABLE `generic_test_result_units_map` ADD FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types`(`test_type_id`) ON DELETE RESTRICT ON UPDATE RESTRICT; ALTER TABLE `generic_test_result_units_map` ADD FOREIGN KEY (`unit_id`) REFERENCES `r_generic_test_result_units`(`unit_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `patients` ADD `is_encrypted` VARCHAR(256) NULL DEFAULT NULL AFTER `patient_code`;
ALTER TABLE `generic_test_results` ADD `sub_test_name` VARCHAR(256) NULL DEFAULT NULL AFTER `facility_id`;
ALTER TABLE `form_generic` ADD `sub_tests` TEXT NULL DEFAULT NULL AFTER `test_type`;
ALTER TABLE `generic_test_results` ADD `final_result_unit` VARCHAR(256) NULL DEFAULT NULL AFTER `sub_test_name`, ADD `result_type` VARCHAR(256) NULL DEFAULT NULL AFTER `final_result_unit`;
ALTER TABLE `generic_test_results` ADD `final_result` VARCHAR(256) NULL DEFAULT NULL AFTER `result`;


