-- Migration file for version 5.7.21
-- Created on 2026-08-19


-- Amit 19-Aug-2026 - Stop the result importers inventing requests they cannot match.
-- When a machine file carries a sample code with no matching request, the importers
-- insert a brand new form_* row built from the instrument file alone -- no facility,
-- no collection date, no reason for testing, no source_of_request. GeneXpert runs VL,
-- EID and TB on one instrument and exports them in a single file, so every non-VL code
-- in that file was landing in form_vl as a new VL sample. On the DRC STS that is 341
-- rows since 2025, of which only 43 carry an actual viral load.
--
-- The setting was seeded per-instance with remote_sync_needed = 'no', so a lab stayed
-- on the permissive default even where the STS had already turned it off. Sync it out
-- and make 'no' the standard everywhere. Upsert rather than update, so an install that
-- never got the seeded row does not silently keep the permissive default.
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_datetime`, `status`)
VALUES ('Import Non matching Sample Results from Machine generated file', 'import_non_matching_sample', 'no', 'general', 'yes', CURRENT_TIMESTAMP, 'active')
ON DUPLICATE KEY UPDATE
    `value` = 'no',
    `remote_sync_needed` = 'yes',
    `updated_datetime` = CURRENT_TIMESTAMP,
    `status` = 'active';

UPDATE `system_config` SET `value` = '5.7.21' WHERE `system_config`.`name` = 'sc_version';
