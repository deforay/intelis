-- Migration file for version 5.8.5
-- Created on 2026-10-28 12:54:48


UPDATE `system_config` SET `value` = '5.8.5' WHERE `system_config`.`name` = 'sc_version';


-- The TB Testing Target Report had its data endpoint but no page. The page and its
-- Excel export now exist, so they get a privilege of their own.
INSERT IGNORE INTO `privileges`
  (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES
  (NULL, 'tb-management', '/tb/management/tb-testing-target-report.php',
    '["/tb/management/get-tb-monthly-threshold-report.php", "/tb/management/tb-testing-target-in-excel.php"]',
    'TB Testing Target Report', NULL, 'always');

-- Roles that can see the TB clinic report get the new page too, so it shows for the
-- people who already use TB's other management reports.
INSERT INTO `roles_privileges_map` (`role_id`, `privilege_id`)
SELECT DISTINCT rp.`role_id`, np.`privilege_id`
  FROM `roles_privileges_map` rp
  JOIN `privileges` sp ON sp.`privilege_id` = rp.`privilege_id`
                      AND sp.`privilege_name` = '/tb/management/tb-clinic-report.php'
  JOIN `privileges` np ON np.`privilege_name` = '/tb/management/tb-testing-target-report.php'
 WHERE NOT EXISTS (
     SELECT 1 FROM `roles_privileges_map` x
      WHERE x.`role_id` = rp.`role_id` AND x.`privilege_id` = np.`privilege_id`
 );

-- A menu entry at the end of TB > Management. A module can carry more than one
-- Management row from older installs, so the parent is the one holding the most entries.
INSERT INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
   `updated_datetime`)
SELECT 'tb', NULL, 'no', 'TB Testing Target Report',
       '/tb/management/tb-testing-target-report.php', NULL, 'always',
       'fa-solid fa-caret-right', 'no', 'allMenu tbMonthlyThresholdReport', parent.`id`,
       (SELECT IFNULL(MAX(c.`display_order`), 0) + 1 FROM `s_app_menu` c WHERE c.`parent_id` = parent.`id`),
       'active', CURRENT_TIMESTAMP
  FROM `s_app_menu` parent
 WHERE parent.`id` = (
           SELECT p.`id` FROM `s_app_menu` p
            WHERE p.`module` = 'tb' AND p.`display_text` = 'Management' AND p.`is_header` = 'no'
            ORDER BY (SELECT COUNT(*) FROM `s_app_menu` k WHERE k.`parent_id` = p.`id`) DESC, p.`id`
            LIMIT 1)
   AND NOT EXISTS (SELECT 1 FROM `s_app_menu` x
                    WHERE x.`link` = '/tb/management/tb-testing-target-report.php');
