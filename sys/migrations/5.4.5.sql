-- Migration file for version 5.4.5
-- Created on 2026-04-29 21:52:53


UPDATE `system_config` SET `value` = '5.4.5' WHERE `system_config`.`name` = 'sc_version';

