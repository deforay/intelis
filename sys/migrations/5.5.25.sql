-- Migration file for version 5.5.25
--
-- Drop the Lab Command History sidebar entry (added in 5.4.4). The page stays
-- accessible through the button on the Lab Sync Status page; the privilege
-- rows are kept so that link keeps working.

DELETE FROM `s_app_menu` WHERE `link` = '/admin/monitoring/lis-command-history.php';

UPDATE `system_config` SET `value` = '5.5.25' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
