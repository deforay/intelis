-- Migration file for version 5.7.72
-- Created on 2026-09-16 15:48:36


UPDATE `system_config` SET `value` = '5.7.72' WHERE `system_config`.`name` = 'sc_version';


-- Every table the metadata sync moves by updated_datetime gets a date on every row,
-- and keeps getting one.
--
-- A side asks for rows changed after the newest updated_datetime it holds. When
-- every row it holds has no date it asks with no date, and the whole table comes
-- back on every sync: on the Rwanda STS the five undated r_cd4_test_reasons rows
-- went to every CD4 lab on every call. Rows added with no date were never sent at
-- all once the other side held a dated row.
--
-- 1) Undated rows get a fixed date far in the past, not NOW(). This runs on labs
--    too, and a lab stamping its copy with the upgrade time would stop receiving
--    any change the STS made before that moment. Rows that already have a date
--    are untouched.

UPDATE `r_vl_sample_rejection_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_vl_test_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_vl_sample_type` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_vl_art_regimen` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_vl_test_failure_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_vl_results` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_eid_sample_rejection_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_eid_sample_type` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_eid_results` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_eid_test_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_covid19_sample_rejection_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_covid19_sample_type` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_covid19_comorbidities` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_covid19_results` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_covid19_symptoms` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_covid19_test_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_hepatitis_sample_rejection_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_hepatitis_sample_type` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_hepatitis_comorbidities` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_hepatitis_results` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_hepatitis_test_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_cd4_sample_rejection_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_cd4_sample_types` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_cd4_test_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_generic_sample_rejection_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_generic_test_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_generic_test_failure_reasons` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_funding_sources` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `r_implementation_partners` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `health_facilities` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `testing_labs` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `instruments` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `instrument_machines` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `instrument_controls` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `facility_details` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;
UPDATE `global_config` SET `updated_datetime` = '2000-01-01 00:00:00' WHERE `updated_datetime` IS NULL;

-- 2) New rows get the current time, and a real change to a row stamps it. Many
--    screens edit these tables without setting updated_datetime; while the table
--    was undated those edits reached the other side only because the whole table
--    was re-sent every time. MySQL stamps only when a value actually changes, and
--    a sync that copies a row sets updated_datetime itself, which keeps the
--    source's date. All of these are instant, metadata-only changes.

ALTER TABLE `r_vl_sample_rejection_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_vl_test_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_vl_sample_type` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_vl_art_regimen` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_vl_test_failure_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_vl_results` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_eid_sample_rejection_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_eid_sample_type` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_eid_results` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_eid_test_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_covid19_sample_rejection_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_covid19_sample_type` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_covid19_comorbidities` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_covid19_results` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_covid19_symptoms` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_covid19_test_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_hepatitis_sample_rejection_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_hepatitis_sample_type` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_hepatitis_comorbidities` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_hepatitis_results` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_hepatitis_test_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_cd4_sample_rejection_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_cd4_sample_types` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_cd4_test_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_generic_sample_rejection_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_generic_test_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_generic_test_failure_reasons` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_funding_sources` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `r_implementation_partners` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `health_facilities` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `testing_labs` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `instruments` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `instrument_machines` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `instrument_controls` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- facility_details and global_config get the default only. Every sync call writes
-- the lab's heartbeat into facility_details, so stamping on change would re-send
-- every facility to every lab on every call; global_config saves already stamp
-- every row they touch.

ALTER TABLE `facility_details` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `global_config` MODIFY `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
