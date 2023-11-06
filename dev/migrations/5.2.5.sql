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
