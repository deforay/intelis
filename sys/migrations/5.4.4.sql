-- Migration file for version 5.4.4
-- Remote command plane: additional privileges + sidebar entry for the
-- Lab Command History page. Migration 5.4.3 already added the
-- queue-lis-command.php privilege; this one extends the set.


-- Cancel endpoint (STS-only)
INSERT IGNORE INTO `privileges`
  (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES
  (NULL, 'monitoring', '/admin/monitoring/cancel-lis-command.php', NULL, 'Cancel Lab Command', '3', 'sts');


-- History page + its AJAX data endpoint (STS-only)
INSERT IGNORE INTO `privileges`
  (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES
  (NULL, 'monitoring', '/admin/monitoring/lis-command-history.php',
    '["/admin/monitoring/get-lis-command-history.php"]',
    'Lab Command History', '4', 'sts');


-- Sidebar menu entry under Monitoring (STS-only). Lives next to API Dashboard.
INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`)
SELECT 'admin', NULL, 'no', 'Lab Command History',
       '/admin/monitoring/lis-command-history.php', NULL, 'sts',
       'fa-solid fa-scroll', 'no', 'allMenu lab-command-history-menu',
       monitoring.id,
       monitoring.next_order,
       'active', CURRENT_TIMESTAMP
FROM (
  SELECT m.id AS id,
         COALESCE((SELECT MAX(c.display_order) FROM s_app_menu c WHERE c.parent_id = m.id), 0) + 1 AS next_order
  FROM s_app_menu m
  WHERE m.display_text = 'Monitoring' AND m.module = 'admin'
    AND m.link IS NULL AND m.is_header = 'no'
  LIMIT 1
) AS monitoring;


UPDATE `system_config` SET `value` = '5.4.4' WHERE `system_config`.`name` = 'sc_version';
