-- Amit 06-Dec-2023 version 5.2.7
UPDATE `system_config` SET `value` = '5.2.7' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 06-Dec-2023
ALTER TABLE `patients` ADD `data_sync` INT NULL DEFAULT '0' AFTER `patient_registered_by`;

-- Jeyabanu 07-Dec-2023
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'generic-requests', '/generic-tests/requests/edit-locked-generic-tests-samples', NULL, 'Edit Locked Generic Tests Samples', '6', 'always');
UPDATE `s_app_menu` SET `link` = '/vl/results/email-results.php' WHERE `s_app_menu`.`link` = '/mail/vlResultMail.php';
INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'eid', NULL, 'no', 'E-mail Test Result', '/eid/results/email-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlResultMailMenu', '76', '172', 'active', CURRENT_TIMESTAMP);
INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'covid19', NULL, 'no', 'E-mail Test Result', '/covid-19/results/email-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlResultMailMenu', '73', '173', 'active', CURRENT_TIMESTAMP);
INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'hepatitis', NULL, 'no', 'E-mail Test Result', '/hepatitis/results/email-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlResultMailMenu', '79', '175', 'active', CURRENT_TIMESTAMP);
INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'tb', NULL, 'no', 'E-mail Test Result', '/tb/results/email-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlResultMailMenu', '82', '176', 'active', CURRENT_TIMESTAMP);

UPDATE `privileges` SET `privilege_name` = '/vl/results/email-results.php', `shared_privileges` = '[\"/vl/results/email-results.php\", \"/vl/results/email-results.php\"]' WHERE `privileges`.`privilege_name` = '/mail/vlResultMail.php';
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'eid-results', '/eid/results/email-results.php', '[\"/eid/results/email-results.php\", \"/eid/results/email-results-confirmation.php\"]', 'Email Test Result', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'hepatitis-results', '/hepatitis/results/email-results.php', '[\"/hepatitis/results/email-results.php\", \"/hepatitis/results/email-results-confirmation.php\"]', 'Email Test Result', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'tb-results', '/tb/results/email-results.php', '[\"/tb/results/email-results.php\", \"/tb/results/email-results-confirmation.php\"]', 'Email Test Result', NULL, 'always');
UPDATE `privileges` SET `display_name` = 'Email Test Result' WHERE `privileges`.`privilege_name` = '/covid-19/results/email-results.php';


-- Jeyabanu 11-Dec-2023
ALTER TABLE `form_vl` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `approver_comments`;
ALTER TABLE `audit_form_vl` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `approver_comments`;


-- Jeyabanu 12-Dec-2023
ALTER TABLE `form_vl` CHANGE `reason_for_vl_result_changes` `reason_for_result_changes` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
ALTER TABLE `audit_form_vl` CHANGE `reason_for_vl_result_changes` `reason_for_result_changes` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
UPDATE `form_vl` SET `result_modified` = 'yes' WHERE `reason_for_result_changes` IS NOT NULL;
UPDATE `form_vl` SET `result_modified` = 'no' WHERE `result_modified` != 'yes';
