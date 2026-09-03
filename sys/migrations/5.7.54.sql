-- Migration file for version 5.7.54
-- Created on 2026-09-03 13:41:00
--
-- Gives the reports that moved into /reports/ a resource of their own on the
-- roles screen, instead of leaving them filed under Monitoring.
--
-- The module is 'common' rather than 'admin' because these are not
-- administrator tools: the Sample Ageing Report is granted to any role that
-- can already see a clinic report, and the rest are read-only reports.
--
-- Only the grouping changes. privilege_id, privilege_name and every row in
-- roles_privileges_map are untouched, so nobody gains or loses access here.
--
-- Every statement is written so re-running the migration cannot create duplicates.

INSERT INTO `resources` (`resource_id`, `module`, `display_name`)
VALUES ('reports', 'common', 'Reports')
ON DUPLICATE KEY UPDATE
    `module` = VALUES(`module`),
    `display_name` = VALUES(`display_name`);

-- The paths are the ones 5.7.52 and 5.7.53 rewrote.
UPDATE `privileges`
   SET `resource_id` = 'reports'
 WHERE `privilege_name` IN (
           '/reports/sample-ageing.php',
           '/reports/lab-performance-indicators.php',
           '/reports/interface-machine-activity.php',
           '/reports/sample-referral-network.php'
       );

UPDATE `system_config` SET `value` = '5.7.54' WHERE `system_config`.`name` = 'sc_version';
