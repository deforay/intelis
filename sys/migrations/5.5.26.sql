-- Migration file for version 5.5.26
--
-- Registers the Lab Performance Indicators report (turnaround time, testing
-- volume by entry mode, failure rates, rejection rates, repeat patient
-- results, across all test modules including custom tests). It sits under
-- Monitoring for now; once reviewed it may be elevated (e.g. under Dashboard).
--
-- Every statement is written so re-running the migration cannot create duplicates.

INSERT IGNORE INTO `privileges` (`resource_id`, `privilege_name`, `display_name`, `show_mode`)
VALUES (
  'monitoring',
  '/admin/monitoring/lab-performance-indicators.php',
  'Lab Performance Indicators',
  'always'
);

-- Grant the report to every role that can already see the Interface Machine
-- Activity report: both are lab-wide operational views.
INSERT INTO `roles_privileges_map` (`role_id`, `privilege_id`)
SELECT rp.`role_id`, np.`privilege_id`
  FROM `roles_privileges_map` rp
  JOIN `privileges` sp ON sp.`privilege_id` = rp.`privilege_id`
                      AND sp.`privilege_name` = '/admin/monitoring/interface-machine-activity.php'
  JOIN `privileges` np ON np.`privilege_name` = '/admin/monitoring/lab-performance-indicators.php'
 WHERE NOT EXISTS (
     SELECT 1 FROM `roles_privileges_map` x
      WHERE x.`role_id` = rp.`role_id` AND x.`privilege_id` = np.`privilege_id`
 );

INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
   `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
   `updated_datetime`)
VALUES
  ('admin', NULL, 'no', 'Lab Performance Indicators',
   '/admin/monitoring/lab-performance-indicators.php', NULL, 'always',
   'fa-solid fa-gauge-high', 'no', 'allMenu  lab-performance-indicators-menu', 7, 24, 'active',
   CURRENT_TIMESTAMP);

UPDATE `system_config` SET `value` = '5.5.26' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
