
-- Amit 12-Apr-2022 version 4.5.0
UPDATE `system_config` SET `value` = '4.5.0' WHERE `system_config`.`name` = 'sc_version';


-- Thana 18-Apr-2022
ALTER TABLE `form_eid` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;

-- Amit 28-Apr-2022
UPDATE `privileges` SET `privilege_name` = 'vl-export-data.php', `display_name` = 'Export VL Data' WHERE `privileges`.`privilege_id` = 23;
ALTER TABLE `form_vl` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;

-- Amit 02-May-2022
ALTER TABLE `form_covid19` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;
ALTER TABLE `form_hepatitis` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;
ALTER TABLE `form_tb` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;
ALTER TABLE `temp_sample_import` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `result_reviewed_by`;
ALTER TABLE `hold_sample_import` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `result_reviewed_by`;
ALTER TABLE `covid19_imported_controls` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `result_reviewed_by`;
ALTER TABLE `vl_imported_controls` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `result_reviewed_by`;
UPDATE `form_vl` SET `lab_tech_comments` = approver_comments;
UPDATE `form_eid` SET `lab_tech_comments` = approver_comments;
UPDATE `form_covid19` SET `lab_tech_comments` = approver_comments;
UPDATE `form_hepatitis` SET `lab_tech_comments` = approver_comments;
UPDATE `form_tb` SET `lab_tech_comments` = approver_comments;
UPDATE `temp_sample_import` SET `lab_tech_comments` = approver_comments;
UPDATE `hold_sample_import` SET `lab_tech_comments` = approver_comments;
UPDATE `covid19_imported_controls` SET `lab_tech_comments` = approver_comments;


-- Amit 02-May-2022 version 4.5.1
UPDATE `system_config` SET `value` = '4.5.1' WHERE `system_config`.`name` = 'sc_version';

-- Amit 09-May-2022
INSERT INTO `global_config`
(`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES
('VL Auto Approve API Results', 'vl_auto_approve_api_results', 'no', 'vl', 'no', NULL, NULL, 'active'),
('EID Auto Approve API Results', 'eid_auto_approve_api_results', 'no', 'eid', 'no', NULL, NULL, 'active'),
('COVID-19 Auto Approve API Results', 'covid19_auto_approve_api_results', 'no', 'covid19', 'no', NULL, NULL, 'active'),
('Hepatitis Auto Approve API Results', 'hepatitis_auto_approve_api_results', 'no', 'hepatitis', 'no', NULL, NULL, 'active'),
('TB Auto Approve API Results', 'tb_auto_approve_api_results', 'no', 'tb', 'no', NULL, NULL, 'active');

-- Amit 21-Jun-2022 version 4.5.2
UPDATE `system_config` SET `value` = '4.5.2' WHERE `system_config`.`name` = 'sc_version';


-- Amit 27-Jun-2022 version 4.5.3
UPDATE `system_config` SET `value` = '4.5.3' WHERE `system_config`.`name` = 'sc_version';


-- Jeyabanu 1-July-2022
UPDATE `privileges` SET `privilege_name` = 'activity-log.php', `display_name` = 'User Activity Log' WHERE `privileges`.`privilege_name` = 'audit-trail.php';
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'common-reference', 'audit-trail.php', 'Audit Trail');

-- Thana 06-Jul-2022
ALTER TABLE `user_details` ADD `hash_algorithm` VARCHAR(256) NOT NULL DEFAULT 'sha1' AFTER `app_access`;

-- Amit 08-Jul-2022
ALTER TABLE `user_login_history` CHANGE `login_id` `login_id` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE `user_login_history` ADD `user_id` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `history_id`;

