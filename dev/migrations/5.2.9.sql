-- Amit 07-Feb-2024 version 5.2.9
UPDATE `system_config` SET `value` = '5.2.9' WHERE `system_config`.`name` = 'sc_version';

-- Brindha 08-Feb-2024 version 5.2.9
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Display VL Log Result', 'vl_display_log_result', 'yes', 'vl', 'no', NULL, NULL, 'active');