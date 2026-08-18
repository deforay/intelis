-- Migration file for version 5.7.13
-- Created on 2026-08-18 16:00:32


UPDATE `system_config` SET `value` = '5.7.13' WHERE `system_config`.`name` = 'sc_version';

