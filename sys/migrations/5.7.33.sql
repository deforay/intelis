-- Migration file for version 5.7.33
-- Created on 2026-08-24 17:27:36


UPDATE `system_config` SET `value` = '5.7.33' WHERE `system_config`.`name` = 'sc_version';

