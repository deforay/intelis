-- Migration file for version 5.7.22
-- Created on 2026-08-18 23:43:54


UPDATE `system_config` SET `value` = '5.7.22' WHERE `system_config`.`name` = 'sc_version';

