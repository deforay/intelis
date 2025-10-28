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
ALTER TABLE `form_vl` CHANGE `reason_for_vl_result_changes` `reason_for_result_changes` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `audit_form_vl` CHANGE `reason_for_vl_result_changes` `reason_for_result_changes` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
UPDATE `form_vl` SET `result_modified` = 'yes' WHERE `reason_for_result_changes` IS NOT NULL;
UPDATE `form_vl` SET `result_modified` = 'no' WHERE `result_modified` != 'yes';

-- Amit 12-Dec-2023
DROP TABLE IF EXISTS `sequence_counter`;
CREATE TABLE IF NOT EXISTS sequence_counter (
    test_type VARCHAR(32),
    year INT,
    code_type VARCHAR(32) COMMENT 'sample_code or remote_sample_code',
    max_sequence_number INT,
    PRIMARY KEY (test_type, year, code_type)
);


INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'vl' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_vl
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);


INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'vl' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_vl
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);


INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'eid' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_eid
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);


INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'eid' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_eid
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);

INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'tb' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_tb
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);

INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'tb' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_tb
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);

INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'covid19' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_covid19
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);

INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'covid19' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_covid19
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);

INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'hepatitis' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_hepatitis
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);

INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'hepatitis' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_hepatitis
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);

INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT test_short_code, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_generic
INNER JOIN r_test_types ON r_test_types.test_type_id = form_generic.test_type
GROUP BY test_short_code, YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);


INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT test_short_code, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_generic
INNER JOIN r_test_types ON r_test_types.test_type_id = form_generic.test_type
GROUP BY test_short_code, YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL
ON DUPLICATE KEY UPDATE
max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number);


-- Jeyabanu 13-Dec-2023
ALTER TABLE `form_eid` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `result`;
ALTER TABLE `audit_form_eid` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `result`;

ALTER TABLE `form_covid19` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `revised_on`;
ALTER TABLE `audit_form_covid19` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `revised_on`;

ALTER TABLE `form_hepatitis` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `revised_on`;
ALTER TABLE `audit_form_hepatitis` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `revised_on`;

ALTER TABLE `form_tb` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `xpert_mtb_result`;
ALTER TABLE `audit_form_tb` ADD `result_modified` VARCHAR(3) NULL DEFAULT NULL AFTER `xpert_mtb_result`;

ALTER TABLE `form_eid` CHANGE `is_child_symptomatic` `is_child_symptomatic` VARCHAR(3) NULL DEFAULT NULL;
ALTER TABLE `audit_form_eid` CHANGE `is_child_symptomatic` `is_child_symptomatic` VARCHAR(3) NULL DEFAULT NULL;

ALTER TABLE `form_eid` ADD `is_result_mail_sent` VARCHAR(5) NULL DEFAULT 'no' AFTER `result_dispatched_datetime`;
ALTER TABLE `audit_form_eid` ADD `is_result_mail_sent` VARCHAR(5) NULL DEFAULT 'no' AFTER `result_dispatched_datetime`;

ALTER TABLE `form_tb` ADD `is_result_mail_sent` VARCHAR(5) NULL DEFAULT 'no' AFTER `result_dispatched_datetime`;
ALTER TABLE `audit_form_tb` ADD `is_result_mail_sent` VARCHAR(5) NULL DEFAULT 'no' AFTER `result_dispatched_datetime`;

-- Amit 20-Dec-2023
ALTER TABLE `form_eid` CHANGE `is_result_mail_sent` `is_result_mail_sent` VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `audit_form_eid` CHANGE `is_result_mail_sent` `is_result_mail_sent` VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `is_result_mail_sent` `is_result_mail_sent` VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `audit_form_tb` CHANGE `is_result_mail_sent` `is_result_mail_sent` VARCHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;


-- Amit 25-Dec-2023
UPDATE `privileges` SET `privilege_name` ='/common/reference/geographical-divisions-details.php' WHERE `privilege_name` LIKE 'geographical-divisions-details.php';
UPDATE `privileges` SET `privilege_name` ='/common/reference/add-geographical-divisions.php' WHERE `privilege_name` LIKE 'add-geographical-divisions.php';
UPDATE `privileges` SET `privilege_name` ='/common/reference/edit-geographical-divisions.php' WHERE `privilege_name` LIKE 'edit-geographical-divisions.php';
UPDATE `privileges` SET `privilege_name` ='/admin/monitoring/sync-status.php' WHERE `privilege_name` LIKE 'sync-status.php';
UPDATE `privileges` SET `privilege_name` ='/admin/monitoring/sync-history.php' WHERE `privilege_name` LIKE 'sync-history.php';
UPDATE `privileges` SET `privilege_name` ='/admin/monitoring/activity-log.php' WHERE `privilege_name` LIKE 'activity-log.php';
UPDATE `privileges` SET `privilege_name` ='/admin/monitoring/audit-trail.php' WHERE `privilege_name` LIKE 'audit-trail.php';
UPDATE `privileges` SET `privilege_name` ='/facilities/facilities.php' WHERE `privilege_name` LIKE 'facilities.php';
UPDATE `privileges` SET `privilege_name` ='/facilities/addFacility.php' WHERE `privilege_name` LIKE 'addFacility.php';
UPDATE `privileges` SET `privilege_name` ='/facilities/editFacility.php' WHERE `privilege_name` LIKE 'editFacility.php';
UPDATE `privileges` SET `privilege_name` ='/users/users.php' WHERE `privilege_name` LIKE 'users.php';
UPDATE `privileges` SET `privilege_name` ='/users/addUser.php' WHERE `privilege_name` LIKE 'addUser.php';
UPDATE `privileges` SET `privilege_name` ='/users/editUser.php' WHERE `privilege_name` LIKE 'editUser.php';
UPDATE `privileges` SET `privilege_name` ='/roles/roles.php' WHERE `privilege_name` LIKE 'roles.php';
UPDATE `privileges` SET `privilege_name` ='/roles/addRole.php' WHERE `privilege_name` LIKE 'addRole.php';
UPDATE `privileges` SET `privilege_name` ='/roles/editRole.php' WHERE `privilege_name` LIKE 'editRole.php';
UPDATE `privileges` SET `privilege_name` ='/global-config/editGlobalConfig.php' WHERE `privilege_name` LIKE 'editGlobalConfig.php';

