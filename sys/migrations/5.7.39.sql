-- Migration file for version 5.7.39
-- Created on 2026-08-25 11:39:56


UPDATE `system_config` SET `value` = '5.7.39' WHERE `system_config`.`name` = 'sc_version';

