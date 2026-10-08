-- Migration file for version 5.7.82
-- Created on 2026-10-08 10:40:31

-- The assay each viral load and EID test was run with, as the instrument names it
-- ("HIV1.0mlDBS", "Xpert HIV-1 Viral Load", ...). The instrument and its lot were
-- already kept; the assay was read from the instrument and then dropped. Programs
-- count tests by platform and assay to plan reagent orders and to follow failure
-- rates, and could not split, for example, plasma from DBS runs on the same machine.
--
-- Filled from Interface Tool results and from the result files that carry the
-- assay (GeneXpert, Abbott m2000). Results entered by hand leave it empty.
ALTER TABLE `form_vl` ADD `assay_name` VARCHAR(255) NULL DEFAULT NULL AFTER `instrument_id`;

ALTER TABLE `form_eid` ADD `assay_name` VARCHAR(255) NULL DEFAULT NULL AFTER `instrument_id`;

-- The Interface Machine Activity page now also counts tests by instrument and assay,
-- whichever way the result arrived. Only the label changes: the link and the
-- privilege row stay as they are, so every role grant keeps working.
UPDATE `privileges`
   SET `display_name` = 'Instrument Activity'
 WHERE `privilege_name` = '/reports/interface-machine-activity.php';

UPDATE `s_app_menu`
   SET `display_text` = 'Instrument Activity',
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `link` = '/reports/interface-machine-activity.php';

UPDATE `system_config` SET `value` = '5.7.82' WHERE `system_config`.`name` = 'sc_version';
