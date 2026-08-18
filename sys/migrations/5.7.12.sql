-- Migration file for version 5.7.12
-- Created on 2026-08-18 15:11:39


UPDATE `system_config` SET `value` = '5.7.12' WHERE `system_config`.`name` = 'sc_version';

