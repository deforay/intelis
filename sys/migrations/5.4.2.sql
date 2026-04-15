-- Migration file for version 5.4.2
-- Created on 2026-03-16 11:13:44


UPDATE `system_config` SET `value` = '5.4.2' WHERE `system_config`.`name` = 'sc_version';


-- Amit 15-Apr-2026
INSERT IGNORE INTO `resources` (`resource_id`, `module`, `display_name`)
VALUES ('monitoring', 'admin', 'Monitoring');

INSERT IGNORE INTO `privileges`
(`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES
(NULL, 'monitoring', '/admin/api-dashboard/api-dashboard.php', NULL, 'API Dashboard', '1', 'always');

-- Add API Dashboard under the Monitoring sidebar group (STS-only)
-- parent_id is looked up from the Monitoring menu row; display_order = next available within that parent
INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`)
SELECT 'admin', NULL, 'no', 'API Dashboard', '/admin/api-dashboard/api-dashboard.php', NULL, 'sts', 'fa-solid fa-gauge-high', 'no', 'allMenu api-dashboard-menu',
       monitoring.id,
       monitoring.next_order,
       'active', CURRENT_TIMESTAMP
FROM (
  SELECT m.id AS id,
         COALESCE((SELECT MAX(c.display_order) FROM s_app_menu c WHERE c.parent_id = m.id), 0) + 1 AS next_order
  FROM s_app_menu m
  WHERE m.display_text = 'Monitoring' AND m.module = 'admin' AND m.link IS NULL AND m.is_header = 'no'
  LIMIT 1
) AS monitoring;