DELETE FROM roles_privileges_map where privilege_id in (SELECT privilege_id FROM privileges WHERE privilege_name LIKE 'globalConfig.php');
DELETE FROM privileges WHERE privilege_name LIKE 'globalConfig.php';

DELETE FROM roles_privileges_map where privilege_id in (SELECT privilege_id FROM privileges WHERE privilege_name LIKE 'upload-facilities.php');
DELETE FROM privileges WHERE privilege_name LIKE 'upload-facilities.php';

DELETE FROM roles_privileges_map where privilege_id in (SELECT privilege_id FROM privileges WHERE privilege_name LIKE 'facilityMap.php');
DELETE FROM privileges WHERE privilege_name LIKE 'facilityMap.php';


-- Amit 26-Dec-2023
ALTER TABLE `user_details` CHANGE `user_id` `user_id` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

ALTER TABLE `form_vl`
CHANGE `reason_for_vl_testing` `reason_for_vl_testing` INT NULL DEFAULT NULL,
CHANGE `result_reviewed_by` `result_reviewed_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `result_approved_by` `result_approved_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `revised_by` `revised_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `tested_by` `tested_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `request_created_by` `request_created_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `sample_package_code` `sample_package_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `patient_gender` `patient_gender` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `patient_mobile_number` `patient_mobile_number` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `vl_result_category` `vl_result_category` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `is_patient_pregnant` `is_patient_pregnant` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `is_patient_breastfeeding` `is_patient_breastfeeding` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `patient_has_active_tb` `patient_has_active_tb` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

CREATE INDEX reason_for_vl_testing ON `form_vl` (`reason_for_vl_testing`);
CREATE INDEX sample_batch_id ON `form_vl` (`sample_batch_id`);
CREATE INDEX funding_source ON `form_vl` (`funding_source`);
CREATE INDEX implementing_partner ON `form_vl` (`implementing_partner`);
CREATE INDEX reason_for_sample_rejection ON `form_vl` (`reason_for_sample_rejection`);

ALTER TABLE `form_vl`
DROP `is_adherance_poor`,
DROP `number_of_enhanced_sessions`,
DROP `patient_tb`,
DROP `patient_tb_yes`,
DROP `patient_drugs_transmission`,
DROP `patient_receiving_therapy`,
DROP `patient_art_date`,
DROP `consultation`,
DROP `first_viral_load`,
DROP `sample_processed`,
DROP `collection_type`,
DROP `collection_site`,
DROP `requesting_vl_service_sector`,
DROP `requesting_category`,
DROP `requesting_professional_number`;


ALTER TABLE `audit_form_vl`
CHANGE `reason_for_vl_testing` `reason_for_vl_testing` INT NULL DEFAULT NULL,
CHANGE `result_reviewed_by` `result_reviewed_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `result_approved_by` `result_approved_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `revised_by` `revised_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `tested_by` `tested_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `request_created_by` `request_created_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `sample_package_code` `sample_package_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `patient_gender` `patient_gender` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `patient_mobile_number` `patient_mobile_number` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `vl_result_category` `vl_result_category` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `is_patient_pregnant` `is_patient_pregnant` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `is_patient_breastfeeding` `is_patient_breastfeeding` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `patient_has_active_tb` `patient_has_active_tb` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

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

ALTER TABLE `form_eid`
CHANGE `result_reviewed_by` `result_reviewed_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `result_approved_by` `result_approved_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `revised_by` `revised_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `tested_by` `tested_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `sample_package_code` `sample_package_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

ALTER TABLE `audit_form_eid`
CHANGE `result_reviewed_by` `result_reviewed_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `result_approved_by` `result_approved_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `revised_by` `revised_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `tested_by` `tested_by` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `sample_package_code` `sample_package_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;


ALTER TABLE `form_vl` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_vl` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_eid` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_eid` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_covid19` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_covid19` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_tb` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_tb` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_generic` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_generic` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_hepatitis` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_hepatitis` ADD `recommended_corrective_action` INT NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;


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
