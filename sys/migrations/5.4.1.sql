-- Migration file for version 5.4.1
-- Created on 2026-01-28 19:31:48


UPDATE `system_config` SET `value` = '5.4.1' WHERE `system_config`.`name` = 'sc_version';

