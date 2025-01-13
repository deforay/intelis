
-- Amit 25-Oct-2024
UPDATE `system_config` SET `value` = '5.3.0' WHERE `system_config`.`name` = 'sc_version';

--Amit 28-Oct-2024
UPDATE facility_details
SET sts_token = CONCAT('sts_', REPLACE(UUID(), '-', ''))
WHERE facility_type = 2 AND sts_token IS NULL;

-- Jeyabanu 30-Oct-2024
ALTER TABLE `package_details` ADD `manifest_change_history` JSON NULL DEFAULT NULL AFTER `number_of_samples`;
ALTER TABLE `package_details` ADD `manifest_print_history` JSON NULL DEFAULT NULL AFTER `manifest_change_history`;


-- Jeyabanu 15-Nov-2024
ALTER TABLE `form_eid` ADD `eid_number` VARCHAR(20) NULL DEFAULT NULL AFTER `lab_reception_person`;
ALTER TABLE `audit_form_eid` ADD `eid_number` VARCHAR(20) NULL DEFAULT NULL AFTER `lab_reception_person`;


-- Jeyabanu 20-Dec-2024
ALTER TABLE `form_cd4` ADD `is_patient_initiated_on_art` VARCHAR(10) NULL DEFAULT NULL AFTER `is_patient_new`;

ALTER TABLE `form_vl` ADD `last_vl_date_recency` DATE NULL DEFAULT NULL AFTER `last_vl_result_failure`, ADD `last_vl_result_recency` TEXT NULL DEFAULT NULL AFTER `last_vl_date_recency`;
ALTER TABLE `audit_form_vl` ADD `last_vl_date_recency` DATE NULL DEFAULT NULL AFTER `last_vl_result_failure`, ADD `last_vl_result_recency` TEXT NULL DEFAULT NULL AFTER `last_vl_date_recency`;


-- Jeyabanu 13-01-2025
ALTER TABLE `form_cd4` ADD `crag_test_results` VARCHAR(50) NULL DEFAULT NULL AFTER `cd4_result_percentage`;
