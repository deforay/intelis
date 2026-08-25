-- Migration file for version 5.7.36
-- Created on 2026-08-25 08:55:35


UPDATE `system_config` SET `value` = '5.7.36' WHERE `system_config`.`name` = 'sc_version';

