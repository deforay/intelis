
-- Jeyabanu 10-08-2023

INSERT IGNORE INTO `s_app_menu` (`id`, `module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'admin', 'no', 'Recommended Corrective Actions', '/common/reference/recommended-corrective-actions.php?testType=eid', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu common-recommended-corrective-actions\r\n', '11', '40', 'active', CURRENT_TIMESTAMP), (NULL, 'admin', 'no', 'Recommended Corrective Actions', '/common/reference/recommended-corrective-actions.php?testType=eid', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu common-recommended-corrective-actions\r\n', '12', '41', 'active', CURRENT_TIMESTAMP);

UPDATE `privileges`
SET `shared_privileges` = '[\"implementation-partners.php\", \"add-implementation-partners.php\", \"edit-implementation-partners.php\", \"funding-sources.php\", \"add-funding-sources.php\", \"edit-funding-sources.php\"]'
WHERE `privilege_name` = 'geographical-divisions-details.php';

UPDATE `roles_privileges_map`
SET `privilege_id`= (SELECT privilege_id FROM privileges WHERE privilege_name = 'geographical-divisions-details.php')
WHERE `privilege_id`=(SELECT privilege_id FROM privileges WHERE privilege_name = 'province-details.php');

DELETE FROM `privileges` WHERE `privilege_name` = 'province-details.php';

ALTER TABLE `form_vl` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_vl` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;

ALTER TABLE `form_eid` ADD `external_sample_code` VARCHAR(100) NULL DEFAULT NULL AFTER `remote_sample_code`;
ALTER TABLE `audit_form_eid` ADD `external_sample_code` VARCHAR(100) NULL DEFAULT NULL AFTER `remote_sample_code`;

ALTER TABLE `form_eid` ADD `test_requested_on` DATE NULL DEFAULT NULL AFTER `result_printed_datetime`;
ALTER TABLE `audit_form_eid` ADD `test_requested_on` DATE NULL DEFAULT NULL AFTER `result_printed_datetime`;

ALTER TABLE `form_covid19` ADD `test_requested_on` DATE NULL DEFAULT NULL AFTER `investigator_email`;
ALTER TABLE `audit_form_covid19` ADD `test_requested_on` DATE NULL DEFAULT NULL AFTER `investigator_email`;

ALTER TABLE `form_covid19` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_covid19` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;

ALTER TABLE `form_hepatitis` ADD `test_requested_on` DATE NULL DEFAULT NULL AFTER `result_printed_datetime`;
ALTER TABLE `audit_form_hepatitis` ADD `test_requested_on` DATE NULL DEFAULT NULL AFTER `result_printed_datetime`;

ALTER TABLE `form_hepatitis` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_hepatitis` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;

ALTER TABLE `form_tb` ADD `test_requested_on` DATE NULL DEFAULT NULL AFTER `result_printed_datetime`;
ALTER TABLE `audit_form_tb` ADD `test_requested_on` DATE NULL DEFAULT NULL AFTER `result_printed_datetime`;

ALTER TABLE `form_tb` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_tb` ADD `result_sent_to_source_datetime` DATETIME NULL DEFAULT NULL AFTER `result_sent_to_source`;

ALTER TABLE `form_tb` ADD `external_sample_code` VARCHAR(100) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `audit_form_tb` ADD `external_sample_code` VARCHAR(100) NULL DEFAULT NULL AFTER `sample_code`;

ALTER TABLE `form_vl` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `audit_form_vl` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;

ALTER TABLE `form_covid19` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `audit_form_covid19` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;

ALTER TABLE `form_eid` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `audit_form_eid` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;

ALTER TABLE `form_hepatitis` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `audit_form_hepatitis` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;


-- Amit 24-Aug-2023
UPDATE `privileges`
SET `privilege_name` = '/batch/edit-batch.php?type=hepatitis'
WHERE `privilege_name` like '%/batch/edit-batch.php?type=hepatitis';


-- Amit 25-Aug-2023
UPDATE `r_sample_status` SET `status_name` = 'Expired' WHERE `r_sample_status`.`status_id` = 10;

INSERT IGNORE INTO `r_sample_status` (`status_id`, `status_name`, `status`) VALUES ('12', 'Cancelled', 'active');
ALTER TABLE `form_eid`
CHANGE `unique_id` `unique_id` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `vlsm_instance_id` `vlsm_instance_id` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `province_id` `province_id` INT NULL DEFAULT NULL,
CHANGE `remote_sample_code_format` `remote_sample_code_format` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `sample_code_format` `sample_code_format` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `sample_code` `sample_code` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `child_name` `child_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `child_surname` `child_surname` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `child_id` `child_id` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `is_sample_rejected` `is_sample_rejected` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `request_created_datetime` `request_created_datetime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
CHANGE `child_age` `child_age` INT NULL DEFAULT NULL,
CHANGE `app_sample_code` `app_sample_code` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `locked` `locked` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no',
CHANGE `manual_result_entry` `manual_result_entry` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
CHANGE `result_sent_to_source` `result_sent_to_source` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'pending';


-- Amit 26-Aug-2023 version 5.2.2
UPDATE `system_config` SET `value` = '5.2.2' WHERE `system_config`.`name` = 'sc_version';
