-- Migration file for version 5.3.2
-- Created on 2025-09-15 14:54:06


UPDATE `system_config` SET `value` = '5.3.2' WHERE `system_config`.`name` = 'sc_version';

-- Amit 22-Sep-2025

INSERT INTO `global_config`
(`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_datetime`, `updated_by`, `status`)
VALUES
('Auto Approve Interface Results', 'auto_approve_interface_results', 'no', 'interfacing', 'yes', CURRENT_TIMESTAMP, NULL, 'active');


-- Amit 25-Sep-2025
ALTER TABLE `hold_sample_import` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_vl_lab_datetime` DATETIME NULL DEFAULT NULL, CHANGE `sample_tested_datetime` `sample_tested_datetime` DATETIME NULL DEFAULT NULL, CHANGE `result_dispatched_datetime` `result_dispatched_datetime` DATETIME NULL DEFAULT NULL, CHANGE `result_reviewed_datetime` `result_reviewed_datetime` DATETIME NULL DEFAULT NULL;


ALTER TABLE `form_vl` DROP `sample_registered_at_lab`;
ALTER TABLE `form_eid` DROP `sample_registered_at_lab`;
ALTER TABLE `form_covid19` DROP `sample_registered_at_lab`;
ALTER TABLE `form_tb` DROP `sample_registered_at_lab`;
ALTER TABLE `form_hepatitis` DROP `sample_registered_at_lab`;
ALTER TABLE `form_cd4` DROP `sample_registered_at_lab`;
ALTER TABLE `form_generic` DROP `sample_registered_at_lab`;


ALTER TABLE `audit_form_vl` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_eid` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_covid19` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_tb` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_hepatitis` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_cd4` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_generic` DROP `sample_registered_at_lab`;

-- Amit 28-Sep-2025
RENAME TABLE `package_details` TO `specimen_manifests`;

-- Thana 19-Sep-2025
INSERT INTO `global_config` (`display_name`, `name`, `value`, `instance_id`, `category`, `remote_sync_needed`, `updated_datetime`, `updated_by`, `status`) 
VALUES ('Result PDF Report Format', 'report_format', null, null, 'general', 'yes', '2025-09-19 17:58:53', null, 'active');

-- Thana 29-Sep-2025
ALTER TABLE `form_tb` 
ADD `referred_by_lab_id` INT NULL DEFAULT NULL AFTER `lab_id`, 
ADD `referred_to_lab_id` INT NULL DEFAULT NULL AFTER `referred_by_lab_id`, 
ADD `reason_for_referral` VARCHAR(128) NULL DEFAULT NULL AFTER `referred_to_lab_id`, 
ADD `referred_on_datetime` DATETIME NULL DEFAULT NULL AFTER `reason_for_referral`, 
ADD `referred_by` VARCHAR(128) NULL DEFAULT NULL AFTER `referred_on_datetime`;

ALTER TABLE `audit_form_tb` 
ADD `referred_by_lab_id` INT NULL DEFAULT NULL AFTER `lab_id`, 
ADD `referred_to_lab_id` INT NULL DEFAULT NULL AFTER `referred_by_lab_id`, 
ADD `reason_for_referral` VARCHAR(128) NULL DEFAULT NULL AFTER `referred_to_lab_id`, 
ADD `referred_on_datetime` DATETIME NULL DEFAULT NULL AFTER `reason_for_referral`, 
ADD `referred_by` VARCHAR(128) NULL DEFAULT NULL AFTER `referred_on_datetime`;

-- Thana 02-Oct-2025
ALTER TABLE `audit_form_vl` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_eid` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_covid19` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_tb` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_hepatitis` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_cd4` DROP `sample_registered_at_lab`;
ALTER TABLE `audit_form_generic` DROP `sample_registered_at_lab`;

-- Thana 06-Oct-2025
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES 
(NULL, 'tb-results', '/tb/results/tb-referral-list.php', NULL, 'TB Referral Lab', '2', 'always'),
(NULL, 'tb-results', '/tb/results/add-tb-referral.php', NULL, 'Add TB Referral Lab', '2', 'always');
INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'tb', NULL, 'no', 'TB Referral Lab', '/tb/results/tb-referral-list.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbFailedResultsMenu', '82', '164', 'active', CURRENT_TIMESTAMP);

-- Thana 10-Oct-2025
CREATE TABLE `tb_referral_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `tb_id` int DEFAULT NULL,
  `from_lab_id` int NOT NULL,
  `to_lab_id` int NOT NULL,
  `reason_for_referral` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `referred_on_datetime` datetime NOT NULL,
  `referred_by` varchar(128) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Amit 14-Oct-2025
UPDATE `s_app_menu`
SET `additional_class_names` = TRIM(REPLACE(`additional_class_names`, 'treeview', ''))
WHERE `additional_class_names` LIKE '%treeview%';


-- Amit 16-Oct-2025
ALTER TABLE specimen_manifests CHANGE COLUMN package_id manifest_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE specimen_manifests CHANGE COLUMN package_code manifest_code VARCHAR(255);
ALTER TABLE specimen_manifests CHANGE COLUMN package_status manifest_status VARCHAR(255);

ALTER TABLE specimen_manifests
  ADD COLUMN manifest_type ENUM('collection','referral') DEFAULT 'collection' AFTER module;

UPDATE specimen_manifests SET manifest_type = 'collection' WHERE manifest_type IS NULL;

-- Thana 17-Oct-2025
UPDATE `privileges` SET `display_name` = 'Add TB Referral Manifest' WHERE `privileges`.`privilege_name` = '/tb/results/add-tb-referral.php'; 
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'tb-results', '/tb/results/edit-tb-referral.php', NULL, 'Edit TB Referral Manifest', '2', 'always');
UPDATE `s_app_menu` SET `inner_pages` = '/tb/results/add-tb-referral.php,/tb/results/edit-tb-referral.php' WHERE `s_app_menu`.`link` = '/tb/results/tb-referral-list.php'; 