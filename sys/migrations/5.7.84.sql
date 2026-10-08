-- Migration file for version 5.7.84
-- Created on 2026-10-08 15:36:16


-- Reports become a group under ADMIN, after Monitoring, instead of a separate
-- REPORTS section of the sidebar (5.7.83). The row keeps its id and link and the
-- reports stay under it, so nothing else moves and no privilege changes. Once
-- there are enough reports to warrant it, the same row can go back to being a
-- top-level section.
UPDATE `s_app_menu`
   SET `is_header` = 'no',
       `display_text` = 'Reports',
       `icon` = 'fa-solid fa-chart-column',
       `additional_class_names` = 'reports-menu',
       `parent_id` = 2,
       `display_order` = 7,
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `link` = '#reports';

UPDATE `system_config` SET `value` = '5.7.84' WHERE `system_config`.`name` = 'sc_version';
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
