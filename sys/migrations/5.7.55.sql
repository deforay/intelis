-- Migration file for version 5.7.55
-- Created on 2026-09-03 13:45:28
--
-- Gives the reports a module of their own on the roles screen.
--
-- 5.7.54 added a 'reports' resource but put it on the 'common' module, so it
-- was collapsed inside COMMON rather than standing on its own. That screen
-- groups by module, not by resource, and prints the module name as the
-- heading, so the module is what has to change.
--
-- SystemService::getActiveModules() gains 'reports' in the same release.
-- addRole, editRole, roles and the menu all filter on that list, so a resource
-- whose module is not in it renders nowhere at all.
--
-- Access is untouched again here: this is the heading a privilege is listed
-- under, not who holds it.
--
-- Every statement is written so re-running the migration cannot create duplicates.

UPDATE `resources`
   SET `module` = 'reports',
       `display_name` = 'General Reports'
 WHERE `resource_id` = 'reports';

UPDATE `system_config` SET `value` = '5.7.55' WHERE `system_config`.`name` = 'sc_version';
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
