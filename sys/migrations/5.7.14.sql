-- Migration file for version 5.7.14
-- Created on 2026-08-18 16:07:09


UPDATE `system_config` SET `value` = '5.7.14' WHERE `system_config`.`name` = 'sc_version';

