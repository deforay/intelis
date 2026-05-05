-- Migration file for version 5.4.6
-- PMTCT Cascade Report under EID -> Management
-- Adds:
--   1. New privileges for the report page, AJAX endpoint and export endpoint
--   2. Grants the new privilege to the same roles that currently have access
--      to the EID Sample Status Report (privilege_id 88)
--   3. Sidebar menu entry under EID -> Management


-- ----------------------------------------------------------------------------
-- 1. Privilege rows
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `privileges`
  (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES
  (NULL, 'eid-management', '/eid/management/pmtct-cascade-report.php',
    '["/eid/management/getPmtctCascadeReport.php", "/eid/management/pmtctCascadeReportExport.php"]',
    'PMTCT Cascade Report', NULL, 'always');


-- ----------------------------------------------------------------------------
-- 2. Grant the new privilege to every role that currently has the EID
--    Sample Status Report (privilege_name = '/eid/management/eid-sample-status.php').
--    Mirrors the access model used by the existing EID management reports.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `roles_privileges_map` (`role_id`, `privilege_id`)
SELECT rpm.role_id, p_new.privilege_id
FROM `roles_privileges_map` rpm
JOIN `privileges` p_old
  ON p_old.privilege_id = rpm.privilege_id
 AND p_old.privilege_name = '/eid/management/eid-sample-status.php'
JOIN `privileges` p_new
  ON p_new.privilege_name = '/eid/management/pmtct-cascade-report.php';


-- ----------------------------------------------------------------------------
-- 3. Sidebar menu entry under the EID -> Management submenu.
--    Resolves the parent_id dynamically rather than hard-coding it.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`)
SELECT 'eid', NULL, 'no', 'PMTCT Cascade Report',
       '/eid/management/pmtct-cascade-report.php', NULL, 'always',
       'fa-solid fa-caret-right', 'no', 'allMenu eidPmtctCascadeReport',
       mgmt.id,
       mgmt.next_order,
       'active', CURRENT_TIMESTAMP
FROM (
  SELECT m.id AS id,
         COALESCE((SELECT MAX(c.display_order) FROM s_app_menu c WHERE c.parent_id = m.id), 0) + 1 AS next_order
  FROM s_app_menu m
  WHERE m.display_text = 'Management' AND m.module = 'eid'
    AND m.has_children = 'yes'
  LIMIT 1
) AS mgmt;


UPDATE `system_config` SET `value` = '5.4.6' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
