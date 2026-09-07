-- Migration file for version 5.7.63
-- Created on 2026-09-07
--
-- Re-asserts the three tables a test type writes its per-test results to.
--
-- The DRC production instance has 53,645 COVID requests taken between May 2021
-- and May 2024, 46,948 of them with results, and no covid19_tests table. The
-- application cannot have run that way: covid-19-add-request-helper.php writes
-- to it on every request, so it was there throughout and went afterwards. What
-- removed it is not recoverable -- nothing in this repository drops it, and the
-- only table-dropping script is confined to audit_form_* -- so this repairs the
-- state rather than the cause. Every COVID page there fails on 1146 until it is
-- back: add, edit, result entry, the exports and the DHIS2 sender all read or
-- write that table.
--
-- All three are re-asserted rather than only the one known to be missing. They
-- are the same kind of table, they are all reachable the same way, and an
-- IF NOT EXISTS costs an installation that has them nothing. This is the set
-- TestsService declares as childResultTable, which is also the set preflight
-- now escalates when the requests are present and the result table is not.
--
-- 5.7.61 re-asserts what 5.2.9 creates; these three are older than that --
-- covid19_tests comes from 4.4.3 -- so they are outside its scope.
--
-- Definitions are taken from sql/init.sql, so a fresh install and a repaired
-- one end up with the same table rather than the shape whichever migration
-- happened to create it years ago. The rows are gone either way: this restores
-- somewhere to write, not what was written. The headline result of each request
-- is on the form row itself and was never in here.

-- The definitions carry no COLLATE clause, though sql/init.sql's do. That file
-- is a dump from MySQL 8, where utf8mb4_0900_ai_ci is the default; naming it
-- here would fail with 1273 on MySQL 5.7, which README lists as supported, and
-- a failed CREATE leaves sc_version behind and the module still broken -- the
-- exact stranding this migration exists to undo. Without the clause each server
-- applies its own default for utf8mb4, and `composer db:collation` is what
-- brings an installation's tables into line.

-- The definitions carry no FOREIGN KEY, though sql/init.sql's do, and the
-- reason is the state this migration must not make worse. A country deployment
-- that never enabled a module has neither its request table nor its result
-- table -- preflight reads exactly that pair as a module removed rather than
-- broken, and passes it. Creating the result table there with a key onto the
-- absent request table raises 1824, which the runner does not treat as benign:
-- the migration halts, sc_version stays behind, and every later version is
-- blocked for good on an installation that had nothing wrong with it.
--
-- The index the key sits on is kept, so lookups by request are unaffected. What
-- is lost is the constraint, on repaired installations only; a fresh install
-- still takes its tables from sql/init.sql with the key intact.

CREATE TABLE IF NOT EXISTS `covid19_tests` (
  `test_id` int NOT NULL AUTO_INCREMENT,
  `covid19_id` int NOT NULL,
  `facility_id` int DEFAULT NULL,
  `test_name` varchar(500) NOT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime NOT NULL,
  `testing_platform` varchar(255) DEFAULT NULL,
  `instrument_id` varchar(50) DEFAULT NULL,
  `kit_lot_no` varchar(256) DEFAULT NULL,
  `kit_expiry_date` date DEFAULT NULL,
  `result` varchar(500) NOT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_id`),
  KEY `covid19_id` (`covid19_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_tests` (
  `tb_test_id` int NOT NULL AUTO_INCREMENT,
  `tb_id` int DEFAULT NULL,
  `lab_id` int DEFAULT NULL,
  `specimen_type` varchar(255) DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` varchar(255) DEFAULT NULL,
  `rejection_on` datetime DEFAULT NULL,
  `test_type` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `actual_no` varchar(256) DEFAULT NULL,
  `test_result` varchar(256) DEFAULT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `result_reviewed_by` varchar(255) DEFAULT NULL,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_approved_by` varchar(255) DEFAULT NULL,
  `result_approved_datetime` datetime DEFAULT NULL,
  `revised_by` varchar(255) DEFAULT NULL,
  `revised_on` datetime DEFAULT NULL,
  `reason_for_result_change` text,
  `comments` mediumtext,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`tb_test_id`),
  KEY `tb_id` (`tb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `generic_test_results` (
  `test_id` int NOT NULL AUTO_INCREMENT,
  `generic_id` int NOT NULL,
  `facility_id` int DEFAULT NULL,
  `sub_test_name` varchar(256) DEFAULT NULL,
  `final_result_unit` varchar(256) DEFAULT NULL,
  `result_type` varchar(256) DEFAULT NULL,
  `test_name` varchar(500) NOT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `testing_platform` varchar(255) DEFAULT NULL,
  `kit_lot_no` varchar(256) DEFAULT NULL,
  `kit_expiry_date` date DEFAULT NULL,
  `result` varchar(500) NOT NULL,
  `final_result` varchar(256) DEFAULT NULL,
  `result_unit` int DEFAULT NULL,
  `final_result_interpretation` text,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `lab_id` int DEFAULT NULL,
  `specimen_type` varchar(255) DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` varchar(255) DEFAULT NULL,
  `rejection_on` datetime DEFAULT NULL,
  `result_reviewed_by` varchar(255) DEFAULT NULL,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_approved_by` varchar(255) DEFAULT NULL,
  `result_approved_datetime` datetime DEFAULT NULL,
  `revised_by` varchar(255) DEFAULT NULL,
  `revised_on` datetime DEFAULT NULL,
  `reason_for_result_change` text,
  `comments` mediumtext,
  `data_sync` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`test_id`),
  KEY `generic_id` (`generic_id`),
  KEY `idx_generic_test_results_lab` (`lab_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE `system_config` SET `value` = '5.7.63' WHERE `system_config`.`name` = 'sc_version';
