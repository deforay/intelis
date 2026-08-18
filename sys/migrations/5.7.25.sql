-- Migration file for version 5.7.25
-- Created on 2026-08-19 00:46:20


UPDATE `system_config` SET `value` = '5.7.25' WHERE `system_config`.`name` = 'sc_version';

