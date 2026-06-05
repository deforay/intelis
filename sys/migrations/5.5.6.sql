-- Migration file for version 5.5.6
-- Created on 2026-05-28 12:55:45
--
-- API facility-scope hardening (final stage of the STS-as-LIS rollout).
--
-- Adds a feature flag controlling whether the new per-endpoint
-- facilityMap intersection on the API write paths (save-request,
-- cancel-requests, instruments) is enforced or merely observed.
--
--   'no'  (default): the check runs and LOGS every out-of-scope API
--                    request, but does NOT reject it. Existing flows
--                    keep working while operators verify the logs are
--                    clean.
--   'yes':          enforce -- out-of-scope facility/lab ids are
--                    rejected on save, instrument lookups, and cancels.
--
-- Flip to 'yes' once the logs confirm no legitimate flow is
-- mis-flagged. LIS installs are no-op regardless of the flag.

INSERT IGNORE INTO `global_config` (`name`, `display_name`, `value`, `category`, `status`)
VALUES ('api_facility_scope_enforce', 'Enforce API facility scope (vs observe-only)', 'no', 'api', 'active');


<<<<<<< Updated upstream
UPDATE `system_config` SET `value` = '5.5.6' WHERE `system_config`.`name` = 'sc_version';

-- Thana 04-May-2026
INSERT IGNORE INTO `privileges` 
(`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES 
(NULL, 'generic-results', '/generic-tests/results/generic-referral-list.php', NULL, 'Refer to another lab', '2', 'always');

INSERT IGNORE INTO `privileges` 
(`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES 
(NULL, 'generic-results', '/generic-tests/results/add-generic-referral.php', NULL, 'Add Referral', '2', 'always');

INSERT IGNORE INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'generic-tests', NULL, 'no', 'Refer to another lab', '/generic-tests/results/generic-referral-list.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericFailedResultsMenu', '62', '90', 'active', CURRENT_TIMESTAMP);
UPDATE `s_app_menu` SET `inner_pages` = '/generic-tests/results/add-generic-referral.php,/generic-tests/results/edit-generic-referral.php' WHERE `s_app_menu`.`id` = 528;


ALTER TABLE `form_generic` ADD `referral_manifest_code` VARCHAR(64) NULL DEFAULT NULL AFTER `sample_package_code`;
ALTER TABLE `form_generic` ADD `referred_by_lab_id` INT NULL DEFAULT NULL AFTER `lab_id`;
ALTER TABLE `form_generic` ADD `referred_to_lab_id` INT NULL DEFAULT NULL AFTER `referred_by_lab_id`;
ALTER TABLE `form_generic` ADD `reason_for_referral` VARCHAR(128) NULL DEFAULT NULL AFTER `referred_to_lab_id`;
ALTER TABLE `form_generic` ADD `referred_on_datetime` DATETIME NULL DEFAULT NULL AFTER `reason_for_referral`;
ALTER TABLE `form_generic` ADD `referred_by` VARCHAR(128) NULL DEFAULT NULL AFTER `referred_on_datetime`;
ALTER TABLE `form_generic` ADD `instrument_id` VARCHAR(128) NULL AFTER `test_platform`;
=======
UPDATE `system_config` SET `value` = '5.5.6' WHERE `system_config`.`name` = 'sc_version';
>>>>>>> Stashed changes
