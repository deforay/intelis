-- Migration file for version 5.7.8
-- Created on 2026-08-18 14:46:46


UPDATE `system_config` SET `value` = '5.7.8' WHERE `system_config`.`name` = 'sc_version';

