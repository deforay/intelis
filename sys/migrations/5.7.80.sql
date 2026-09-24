-- Migration file for version 5.7.80
-- Created on 2026-09-24

-- Whether a printed manifest shows patient names, chosen per manifest on Add/Edit
-- Manifest. NULL for every manifest saved before, which keeps following the module's
-- "Show participant name in manifest" setting, so a reprint does not change.
ALTER TABLE `specimen_manifests` ADD COLUMN `show_patient_names` ENUM('yes','no') NULL DEFAULT NULL AFTER `lab_id`;

UPDATE `system_config` SET `value` = '5.7.80' WHERE `system_config`.`name` = 'sc_version';
