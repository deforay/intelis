-- Migration file for version 5.7.3
-- Created on 2026-08-15 13:13:24


UPDATE `system_config` SET `value` = '5.7.3' WHERE `system_config`.`name` = 'sc_version';

