-- Migration file for version 5.5.0
-- Created on 2026-05-11 14:37:24


UPDATE `system_config` SET `value` = '5.5.0' WHERE `system_config`.`name` = 'sc_version';

