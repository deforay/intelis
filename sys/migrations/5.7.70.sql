-- Migration file for version 5.7.70
-- Created on 2026-09-14 15:40:21
--
-- Amit 14-Sep-2026
--
-- Let the interface result lookup use indexes.
--
-- InterfacingService::findSample() matches an instrument result to a sample with
--
--   sample_code IN (...) OR remote_sample_code IN (...) OR lab_assigned_code IN (...)
--
-- MySQL can only answer an OR through indexes when every branch has one. Nothing
-- indexed lab_assigned_code, so the lookup read the whole table every time, on
-- every lab, whether the lab uses lab assigned codes or not.
--
-- A result with no matching sample costs the most: the lookup runs against every
-- active test table, and then again to decide why it missed. An instrument that
-- uploads its whole history (GeneXpert "Automatic Result Upload") queues thousands
-- of those, and bin/interface.php works through all of them before it reaches the
-- day's results.
--
-- Measured on a 1,410,608-row form_vl copy (DRC):
--
--   before   ~1s     type=ALL, 1.4M rows read
--   after   0.01s    index_merge sort_union(sample_code, remote_sample_code,
--                    idx_lab_assigned_code), rows=4
--
-- form_generic also had no index on remote_sample_code, which breaks the same OR.
--
-- Named, so add_index_if_missing() skips them on a replay. One action per
-- statement. Secondary index creation is ONLINE in MySQL 8; the form_vl index
-- above took 1.6 seconds on that copy.

ALTER TABLE `form_vl` ADD INDEX `idx_lab_assigned_code` (`lab_assigned_code`);

ALTER TABLE `form_eid` ADD INDEX `idx_lab_assigned_code` (`lab_assigned_code`);

ALTER TABLE `form_covid19` ADD INDEX `idx_lab_assigned_code` (`lab_assigned_code`);

ALTER TABLE `form_hepatitis` ADD INDEX `idx_lab_assigned_code` (`lab_assigned_code`);

ALTER TABLE `form_tb` ADD INDEX `idx_lab_assigned_code` (`lab_assigned_code`);

ALTER TABLE `form_cd4` ADD INDEX `idx_lab_assigned_code` (`lab_assigned_code`);

ALTER TABLE `form_generic` ADD INDEX `idx_lab_assigned_code` (`lab_assigned_code`);

ALTER TABLE `form_generic` ADD INDEX `idx_remote_sample_code` (`remote_sample_code`);


UPDATE `system_config` SET `value` = '5.7.70' WHERE `system_config`.`name` = 'sc_version';
