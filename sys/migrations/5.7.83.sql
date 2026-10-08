-- Migration file for version 5.7.83
-- Created on 2026-10-08

-- Simpler admin menu. With every test module on, ADMIN had grown to 11 groups and
-- about 64 links, 40 of them the same handful of reference pages repeated per test.
--
--   ADMIN    Users & Roles, Facilities, Settings, Reference Lists, Test Settings, Monitoring
--   REPORTS  Lab Performance Indicators, Instrument Activity, Sample Referral Network
--            (and Sample Ageing Report, still hidden since 5.7.50)
--
-- Only menu rows move. Every link stays as it is, so no privilege row or role
-- grant changes and nobody gains or loses access to a page.
--
-- The per-test config groups are kept, re-parented under the new Test Settings
-- entry. That entry has has_children = 'hub': the sidebar shows it as a single
-- link, and /admin/test-settings.php lists the groups below it as tabs, each
-- filtered by active module and by the user's role exactly as the sidebar was.

-- ADMIN moves up one place; REPORTS takes the slot after it.
UPDATE `s_app_menu` SET `display_order` = 1, `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `id` = 2 AND `module` = 'admin' AND `parent_id` = 0;

UPDATE `s_app_menu` SET `display_text` = 'Users & Roles', `display_order` = 1, `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `id` = 3 AND `module` = 'admin';

UPDATE `s_app_menu` SET `display_order` = 2, `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `id` = 6 AND `module` = 'admin';

UPDATE `s_app_menu` SET `display_text` = 'Settings', `display_order` = 3, `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `id` = 8 AND `module` = 'admin';

UPDATE `s_app_menu` SET `display_order` = 6, `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `id` = 7 AND `module` = 'admin';

-- Settings keeps General Configuration, Instruments and Lab Storage.
UPDATE `s_app_menu`
   SET `display_order` = CASE `link`
           WHEN '/global-config/editGlobalConfig.php' THEN 1
           WHEN '/instruments/instruments.php' THEN 2
           WHEN '/common/reference/lab-storage.php' THEN 3
       END,
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `parent_id` = 8
   AND `link` IN ('/global-config/editGlobalConfig.php', '/instruments/instruments.php',
                  '/common/reference/lab-storage.php');

-- Reference Lists: the shared lists that are data, not settings.
INSERT INTO `s_app_menu`
(`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
`icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
`updated_datetime`)
SELECT NULL, 'admin', NULL, 'no', 'Reference Lists', '#reference-lists', NULL,
'always', 'fa-solid fa-list-ul', 'yes', 'reference-lists-menu', 2, 4, 'active', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `s_app_menu` WHERE `link` = '#reference-lists');

UPDATE `s_app_menu`
   SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `s_app_menu` WHERE `link` = '#reference-lists' LIMIT 1) AS `r`),
       `display_order` = CASE `link`
           WHEN '/common/reference/geographical-divisions-details.php' THEN 1
           WHEN '/common/reference/implementation-partners.php' THEN 2
           WHEN '/common/reference/funding-sources.php' THEN 3
       END,
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `module` = 'admin'
   AND `link` IN ('/common/reference/geographical-divisions-details.php',
                  '/common/reference/implementation-partners.php',
                  '/common/reference/funding-sources.php');

-- Test Settings: one sidebar link in place of seven config groups.
INSERT INTO `s_app_menu`
(`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
`icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
`updated_datetime`)
SELECT NULL, 'admin', NULL, 'no', 'Test Settings', '/admin/test-settings.php', NULL,
'always', 'fa-solid fa-sliders', 'hub', 'allMenu test-settings-menu', 2, 5, 'active', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `s_app_menu` WHERE `link` = '/admin/test-settings.php');

-- Earlier versions of fix-app-menu.php left up to three "CD4 Config" groups, and
-- which one holds the pages differs by install: a fresh install from init.sql
-- has them under a group with no link and an empty #cd4-config group beside it;
-- older upgrades have them under #cd4-config and empty groups beside it.
-- fix-app-menu.php finds the group by #cd4-config, so that one is kept: give
-- the link to the oldest group if none has it, move every CD4 page under it,
-- then drop the groups left empty.
UPDATE `s_app_menu`
   SET `link` = '#cd4-config', `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `id` = (SELECT `id` FROM (SELECT MIN(`id`) AS `id` FROM `s_app_menu`
                                  WHERE `module` = 'admin' AND `display_text` = 'CD4 Config'
                                    AND `parent_id` = 2) AS `oldest`)
   AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `s_app_menu` WHERE `link` = '#cd4-config' LIMIT 1) AS `kept`);

UPDATE `s_app_menu`
   SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `s_app_menu` WHERE `link` = '#cd4-config' LIMIT 1) AS `kept`),
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `parent_id` IN (SELECT `id` FROM (SELECT `id` FROM `s_app_menu`
                                          WHERE `module` = 'admin' AND `display_text` = 'CD4 Config'
                                            AND (`link` IS NULL OR `link` IN ('', 'cd4-config'))) AS `stale`);

DELETE `m` FROM `s_app_menu` `m`
  LEFT JOIN `s_app_menu` `c` ON `c`.`parent_id` = `m`.`id`
 WHERE `m`.`module` = 'admin'
   AND `m`.`display_text` = 'CD4 Config'
   AND (`m`.`link` IS NULL OR `m`.`link` IN ('', 'cd4-config'))
   AND `c`.`id` IS NULL;

