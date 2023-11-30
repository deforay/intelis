-- Amit 18-Oct-2023 version 5.2.5
UPDATE `system_config` SET `value` = '5.2.5' WHERE `system_config`.`name` = 'sc_version';


-- Amit 26-Oct-2023
ALTER TABLE `vl_imported_controls` ADD `import_machine_file_name` VARCHAR(256) NULL DEFAULT NULL AFTER `imported_date_time`;
ALTER TABLE `eid_imported_controls` ADD `import_machine_file_name` VARCHAR(256) NULL DEFAULT NULL AFTER `imported_date_time`;
ALTER TABLE `covid19_imported_controls` ADD `import_machine_file_name` VARCHAR(256) NULL DEFAULT NULL AFTER `imported_date_time`;

-- Jeyabanu 26-Oct-2023
ALTER TABLE `form_vl` CHANGE `cv_number` `cv_number` VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE `audit_form_vl` CHANGE `cv_number` `cv_number` VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE `temp_sample_import` ADD `cv_number` VARCHAR(256) NULL DEFAULT NULL AFTER `lab_phone_number`;

-- Jeyabanu 03-Nov-2023
INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'vl', NULL, 'no', 'E-mail Test Result', '/mail/vlResultMail.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlResultMailMenu', '70', '101', 'active', CURRENT_TIMESTAMP);
UPDATE `privileges` SET `resource_id` = 'vl-results' WHERE `privileges`.`privilege_name` = '/mail/vlResultMail.php';


-- Jeyabanu 03-Nov-2023
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Minimum Patient ID Length', 'vl_min_patient_id_length', NULL, 'vl', 'no', NULL, NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Minimum Patient ID Length', 'eid_min_patient_id_length', NULL, 'eid', 'no', NULL, NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Minimum Patient ID Length', 'covid19_min_patient_id_length', NULL, 'covid19', 'no', NULL, NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Minimum Patient ID Length', 'hepatitis_min_patient_id_length', NULL, 'hepatitis', 'no', NULL, NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Minimum Patient ID Length', 'tb_min_patient_id_length', NULL, 'tb', 'no', NULL, NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Minimum Patient ID Length', 'generic_min_patient_id_length', NULL, 'generic', 'no', NULL, NULL, 'active');

UPDATE `privileges`
SET `shared_privileges` = '["/batch/delete-batch.php?type=hepatitis","/batch/edit-batch-position.php?type=hepatitis"]'
WHERE `privilege_name` = '/batch/edit-batch.php?type=hepatitis';


-- Thana 10-Nov-2023
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'generic-requests', '/generic-tests/requests/clone-request.php', NULL, 'Clone Generic Tests', '7', 'always');
-- Jeyabanu 14-Nov-2023
-- ALTER TABLE `patients` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `patient_id`;
-- ALTER TABLE `patients` ADD `patient_phone_number` VARCHAR(50) NULL DEFAULT NULL AFTER `patient_gender`;
-- ALTER TABLE `patients` ADD `patient_age_in_years` INT NULL DEFAULT NULL AFTER `patient_phone_number`;
-- ALTER TABLE `patients` ADD `patient_dob` DATE NULL DEFAULT NULL AFTER `patient_age_in_years`;
-- ALTER TABLE `patients` ADD `patient_address` TEXT NULL DEFAULT NULL AFTER `patient_dob`;
-- ALTER TABLE `patients` ADD `is_patient_pregnant` VARCHAR(10) NULL DEFAULT NULL AFTER `patient_address`;
-- ALTER TABLE `patients` ADD `is_patient_breastfeeding` VARCHAR(10) NULL DEFAULT NULL AFTER `is_patient_pregnant`;
-- ALTER TABLE `patients` ADD `patient_age_in_months` INT NULL DEFAULT NULL AFTER `patient_age_in_years`;

-- Jeyabanu 16-Nov-2023
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('patients', 'common', 'Manage Patients');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'patients', 'view-patients.php', NULL, 'Manage Patients', NULL, 'always'), (NULL, 'patients', 'add-patient.php', NULL, 'Add Patient', NULL, 'always'), (NULL, 'patients', 'edit-patient.php', NULL, 'Edit Patient', NULL, 'always');
-- ALTER TABLE `patients` ADD `status` VARCHAR(11) NULL DEFAULT NULL AFTER `patient_district`;


-- Jeyabanu 22-Nov-2023
UPDATE `privileges` SET `display_name` = 'Add Sample Type' WHERE `privileges`.`privilege_name` = '/generic-tests/requests/addSampleType.php';
UPDATE `privileges` SET `display_name` = 'Add Symptom' WHERE `privileges`.`privilege_name` = '/generic-tests/requests/addSymptoms.php';
UPDATE `privileges` SET `display_name` = 'Add Test Reason' WHERE `privileges`.`privilege_name` = '/generic-tests/requests/addTestingReason.php';

UPDATE `privileges` SET `display_name` = 'Edit Sample Type' WHERE `privileges`.`privilege_name` = '/generic-tests/requests/editSampleType.php';
UPDATE `privileges` SET `display_name` = 'Edit Symptom' WHERE `privileges`.`privilege_name` = '/generic-tests/requests/editSymptoms.php';
UPDATE `privileges` SET `display_name` = 'Edit Test Reason' WHERE `privileges`.`privilege_name` = '/generic-tests/requests/editTestingReason.php';

UPDATE `privileges` SET `display_name` = 'Access Sample Type' WHERE `privileges`.`privilege_name` = '/generic-tests/requests/sampleType.php';
UPDATE `privileges` SET `display_name` = 'Access Symptom' WHERE `privileges`.`privilege_name` = '/generic-tests/requests/symptoms.php';
UPDATE `privileges` SET `display_name` = 'Access Test Reason' WHERE `privileges`.`privilege_name` = '/generic-tests/requests/testingReason.php';
