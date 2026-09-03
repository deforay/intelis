-- Migration file for version 5.7.50
--
-- Takes the Sample Flow page off the menu for now. The page, its privilege and
-- the role grants from 5.7.49 stay in place, so it still opens by URL for a
-- walkthrough; only the three menu entries are switched off. Making it visible
-- again is the same statement with 'active'.
--
-- Every statement is written so re-running the migration cannot create duplicates.

UPDATE `s_app_menu` SET `status` = 'inactive', `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `link` LIKE '/sample-flow/sample-flow.php%';

UPDATE `system_config` SET `value` = '5.7.50' WHERE `system_config`.`name` = 'sc_version';
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
