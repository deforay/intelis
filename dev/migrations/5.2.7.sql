-- Amit 06-Dec-2023 version 5.2.7
UPDATE `system_config` SET `value` = '5.2.7' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 06-Dec-2023
ALTER TABLE `patients` ADD `data_sync` INT NULL DEFAULT '0' AFTER `patient_registered_by`;

-- Jeyabanu 07-Dec-2023
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'generic-requests', '/generic-tests/requests/edit-locked-generic-tests-samples', NULL, 'Edit Locked Generic Tests Samples', '6', 'always');
