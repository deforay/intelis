-- Migration file for version 5.7.44
-- Created on 2026-08-25 13:49:19


UPDATE `system_config` SET `value` = '5.7.44' WHERE `system_config`.`name` = 'sc_version';

