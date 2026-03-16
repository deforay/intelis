-- Migration file for version 5.4.2
-- Created on 2026-03-16 11:13:44


UPDATE `system_config` SET `value` = '5.4.2' WHERE `system_config`.`name` = 'sc_version';

