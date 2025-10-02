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