-- The groups become the tabs of the Test Settings page, so they are named for
-- the test alone.
UPDATE `s_app_menu`
   SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `s_app_menu` WHERE `link` = '/admin/test-settings.php' LIMIT 1) AS `t`),
       `display_text` = CASE
           WHEN `id` = 10 THEN 'HIV Viral Load'
           WHEN `id` = 11 THEN 'EID'
           WHEN `id` = 12 THEN 'COVID-19'
           WHEN `id` = 13 THEN 'Hepatitis'
           WHEN `id` = 14 THEN 'TB'
           WHEN `id` = 9 THEN 'Custom Tests'
           ELSE 'CD4'
       END,
       `display_order` = CASE
           WHEN `id` = 10 THEN 1
           WHEN `id` = 11 THEN 2
           WHEN `id` = 12 THEN 3
           WHEN `id` = 13 THEN 4
           WHEN `id` = 14 THEN 5
           WHEN `id` = 9 THEN 7
           ELSE 6
       END,
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `module` = 'admin'
   AND `parent_id` = 2
   AND (`id` IN (9, 10, 11, 12, 13, 14) OR `link` = '#cd4-config');

-- Same page, same name on every tab.
UPDATE `s_app_menu`
   SET `display_text` = CASE `display_text`
           WHEN 'Sample Type' THEN 'Sample Types'
           WHEN 'Co-morbidities' THEN 'Comorbidities'
           WHEN 'Sample Rejection Reasons' THEN 'Rejection Reasons'
           WHEN 'Testing Reasons' THEN 'Test Reasons'
       END,
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `module` = 'admin'
   AND `display_text` IN ('Sample Type', 'Co-morbidities', 'Sample Rejection Reasons', 'Testing Reasons')
   AND `parent_id` IN (SELECT `id` FROM (SELECT `id` FROM `s_app_menu` WHERE `parent_id` IN
           (SELECT `id` FROM `s_app_menu` WHERE `link` = '/admin/test-settings.php')) AS `g`);

-- ...and in the same order on every tab, test-specific lists last.
UPDATE `s_app_menu`
   SET `display_order` = CASE `display_text`
           WHEN 'Test Type Configuration' THEN 1
           WHEN 'Sample Types' THEN 2
           WHEN 'Test Reasons' THEN 3
           WHEN 'Results' THEN 4
           WHEN 'Rejection Reasons' THEN 5
           WHEN 'Test Failure Reasons' THEN 6
           WHEN 'Recommended Corrective Actions' THEN 7
           ELSE 100 + `id`
       END,
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `module` = 'admin'
   AND `parent_id` IN (SELECT `id` FROM (SELECT `id` FROM `s_app_menu` WHERE `parent_id` IN
           (SELECT `id` FROM `s_app_menu` WHERE `link` = '/admin/test-settings.php')) AS `g`);

-- Monitoring: who did what, then sync and API, then the system itself. The
-- three reports that sat here move to REPORTS below.
UPDATE `s_app_menu`
   SET `display_order` = CASE `link`
           WHEN '/admin/monitoring/activity-log.php' THEN 1
           WHEN '/admin/monitoring/audit-trail.php' THEN 2
           WHEN '/admin/monitoring/page-usage.php' THEN 3
           WHEN '/admin/monitoring/api-sync-history.php' THEN 4
           WHEN '/admin/api-dashboard/api-dashboard.php' THEN 5
           WHEN '/admin/monitoring/sync-status.php' THEN 6
           WHEN '/admin/monitoring/sources-of-requests.php' THEN 7
           WHEN '/admin/monitoring/test-results-metadata.php' THEN 8
           WHEN '/admin/monitoring/log-files.php' THEN 9
           ELSE `display_order`
       END,
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `parent_id` = 7;

-- REPORTS: a top-level section for the reports in app/reports/ that are not
-- tied to one test.
INSERT INTO `s_app_menu`
(`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
`icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
`updated_datetime`)
SELECT NULL, 'reports', NULL, 'yes', 'REPORTS', '#reports', NULL,
'always', NULL, 'yes', 'header', 0, 2, 'active', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `s_app_menu` WHERE `link` = '#reports');

-- Each report keeps its module, so the cloud-LIS admin whitelist in
-- AppMenuService still treats the three admin ones as before. Each also keeps
-- its status: 5.7.50 hid the Sample Ageing Report menu entries on purpose, and
-- this only files it under REPORTS for the day it is switched back on.
UPDATE `s_app_menu`
   SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `s_app_menu` WHERE `link` = '#reports' LIMIT 1) AS `h`),
       `display_text` = CASE `link`
           WHEN '/reports/sample-ageing.php' THEN 'Sample Ageing Report'
           ELSE `display_text`
       END,
       `display_order` = CASE `link`
           WHEN '/reports/sample-ageing.php' THEN 1
           WHEN '/reports/lab-performance-indicators.php' THEN 2
           WHEN '/reports/interface-machine-activity.php' THEN 3
           WHEN '/reports/sample-referral-network.php' THEN 4
       END,
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE (`link` = '/reports/sample-ageing.php' AND `parent_id` = 0)
    OR (`parent_id` = 7 AND `link` IN ('/reports/lab-performance-indicators.php',
                                       '/reports/interface-machine-activity.php',
                                       '/reports/sample-referral-network.php'));

UPDATE `system_config` SET `value` = '5.7.83' WHERE `system_config`.`name` = 'sc_version';
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
