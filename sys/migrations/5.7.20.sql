-- Migration file for version 5.7.20
-- Created on 2026-08-18 19:43:11


UPDATE `system_config` SET `value` = '5.7.20' WHERE `system_config`.`name` = 'sc_version';

