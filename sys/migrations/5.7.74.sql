-- Migration file for version 5.7.74
-- Created on 2026-09-21 15:08:35


UPDATE `system_config` SET `value` = '5.7.74' WHERE `system_config`.`name` = 'sc_version';

