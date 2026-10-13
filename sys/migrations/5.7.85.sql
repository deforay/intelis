-- Migration file for version 5.7.85
-- Created on 2026-10-08 18:39:40

-- What the analyzer reported about each viral load and EID run, read from its own
-- message (orders.raw_text) for results from the Interface Tool:
--
--   instrument_model   the model as the analyzer names itself ("Alinity m", "c5800",
--                      "m2000", "GeneXpert"), whatever the lab called the instrument
--   instrument_serial  its serial number: the one identifier of the physical machine
--   analyzer_run_id    the analyzer's own run or plate ID (m2000 run, cobas run,
--                      GeneXpert cartridge): the real batch of an interfaced result
--   analyzer_message   the analyzer's error or flag codes and text for the run
--                      ("U06T Pipetting anomaly ...", "Error 2014: ..."): why a test failed
--   analyzer_readings  cycle thresholds, internal control and other values reported
--                      with the result, as a JSON list
--
-- Empty for results entered by hand or imported from files.
--
-- analyzer_message is TEXT, not VARCHAR(500): a VARCHAR(500) holds 2000 bytes of the
-- table's 65,535-byte row limit, and on a lab whose form_eid was already near it the
-- column could not be added, stopping the update. Labs that added it as VARCHAR(500)
-- keep it; rebuilding form_vl to change the type costs more than it buys.
ALTER TABLE `form_vl` ADD `instrument_model` VARCHAR(100) NULL DEFAULT NULL AFTER `assay_name`;
ALTER TABLE `form_vl` ADD `instrument_serial` VARCHAR(100) NULL DEFAULT NULL AFTER `instrument_model`;
ALTER TABLE `form_vl` ADD `analyzer_run_id` VARCHAR(100) NULL DEFAULT NULL AFTER `instrument_serial`;
ALTER TABLE `form_vl` ADD `analyzer_message` TEXT NULL DEFAULT NULL AFTER `analyzer_run_id`;
ALTER TABLE `form_vl` ADD `analyzer_readings` JSON NULL DEFAULT NULL AFTER `analyzer_message`;

ALTER TABLE `form_eid` ADD `instrument_model` VARCHAR(100) NULL DEFAULT NULL AFTER `assay_name`;
ALTER TABLE `form_eid` ADD `instrument_serial` VARCHAR(100) NULL DEFAULT NULL AFTER `instrument_model`;
ALTER TABLE `form_eid` ADD `analyzer_run_id` VARCHAR(100) NULL DEFAULT NULL AFTER `instrument_serial`;
ALTER TABLE `form_eid` ADD `analyzer_message` TEXT NULL DEFAULT NULL AFTER `analyzer_run_id`;
ALTER TABLE `form_eid` ADD `analyzer_readings` JSON NULL DEFAULT NULL AFTER `analyzer_message`;


UPDATE `system_config` SET `value` = '5.7.85' WHERE `system_config`.`name` = 'sc_version';

