-- Migration file for version 5.5.8
--
-- Result/rejection change-reason history.
--
-- The per-test TB reason column was VARCHAR(255), too small to hold the accumulating
-- JSON change-history that the other modules keep on their form_* rows. Widen it to
-- TEXT so TB can store the same canonical JSON array per test.
--
-- (No change is needed for the other reason columns -- form_vl.reason_for_result_changes,
-- form_eid/covid19/tb.reason_for_changing, form_cd4.reason_for_result_changes,
-- form_generic.reason_for_test_result_changes -- they are already TEXT/MEDIUMTEXT.)
--
-- Existing rows in any of these columns are normalized to the canonical JSON array by
-- run-once/normalize-change-history.php (idempotent; readers tolerate every legacy shape
-- regardless).

ALTER TABLE `tb_tests` CHANGE `reason_for_result_change` `reason_for_result_change` TEXT CHARACTER SET utf8mb4 NULL DEFAULT NULL;

UPDATE `system_config` SET `value` = '5.5.8' WHERE `system_config`.`name` = 'sc_version';
