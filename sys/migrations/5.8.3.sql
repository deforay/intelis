-- Migration file for version 5.8.3
-- Created on 2026-10-23 13:55:38


UPDATE `system_config` SET `value` = '5.8.3' WHERE `system_config`.`name` = 'sc_version';

