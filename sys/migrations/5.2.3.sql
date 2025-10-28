
-- Jeyabanu 25-08-2023
ALTER TABLE `form_vl` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `patient_art_no`;
ALTER TABLE `audit_form_vl` ADD `is_encrypted` VARCHAR(10) NULL DEFAULT 'no' AFTER `patient_art_no`;

ALTER TABLE `form_vl` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;
ALTER TABLE `audit_form_vl` ADD `sync_patient_identifiers` VARCHAR(10) NULL DEFAULT 'yes' AFTER `is_encrypted`;


-- Jeyabanu 29-08-2023
ALTER TABLE `form_generic` ADD `laboratory_number` VARCHAR(100) NULL DEFAULT NULL AFTER `patient_id`;
ALTER TABLE `audit_form_generic` ADD `laboratory_number` VARCHAR(100) NULL DEFAULT NULL AFTER `patient_id`;


-- Jeyabanu 31-08-2023
ALTER TABLE `s_app_menu` ADD `sub_module` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `module`;
UPDATE `s_app_menu` SET `sub_module` = 'generic-tests' WHERE `s_app_menu`.`display_text` = 'Other Lab Tests Config';
UPDATE `s_app_menu` SET `sub_module` = 'vl' WHERE `s_app_menu`.`display_text` = 'VL Config';
UPDATE `s_app_menu` SET `sub_module` = 'eid' WHERE `s_app_menu`.`display_text` = 'EID Config';
UPDATE `s_app_menu` SET `sub_module` = 'covid19' WHERE `s_app_menu`.`display_text` = 'Covid-19 Config';
UPDATE `s_app_menu` SET `sub_module` = 'hepatitis' WHERE `s_app_menu`.`display_text` = 'Hepatitis Config';
UPDATE `s_app_menu` SET `sub_module` = 'tb' WHERE `s_app_menu`.`display_text` = 'TB Config';



-- Amit 4-Sep-2023 version 5.2.3
UPDATE `system_config` SET `value` = '5.2.3' WHERE `system_config`.`name` = 'sc_version';
