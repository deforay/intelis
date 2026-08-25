-- Migration file for version 5.7.37
-- Created on 2026-08-25 09:20:09


UPDATE `system_config` SET `value` = '5.7.37' WHERE `system_config`.`name` = 'sc_version';

