-- Migration file for version 5.7.60
-- Created on 2026-09-07
--
-- Retires `patients_old` and lands `support`.`status`.
--
-- patients_old is what 5.2.6 left behind. That migration renamed the live
-- `patients` table out of the way and created a new one in its place, and
-- nothing was ever copied across or read back: no migration since mentions the
-- old table, and no line of application code refers to it. It has sat on every
-- upgraded instance for two years as a table nothing opens.
--
-- It is also permanently in drift and cannot be brought out of it. Each
-- instance's copy is whatever its `patients` table looked like before 5.2.6,
-- which differs per install -- the four statements that would have reshaped
-- them are commented out in 5.2.6 -- while sql/init.sql carries one particular
-- instance's version of that shape. Every install that upgraded through 5.2.6
-- therefore reports a missing column on it forever, and `intelis check` fails
-- on three Cameroon instances today for exactly that reason and nothing else.
-- No migration will ever halt on it, because no migration will ever touch it;
-- the finding is pure noise, and a check that cries wolf stops being read.
--
-- Unlike 5.7.58, sql/init.sql IS edited alongside this one. That migration
-- dropped duplicate indexes the seed creates, and leaving the seed alone let
-- both populations converge. A table is different: leave it declared and every
-- fresh install would create patients_old only for this same upgrade to drop
-- it, and then report it missing from then on. The seed and the migration have
-- to agree that the table is gone.
--
-- Dropping is irreversible and the rows are the only copy, so this is worth
-- stating plainly: they are pre-5.2.6 patient registry entries, superseded by
-- the `patients` table that replaced them, and the patient details attached to
-- any actual sample live on the request row itself rather than here.
--
-- support.status is unrelated and much smaller. 5.0.9 introduced the column
-- inside a CREATE TABLE for a table that already existed on installs older
-- than it, so the CREATE failed as a duplicate -- benign, and correctly
-- ignored -- and took the new column down with it. app/support/saveScreenshot.php
-- writes `status` = 'sent' once the support mail goes out, so on those installs
-- that write has been failing ever since, after the mail has already been sent.

DROP TABLE IF EXISTS `patients_old`;

ALTER TABLE `support` ADD COLUMN `status` VARCHAR(100) NULL DEFAULT 'active';

UPDATE `system_config` SET `value` = '5.7.60' WHERE `system_config`.`name` = 'sc_version';
