-- Migration file for version 5.7.49
-- Created on 2026-09-02 18:50:19
--
-- Registers the Sample Flow page: where every sample registered in a period
-- is right now (at facility, in transit, at lab, awaiting approval, awaiting
-- release, released, or an exit) and how long it has been there, with a
-- breakdown by lab, facility, province, district or partner.
--
-- It is a program-level view, so it sits at the top of the menu next to the
-- Dashboard rather than under Admin, and each module's report section links
-- to it with the module preselected.
--
-- Every statement is written so re-running the migration cannot create duplicates.

INSERT IGNORE INTO `privileges` (`resource_id`, `privilege_name`, `display_name`, `show_mode`)
VALUES (
  'monitoring',
  '/sample-flow/sample-flow.php',
  'Sample Flow',
  'always'
);

-- Grant the page to every role that can already see either clinic report:
-- the same people who chase results not available are the ones who need to
-- see where the samples are.
INSERT INTO `roles_privileges_map` (`role_id`, `privilege_id`)
SELECT DISTINCT rp.`role_id`, np.`privilege_id`
  FROM `roles_privileges_map` rp
  JOIN `privileges` sp ON sp.`privilege_id` = rp.`privilege_id`
                      AND sp.`privilege_name` IN (
                            '/vl/program-management/highViralLoad.php',
                            '/eid/management/eid-clinic-report.php'
                          )
  JOIN `privileges` np ON np.`privilege_name` = '/sample-flow/sample-flow.php'
 WHERE NOT EXISTS (
     SELECT 1 FROM `roles_privileges_map` x
      WHERE x.`role_id` = rp.`role_id` AND x.`privilege_id` = np.`privilege_id`
 );

-- The Dashboard keeps the first slot; Sample Flow takes the one after it.
UPDATE `s_app_menu` SET `display_order` = 0
 WHERE `parent_id` = 0 AND `link` = '/dashboard/index.php';

INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
   `updated_datetime`)
VALUES
  ('dashboard', NULL, 'no', 'SAMPLE FLOW',
   '/sample-flow/sample-flow.php', NULL, 'always',
   'fa-solid fa-diagram-project', 'no', 'allMenu  sample-flow-menu', 0, 1, 'active',
   CURRENT_TIMESTAMP);

-- Module entries open the page with that module preselected. Parent rows are
-- looked up by link and module rather than by id, since ids differ per install.
INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
   `updated_datetime`)
SELECT 'vl', NULL, 'no', 'Sample Flow',
       '/sample-flow/sample-flow.php?t=vl', NULL, 'always',
       'fa-solid fa-diagram-project', 'no', 'allMenu  sample-flow-menu', parent.`id`, 110, 'active',
       CURRENT_TIMESTAMP
  FROM `s_app_menu` parent
 WHERE parent.`module` = 'vl' AND parent.`display_text` = 'Management' AND parent.`is_header` = 'no'
   AND NOT EXISTS (SELECT 1 FROM `s_app_menu` x WHERE x.`link` = '/sample-flow/sample-flow.php?t=vl')
 LIMIT 1;

INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
   `updated_datetime`)
SELECT 'eid', NULL, 'no', 'Sample Flow',
       '/sample-flow/sample-flow.php?t=eid', NULL, 'always',
       'fa-solid fa-diagram-project', 'no', 'allMenu  sample-flow-menu', parent.`id`, 125, 'active',
       CURRENT_TIMESTAMP
  FROM `s_app_menu` parent
 WHERE parent.`module` = 'eid' AND parent.`display_text` = 'Management' AND parent.`is_header` = 'no'
   AND NOT EXISTS (SELECT 1 FROM `s_app_menu` x WHERE x.`link` = '/sample-flow/sample-flow.php?t=eid')
 LIMIT 1;

UPDATE `system_config` SET `value` = '5.7.49' WHERE `system_config`.`name` = 'sc_version';
