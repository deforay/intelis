-- Migration file for version 5.8.9
-- Created on 2026-11-19 16:54:15


UPDATE `system_config` SET `value` = '5.8.9' WHERE `system_config`.`name` = 'sc_version';


-- The CD4 Testing Target Report page has existed without a privilege or a menu
-- entry, so nobody could open it. The dashboard's CD4 target alert pointed at the
-- hepatitis report instead. The page gets a privilege and a menu entry, as the TB
-- report did in 5.8.5. Its data endpoint and Excel export are shared with it in
-- bin/setup/update-privileges.php, which owns every shared_privileges value.
INSERT IGNORE INTO `privileges`
  (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES
  (NULL, 'cd4-management', '/cd4/management/cd4-testing-target-report.php', NULL,
    'CD4 Testing Target Report', NULL, 'always');

-- Roles that can see the CD4 clinic report get the new page too, so it shows for
-- the people who already use CD4's other management reports.
INSERT INTO `roles_privileges_map` (`role_id`, `privilege_id`)
SELECT DISTINCT rp.`role_id`, np.`privilege_id`
  FROM `roles_privileges_map` rp
  JOIN `privileges` sp ON sp.`privilege_id` = rp.`privilege_id`
                      AND sp.`privilege_name` = '/cd4/management/cd4-clinic-report.php'
  JOIN `privileges` np ON np.`privilege_name` = '/cd4/management/cd4-testing-target-report.php'
 WHERE NOT EXISTS (
     SELECT 1 FROM `roles_privileges_map` x
      WHERE x.`role_id` = rp.`role_id` AND x.`privilege_id` = np.`privilege_id`
 );

-- A menu entry at the end of CD4 > Management. A module can carry more than one
-- Management row from older installs, so the parent is the one holding the most entries.
INSERT INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
   `updated_datetime`)
SELECT 'cd4', NULL, 'no', 'CD4 Testing Target Report',
       '/cd4/management/cd4-testing-target-report.php', NULL, 'always',
       'fa-solid fa-caret-right', 'no', 'allMenu cd4MonthlyThresholdReport', parent.`id`,
       (SELECT IFNULL(MAX(c.`display_order`), 0) + 1 FROM `s_app_menu` c WHERE c.`parent_id` = parent.`id`),
       'active', CURRENT_TIMESTAMP
  FROM `s_app_menu` parent
 WHERE parent.`id` = (
           SELECT p.`id` FROM `s_app_menu` p
            WHERE p.`module` = 'cd4' AND p.`display_text` = 'Management' AND p.`is_header` = 'no'
            ORDER BY (SELECT COUNT(*) FROM `s_app_menu` k WHERE k.`parent_id` = p.`id`) DESC, p.`id`
            LIMIT 1)
   AND NOT EXISTS (SELECT 1 FROM `s_app_menu` x
                    WHERE x.`link` = '/cd4/management/cd4-testing-target-report.php');
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
