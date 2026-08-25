-- Migration file for version 5.7.40
-- Created on 2026-08-25 12:02:30


UPDATE `system_config` SET `value` = '5.7.40' WHERE `system_config`.`name` = 'sc_version';

