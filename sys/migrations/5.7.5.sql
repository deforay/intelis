-- Migration file for version 5.7.5
-- Created on 2026-08-15 15:46:38


UPDATE `system_config` SET `value` = '5.7.5' WHERE `system_config`.`name` = 'sc_version';

