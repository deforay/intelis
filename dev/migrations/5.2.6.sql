-- Amit 22-Nov-2023 version 5.2.6
UPDATE `system_config` SET `value` = '5.2.6' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 24-Nov-2023
ALTER TABLE `patients` CHANGE `patient_code_key` `patient_code_key` INT NULL DEFAULT NULL;

