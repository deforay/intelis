-- Migration file for version 5.8.2
-- Created on 2026-10-22 11:03:55

-- The Sample Ageing Report belongs with each test's other reports, so it gets an
-- entry under every module's Management menu, opening with that test selected.
-- VL and EID have one since 5.7.49; this adds the rest at the end of each list.
-- A module can carry more than one Management row from older installs, so the
-- parent is the one holding the most entries.
INSERT INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
   `updated_datetime`)
SELECT t.`module`, NULL, 'no', 'Sample Ageing Report',
       CONCAT('/reports/sample-ageing.php?t=', t.`module`), NULL, 'always',
       'fa-solid fa-caret-right', 'no', 'allMenu  sample-ageing-menu', parent.`id`,
       (SELECT IFNULL(MAX(c.`display_order`), 0) + 1 FROM `s_app_menu` c WHERE c.`parent_id` = parent.`id`),
       'active', CURRENT_TIMESTAMP
  FROM (SELECT 'cd4' AS `module` UNION ALL SELECT 'covid19' UNION ALL SELECT 'hepatitis'
        UNION ALL SELECT 'tb' UNION ALL SELECT 'generic-tests') t
  JOIN `s_app_menu` parent ON parent.`id` = (
           SELECT p.`id` FROM `s_app_menu` p
            WHERE p.`module` = t.`module` AND p.`display_text` = 'Management' AND p.`is_header` = 'no'
            ORDER BY (SELECT COUNT(*) FROM `s_app_menu` k WHERE k.`parent_id` = p.`id`) DESC, p.`id`
            LIMIT 1)
 WHERE NOT EXISTS (SELECT 1 FROM `s_app_menu` x
                    WHERE x.`link` = CONCAT('/reports/sample-ageing.php?t=', t.`module`));

-- 5.7.49 granted the page only to roles that could see the VL or EID clinic
-- report. Roles that can see any module's clinic report get it now, so the new
-- entries show for the people who use that module. The page itself offers only
-- the tests the role covers.
INSERT INTO `roles_privileges_map` (`role_id`, `privilege_id`)
SELECT DISTINCT rp.`role_id`, np.`privilege_id`
  FROM `roles_privileges_map` rp
  JOIN `privileges` sp ON sp.`privilege_id` = rp.`privilege_id`
                      AND sp.`privilege_name` IN (
                            '/vl/program-management/vl-clinic-reports.php',
                            '/eid/management/eid-clinic-reports.php',
                            '/cd4/management/cd4-clinic-report.php',
                            '/covid-19/management/covid-19-clinic-report.php',
                            '/hepatitis/management/hepatitis-clinic-report.php',
                            '/tb/management/tb-clinic-report.php',
                            'generic-tests-clinic-report.php'
                          )
  JOIN `privileges` np ON np.`privilege_name` = '/reports/sample-ageing.php'
 WHERE NOT EXISTS (
     SELECT 1 FROM `roles_privileges_map` x
      WHERE x.`role_id` = rp.`role_id` AND x.`privilege_id` = np.`privilege_id`
 );

-- The VL and EID entries take the icon every other Management entry uses.
UPDATE `s_app_menu`
   SET `icon` = 'fa-solid fa-caret-right', `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `link` IN ('/reports/sample-ageing.php?t=vl', '/reports/sample-ageing.php?t=eid');

-- With an entry under every module, the one under Admin > Reports only repeats them.
UPDATE `s_app_menu`
   SET `status` = 'inactive', `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `link` = '/reports/sample-ageing.php';

UPDATE `system_config` SET `value` = '5.8.2' WHERE `system_config`.`name` = 'sc_version';

