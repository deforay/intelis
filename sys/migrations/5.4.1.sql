-- Migration file for version 5.4.1
-- Created on 2026-01-28 19:31:48

-- Fix tb-reference resource to show under TB tab instead of Admin
UPDATE `resources` SET `module` = 'tb' WHERE `resource_id` = 'tb-reference';

-- Add missing TB reference privilege
INSERT INTO `privileges` (`resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES (
    'tb-reference',
    '/tb/reference/tb-sample-type.php',
    '["/tb/reference/tb-sample-rejection-reasons.php", "/tb/reference/add-tb-sample-rejection-reason.php", "/tb/reference/add-tb-sample-type.php", "/tb/reference/tb-test-reasons.php", "/tb/reference/add-tb-test-reasons.php", "/tb/reference/tb-results.php", "/tb/reference/add-tb-results.php"]',
    'Manage TB Reference Tables',
    NULL,
    'always'
) ON DUPLICATE KEY UPDATE shared_privileges = VALUES(shared_privileges);

UPDATE `system_config` SET `value` = '5.4.1' WHERE `system_config`.`name` = 'sc_version';
-- Thana 05-Feb-2026
ALTER TABLE `form_tb` CHANGE `is_specimen_reordered` `is_specimen_reordered` ENUM('yes','no','') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `audit_form_tb` ADD `is_result_finalized` ENUM('no','yes','') NOT NULL DEFAULT 'no' AFTER `locked`;
ALTER TABLE `form_tb` ADD `is_result_finalized` ENUM('no','yes','') NOT NULL DEFAULT 'no' AFTER `locked`;