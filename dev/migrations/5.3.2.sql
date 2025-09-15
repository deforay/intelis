-- Migration file for version 5.3.2
-- Created on 2025-09-15 14:54:06


UPDATE `system_config` SET `value` = '5.3.2' WHERE `system_config`.`name` = 'sc_version';

