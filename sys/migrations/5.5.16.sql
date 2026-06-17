-- Migration file for version 5.5.16
--
-- Per-user testing-lab assignment for STS-as-LIS ("liscloud").
--
-- A user's *lab identity* (which single testing lab they operate as) is a
-- distinct axis from their *facility map* (which collection sites' samples they
-- may see/add). facilityMap can span thousands of collection sites; the testing
-- lab is exactly one facility (facility_type = 2). Conflating the two — inferring
-- the lab from facilityMap — was wrong, so we store the lab explicitly per user.
--
-- On STS, an admin assigns this for testing-lab-role users. On LIS/standalone it
-- is forced server-side to sc_testing_lab_id (never accepted from the form), so
-- single-lab installs are unaffected and the value cannot be spoofed.
--
-- Nullable: collection-site users and unassigned users keep NULL.

ALTER TABLE `user_details`
  ADD COLUMN `testing_lab_id` INT DEFAULT NULL AFTER `role_id`;

ALTER TABLE `user_details`
  ADD INDEX `idx_user_details_testing_lab` (`testing_lab_id`);


UPDATE `system_config` SET `value` = '5.5.16' WHERE `system_config`.`name` = 'sc_version';

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
