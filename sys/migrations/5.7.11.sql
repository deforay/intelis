-- Migration file for version 5.7.11
-- Created on 2026-08-18 15:00:23


UPDATE `system_config` SET `value` = '5.7.11' WHERE `system_config`.`name` = 'sc_version';

