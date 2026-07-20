-- Migration file for version 5.5.24
--
-- Adds the Interface Machine Activity report to the Monitoring menu. The report
-- reads instrument_activity_log, which 5.5.23 created.
--
-- Every statement is written so re-running the migration cannot create duplicates.

INSERT IGNORE INTO `privileges` (`resource_id`, `privilege_name`, `display_name`, `show_mode`)
VALUES (
  'monitoring',
  '/admin/monitoring/interface-machine-activity.php',
  'Interface Machine Activity',
  'always'
);

-- Grant the report to every role that can already see the API History report,
-- rather than to everyone: both show operational plumbing rather than patient data.
INSERT INTO `roles_privileges_map` (`role_id`, `privilege_id`)
SELECT rp.`role_id`, np.`privilege_id`
  FROM `roles_privileges_map` rp
  JOIN `privileges` sp ON sp.`privilege_id` = rp.`privilege_id`
                      AND sp.`privilege_name` = '/admin/monitoring/api-sync-history.php'
  JOIN `privileges` np ON np.`privilege_name` = '/admin/monitoring/interface-machine-activity.php'
 WHERE NOT EXISTS (
     SELECT 1 FROM `roles_privileges_map` x
      WHERE x.`role_id` = rp.`role_id` AND x.`privilege_id` = np.`privilege_id`
 );

INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
   `updated_datetime`)
VALUES
  ('admin', NULL, 'no', 'Interface Machine Activity',
   '/admin/monitoring/interface-machine-activity.php', NULL, 'always',
   'fa-solid fa-plug', 'no', 'allMenu  interface-machine-activity-menu', 7, 23, 'active',
   CURRENT_TIMESTAMP);

UPDATE `system_config` SET `value` = '5.5.24' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
