-- Migration file for version 5.7.29
-- Created on 2026-08-21 10:52:33

-- s_lis_remote_commands.requested_by was INT, but user_details.user_id is a
-- VARCHAR(50) holding a UUID. Two things went wrong at once:
--
--   Writing:  (int) '019a3f2c-8b1d-...' keeps only the leading run of digits,
--             so a user id collapsed to 19, or to 0 for any UUID starting
--             with a hex letter. The requester was not recorded, it was
--             discarded.
--
--   Reading:  the history join `u.user_id = c.requested_by` compared a
--             VARCHAR to an INT, so MySQL coerced every row of user_details
--             to a number by the same leading-digits rule and matched all of
--             them. One command rendered as one row per user -- dozens of
--             identical lines, each with its own Replay link.
--
-- Widening the column fixes both: the id is stored whole, and the join
-- becomes VARCHAR to VARCHAR.
--
-- Existing rows keep their truncated numbers as strings. Those digits cannot
-- be turned back into a user id -- the information is gone -- so they simply
-- stop matching anything and the history shows an em dash for who asked.
-- That is the honest answer, and it is what the column already meant.

ALTER TABLE `s_lis_remote_commands`
  MODIFY COLUMN `requested_by` VARCHAR(50) NULL DEFAULT NULL;


UPDATE `system_config` SET `value` = '5.7.29' WHERE `system_config`.`name` = 'sc_version';
