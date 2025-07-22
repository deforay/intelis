-- Migration file for version 5.3.1
-- Created on 2025-03-21 12:41:17

-- Amit 21-Mar-2025
UPDATE `system_config` SET `value` = '5.3.1' WHERE `system_config`.`name` = 'sc_version';

-- Amit 10-Apr-2025
UPDATE `s_app_menu` SET `show_mode` = 'sts' WHERE link like '/admin/monitoring/sync-status.php';

-- Amit 24-Apr-2025
ALTER TABLE `form_vl` CHANGE `recency_vl` `recency_vl` VARCHAR(10) CHARACTER SET utf8mb4 NULL DEFAULT 'no';
ALTER TABLE `audit_form_vl` CHANGE `recency_vl` `recency_vl` VARCHAR(10) CHARACTER SET utf8mb4 NULL DEFAULT 'no';

-- Amit 28-Apr-2025
INSERT IGNORE INTO roles_privileges_map (role_id, privilege_id)
SELECT 1, privilege_id FROM privileges;

-- Amit 06-May-2025
UPDATE `privileges` SET `privilege_name`= '/vl/results/vl-print-results.php' WHERE `privilege_name` LIKE '/vl/results/vlPrintResult.php';
UPDATE `s_app_menu` SET `link`= '/vl/results/vl-print-results.php' WHERE `link` LIKE '/vl/results/vlPrintResult.php';

-- Amit 21-May-2025
UPDATE `s_app_menu` set icon = "fa-solid fa-table" WHERE `link` = '/admin/monitoring/test-results-metadata.php';
UPDATE `s_app_menu` set icon = "fa-solid fa-file-lines" WHERE `link` = '/admin/monitoring/log-files.php';
UPDATE `s_app_menu` set icon = "fas fa-user-clock" WHERE `link` = '/admin/monitoring/activity-log.php';

-- UPDATE `user_details` set login_id = null where status = 'inactive';
-- UPDATE `user_details` set `status`='inactive', `login_id` = null where hash_algorithm = 'sha1';
-- ALTER TABLE `user_details` ADD UNIQUE(`login_id`);
UPDATE user_details SET user_id = UUID() WHERE user_id IS NULL OR TRIM(user_id) = '';

-- Amit 29-May-2025
ALTER TABLE `r_vl_art_regimen` DROP `nation_identifier`;

-- Amit 31-May-2025
CREATE INDEX idx_vl_result_category_status ON form_vl (vl_result_category, result_status);

-- Amit 10-Jun-2025
ALTER TABLE `user_details` DROP INDEX `interface_user_name`;
ALTER TABLE `user_details` CHANGE `interface_user_name` `interface_user_name` JSON NULL DEFAULT NULL;

-- Amit 11-Jun-2025
ALTER TABLE `temp_sample_import` CHANGE `import_machine_name` `import_machine_name` VARCHAR(128) NULL DEFAULT NULL;

-- Amit 17-Jun-2025
ALTER TABLE `hold_sample_import` ADD `instrument_id` VARCHAR(128) NULL DEFAULT NULL AFTER `vl_test_platform`;
-- Thana 02-Jul-2025
ALTER TABLE `form_eid` ADD `specific_infant_treatment` VARCHAR(128) NULL DEFAULT NULL AFTER `is_infant_receiving_treatment`;
ALTER TABLE `audit_form_eid` ADD `specific_infant_treatment` VARCHAR(128) NULL DEFAULT NULL AFTER `is_infant_receiving_treatment`;
ALTER TABLE `form_eid` ADD `child_age_in_days` INT NULL DEFAULT NULL AFTER `child_treatment_initiation_date`, ADD `test_request_date` DATE NULL DEFAULT NULL AFTER `child_age_in_days`, ADD `infant_email` VARCHAR(256) NULL DEFAULT NULL AFTER `test_request_date`, ADD `infant_phone` INT NULL DEFAULT NULL AFTER `infant_email`;
ALTER TABLE `audit_form_eid` ADD `child_age_in_days` INT NULL DEFAULT NULL AFTER `child_treatment_initiation_date`, ADD `test_request_date` DATE NULL DEFAULT NULL AFTER `child_age_in_days`, ADD `infant_email` VARCHAR(256) NULL DEFAULT NULL AFTER `test_request_date`, ADD `infant_phone` INT NULL DEFAULT NULL AFTER `infant_email`;

-- Thana 17-Jul-2025
ALTER TABLE `form_vl` ADD `test_request_date` VARCHAR(128) NULL DEFAULT NULL AFTER `test_requested_on`;
ALTER TABLE `audit_form_vl` ADD `test_request_date` VARCHAR(128) NULL DEFAULT NULL AFTER `test_requested_on`;
