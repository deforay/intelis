-- Migration file for version 5.7.1
-- Created on 2026-08-15 11:59:54


UPDATE `system_config` SET `value` = '5.7.1' WHERE `system_config`.`name` = 'sc_version';

