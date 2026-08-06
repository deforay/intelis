-- Migration file for version 5.6.2
-- Created on 2026-08-06 11:27:39


UPDATE `system_config` SET `value` = '5.6.2' WHERE `system_config`.`name` = 'sc_version';

