-- Migration file for version 5.4.0
-- Created on 2025-11-19 08:39:49


UPDATE `system_config` SET `value` = '5.4.0' WHERE `system_config`.`name` = 'sc_version';

