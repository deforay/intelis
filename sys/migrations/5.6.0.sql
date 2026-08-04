-- Migration file for version 5.6.0
-- Created on 2026-08-04 11:10:51


UPDATE `system_config` SET `value` = '5.6.0' WHERE `system_config`.`name` = 'sc_version';

