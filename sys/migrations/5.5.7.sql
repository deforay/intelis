-- Migration file for version 5.5.7
-- Created on 2026-06-03
--
-- Custom Test (test type) export / import.
--
-- Registers the two new endpoints under the existing "Add/Edit Test Types"
-- privilege (id 347) so anyone who can manage test types can export a
-- configured test to a portable JSON file and import it -- landing on an
-- editable form -- on another instance.

UPDATE `privileges`
SET `shared_privileges` = '["/generic-tests/configuration/add-test-type.php", "/generic-tests/configuration/edit-test-type.php", "/generic-tests/configuration/clone-test-type.php", "/generic-tests/configuration/export-test-type.php", "/generic-tests/configuration/import-test-type.php"]'
WHERE `privilege_name` = '/generic-tests/configuration/test-type.php';


UPDATE `system_config` SET `value` = '5.5.7' WHERE `system_config`.`name` = 'sc_version';

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

-- Thana 05-May-2026
CREATE TABLE IF NOT EXISTS `generic_referral_history` (  `history_id` int NOT NULL AUTO_INCREMENT,
  `generic_id` int DEFAULT NULL,
  `from_lab_id` int NOT NULL,
  `to_lab_id` int NOT NULL,
  `reason_for_referral` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `referred_on_datetime` datetime NOT NULL,
  `referred_by` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;