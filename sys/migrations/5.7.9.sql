-- Migration file for version 5.7.9
-- Created on 2026-08-18 14:50:14


UPDATE `system_config` SET `value` = '5.7.9' WHERE `system_config`.`name` = 'sc_version';

