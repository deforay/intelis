-- Migration file for version 5.7.66
-- Created on 2026-09-09
--
-- Page usage tracking: one row per user, page and day.
CREATE TABLE IF NOT EXISTS `user_page_usage` (
`usage_id` bigint NOT NULL AUTO_INCREMENT,
`user_id` varchar(256) NOT NULL,
`page_url` varchar(255) NOT NULL,
`page_name` varchar(255) DEFAULT NULL,
`module` varchar(64) DEFAULT NULL,
`usage_date` date NOT NULL,
`visits` int unsigned NOT NULL DEFAULT 1,
`duration_seconds` int unsigned NOT NULL DEFAULT 0,
`first_seen_datetime` datetime NOT NULL,
`last_seen_datetime` datetime NOT NULL,
`last_ip_address` varchar(64) DEFAULT NULL,
PRIMARY KEY (`usage_id`),
UNIQUE KEY `uniq_user_page_day` (`user_id`,`page_url`,`usage_date`),
KEY `idx_upu_date` (`usage_date`),
KEY `idx_upu_page_date` (`page_url`,`usage_date`),
KEY `idx_upu_module_date` (`module`,`usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
INSERT IGNORE INTO `global_config`
(`display_name`, `name`, `value`, `instance_id`, `category`, `remote_sync_needed`,
`updated_datetime`, `updated_by`, `status`)
VALUES
('Track Page Usage', 'track_page_usage', 'yes', NULL, 'general', 'no', NOW(), NULL, 'active');
INSERT IGNORE INTO `privileges`
(`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`,
`display_order`, `show_mode`)
VALUES
(NULL, 'monitoring', '/admin/monitoring/page-usage.php', NULL, 'Page Usage', NULL, 'always');
INSERT INTO `s_app_menu`
(`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`,
`icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`,
`updated_datetime`)
SELECT NULL, 'admin', NULL, 'no', 'Page Usage', '/admin/monitoring/page-usage.php', NULL,
'always', 'fa-solid fa-stopwatch', 'no', 'allMenu page-usage-menu', 7, 25, 'active', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `s_app_menu` WHERE `link` = '/admin/monitoring/page-usage.php');


-- Jeyabanu 10-Sep-2026
ALTER TABLE `user_page_usage` ADD `session_hash` VARCHAR(20) NULL DEFAULT NULL AFTER `duration_seconds`;

UPDATE `system_config` SET `value` = '5.7.66' WHERE `system_config`.`name` = 'sc_version';
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
