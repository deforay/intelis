-- Migration file for version 5.7.53
-- Created on 2026-09-03 13:37:28
--
-- Moves three monitoring reports out of /admin/monitoring/ and into /reports/,
-- alongside the Sample Ageing Report from 5.7.52:
--
--   Lab Performance Indicators   (registered by 5.5.26)
--   Interface Machine Activity   (registered by 5.5.24)
--   Sample Referral Network      (registered by 5.5.2)
--
-- Each privilege row is updated in place rather than replaced, so the
-- privilege_id never changes and every role grant made by those earlier
-- migrations keeps working.
--
-- Only the menu link is rewritten. The parent, display order, label, icon and
-- class names are all left alone, so each report stays exactly where it is in
-- the menu; this migration is about the path, not about where they appear.
--
-- Every statement is written so re-running the migration cannot create duplicates.

UPDATE `privileges`
   SET `privilege_name` = REPLACE(`privilege_name`, '/admin/monitoring/', '/reports/')
 WHERE `privilege_name` IN (
           '/admin/monitoring/lab-performance-indicators.php',
           '/admin/monitoring/interface-machine-activity.php',
           '/admin/monitoring/sample-referral-network.php'
       );

UPDATE `s_app_menu`
   SET `link` = REPLACE(`link`, '/admin/monitoring/', '/reports/'),
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `link` IN (
           '/admin/monitoring/lab-performance-indicators.php',
           '/admin/monitoring/interface-machine-activity.php',
           '/admin/monitoring/sample-referral-network.php'
       );

UPDATE `system_config` SET `value` = '5.7.53' WHERE `system_config`.`name` = 'sc_version';
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
