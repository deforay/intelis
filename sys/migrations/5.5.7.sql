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
