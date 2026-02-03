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

