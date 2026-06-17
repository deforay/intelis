-- Migration file for version 5.5.19
-- Created on 2026-06-17 15:33:02


UPDATE `system_config` SET `value` = '5.5.19' WHERE `system_config`.`name` = 'sc_version';

