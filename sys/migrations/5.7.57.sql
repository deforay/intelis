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
-- Dropping the 5.7.56 name before adding the seed's lands every install on
-- exactly one index whichever state it starts from:
--
--   fresh install          drops the duplicate 5.7.56 added, keeps the seed's
--   upgraded, ran 5.7.56   drops that one, gains `last_modified_datetime`
--   upgraded, never ran it drop is a no-op, gains `last_modified_datetime`
--
-- The drop has to come first, and the reason is the runner rather than the
-- data. add_index_if_missing() now recognises an index by its column list as
-- well as its name, so while `idx_last_modified_datetime` is still on the table
-- an ADD over the same column is read as already satisfied and skipped. Adding
-- first would therefore skip the add on form_tb, form_cd4 and form_generic --
-- the three tables sql/init.sql does not seed with this index -- and the drop
-- below would then take the only one they had, leaving them with none.
--
-- The cost is that an upgraded install has no index on this column between the
-- two statements. That window lasts as long as one rebuild during an upgrade,
-- against a wrong outcome that would last until someone noticed.
--
-- Rebuilding an index on form_vl is an INPLACE operation taking minutes on a
-- large table and wanting a brief metadata lock at each end, so it waits behind
-- any long transaction. Every statement is written so re-running the migration
-- cannot create duplicates.

ALTER TABLE `form_vl`        DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_eid`       DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_covid19`   DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_tb`        DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_cd4`       DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_hepatitis` DROP INDEX `idx_last_modified_datetime`;
ALTER TABLE `form_generic`   DROP INDEX `idx_last_modified_datetime`;

ALTER TABLE `form_vl`        ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_eid`       ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_covid19`   ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_tb`        ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_cd4`       ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_hepatitis` ADD INDEX `last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_generic`   ADD INDEX `last_modified_datetime` (`last_modified_datetime`);

UPDATE `system_config` SET `value` = '5.7.57' WHERE `system_config`.`name` = 'sc_version';
