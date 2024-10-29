
-- Amit 25-Oct-2024
UPDATE `system_config` SET `value` = '5.3.0' WHERE `system_config`.`name` = 'sc_version';

--Amit 28-Oct-2024
UPDATE facility_details set sts_token= UUID() where facility_type = 2 and sts_token is null;
