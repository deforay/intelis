-- Migration file for version 5.5.1
-- TB Cascade Report under TB -> Management
-- Adds:
--   1. New privileges for the report page and its AJAX endpoint
--   2. Grants the new privilege to the same roles that currently have access
--      to the TB Sample Status Report
--   3. Sidebar menu entry under TB -> Management


-- ----------------------------------------------------------------------------
-- 1. Privilege rows
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `privileges`
  (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES
  (NULL, 'tb-management', '/tb/management/tb-cascade-report.php',
    '["/tb/management/getTbCascadeReport.php"]',
    'TB Cascade Report', NULL, 'always');


-- ----------------------------------------------------------------------------
-- 2. Grant the new privilege to every role that currently has the TB Sample
--    Status Report (privilege_name = '/tb/management/tb-sample-status.php').
--    Mirrors the access model used by the existing TB management reports.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `roles_privileges_map` (`role_id`, `privilege_id`)
SELECT rpm.role_id, p_new.privilege_id
FROM `roles_privileges_map` rpm
JOIN `privileges` p_old
  ON p_old.privilege_id = rpm.privilege_id
 AND p_old.privilege_name = '/tb/management/tb-sample-status.php'
JOIN `privileges` p_new
  ON p_new.privilege_name = '/tb/management/tb-cascade-report.php';


-- ----------------------------------------------------------------------------
-- 3. Sidebar menu entry under the TB -> Management submenu.
--    Resolves the parent_id dynamically rather than hard-coding it.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`)
SELECT 'tb', NULL, 'no', 'TB Cascade Report',
       '/tb/management/tb-cascade-report.php', NULL, 'always',
       'fa-solid fa-caret-right', 'no', 'allMenu tbCascadeReport',
       mgmt.id,
       mgmt.next_order,
       'active', CURRENT_TIMESTAMP
FROM (
  SELECT m.id AS id,
         COALESCE((SELECT MAX(c.display_order) FROM s_app_menu c WHERE c.parent_id = m.id), 0) + 1 AS next_order
  FROM s_app_menu m
  WHERE m.display_text = 'Management' AND m.module = 'tb'
    AND m.has_children = 'yes'
  LIMIT 1
) AS mgmt;


-- ----------------------------------------------------------------------------
-- 4. Reconcile form_tb.is_result_finalized with form_tb.result.
--    Business rule: a sample with a final result text MUST have the
--    finalized flag set to 'yes'. Historical samples drifted because the
--    approval/sync code paths populated form_tb.result without flipping
--    the flag — surfaces as the "Accepted without result entered" hygiene
--    warning on the TB Cascade Report. This UPDATE backfills those rows.
-- ----------------------------------------------------------------------------
UPDATE `form_tb`
SET `is_result_finalized` = 'yes',
    `last_modified_datetime` = COALESCE(`last_modified_datetime`, CURRENT_TIMESTAMP)
WHERE `result` IS NOT NULL
  AND TRIM(`result`) <> ''
  AND (`is_result_finalized` IS NULL OR `is_result_finalized` <> 'yes');


UPDATE `system_config` SET `value` = '5.5.1' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
