-- Migration file for version 5.8.10
-- Created on 2026-11-19 18:12:23


UPDATE `system_config` SET `value` = '5.8.10' WHERE `system_config`.`name` = 'sc_version';

