-- Migration file for version 5.5.5
-- Created on 2026-05-28 11:40:50
--
-- Add lab_id to the sample-code generation queue so per-test-type services
-- can include the form-selected lab in code generation. Used immediately by
-- TB's facility-postfix logic (so STS users acting as the lab for the
-- selected facility get the same facility-coded sample code as a LIS install
-- would produce); available to other test types in the future.

ALTER TABLE `queue_sample_code_generation`
  ADD COLUMN `lab_id` INT DEFAULT NULL AFTER `prefix`;


UPDATE `system_config` SET `value` = '5.5.5' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
