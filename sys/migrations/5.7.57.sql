-- Migration file for version 5.7.57
-- Created on 2026-09-05
--
-- Converges the request tables on one index over last_modified_datetime.
--
-- 5.7.56 added that index as `idx_last_modified_datetime`. sql/init.sql already
-- declares the same index on form_vl, form_eid, form_covid19 and form_hepatitis
-- under the name `last_modified_datetime`, and the runner decides whether an
-- index is already present by NAME. A fresh install therefore came out of 5.7.56
-- carrying two identical indexes on four tables, paying the storage and the
-- write maintenance for both forever. An upgraded install that never had the
-- index -- the DRC server is one -- came out with only the new name, so the two
-- populations disagree about what the index is called.
--
-- 5.7.56 is already released, so it cannot be edited to fix this: an install
-- that has run it would not run it again, and changing the file would only make
-- fresh installs diverge further.
--
-- Adding under the seed's name before dropping the other one lands every
-- install on exactly one index whichever state it starts from:
--
--   fresh install          already has `last_modified_datetime` (add skips),
--                          drops the duplicate 5.7.56 added
--   upgraded, ran 5.7.56   gains `last_modified_datetime`, drops the other
--   upgraded, never ran it gains `last_modified_datetime`, drop is a no-op
--
-- The add must come first. Dropping first would leave an upgraded install with
-- no index at all for the length of the rebuild, and the sync reads this column
-- on every run.
--
-- Rebuilding an index on form_vl is an INPLACE operation taking minutes on a
-- large table and wanting a brief metadata lock at each end, so it waits behind
-- any long transaction. Every statement is written so re-running the migration
-- cannot create duplicates.

ALTER TABLE `form_vl`        ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_eid`       ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_covid19`   ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_tb`        ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_cd4`       ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_hepatitis` ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_generic`   ADD INDEX `last_modified_datetime` (`last_modified_datetime`);

ALTER TABLE `form_vl`        DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_eid`       DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_covid19`   DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_tb`        DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_cd4`       DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_hepatitis` DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_generic`   DROP INDEX `idx_last_modified_datetime`;

UPDATE `system_config` SET `value` = '5.7.57' WHERE `system_config`.`name` = 'sc_version';
