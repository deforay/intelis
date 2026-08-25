-- Migration file for version 5.7.43
-- Created on 2026-08-25 13:13:01


UPDATE `system_config` SET `value` = '5.7.43' WHERE `system_config`.`name` = 'sc_version';

