-- Migration file for version 5.7.38
-- Created on 2026-08-25 11:12:42


UPDATE `system_config` SET `value` = '5.7.38' WHERE `system_config`.`name` = 'sc_version';

