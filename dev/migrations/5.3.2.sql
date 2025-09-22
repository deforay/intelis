-- Migration file for version 5.3.2
-- Created on 2025-09-15 14:54:06


UPDATE `system_config` SET `value` = '5.3.2' WHERE `system_config`.`name` = 'sc_version';

-- Amit 22-Sep-2024

INSERT INTO `global_config`
(`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_datetime`, `updated_by`, `status`)
VALUES
('Auto Approve Interface Results', 'auto_approve_interface_results', 'no', 'interfacing', 'yes', CURRENT_TIMESTAMP, NULL, 'active');
