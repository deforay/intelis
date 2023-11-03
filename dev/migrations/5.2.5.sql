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

