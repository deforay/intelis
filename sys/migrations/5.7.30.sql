-- Migration file for version 5.7.30
-- Created on 2026-08-21

-- Two more columns that store a user id as an INT while user_details.user_id is a
-- VARCHAR(50) holding a UUID. This is the same defect 5.7.29 fixed on
-- s_lis_remote_commands.requested_by, found by sweeping the schema for it: casting
-- a UUID to an int keeps only the leading run of digits, so an id becomes 19, or 0
-- for any UUID starting with a hex letter. The fleet runs with sql_mode = '', so
-- nothing complains -- the id is simply thrown away on the way in.


-- Who mapped an ART regimen code onto another. Written by
-- app/vl/reference/update-vl-art-code-alias.php, which passes the whole session
-- user id; the column then truncated it.
ALTER TABLE `r_vl_art_regimen_alias`
  MODIFY COLUMN `mapped_by` VARCHAR(50) NULL DEFAULT NULL;

-- 0 is the id of no user: it is what a UUID beginning with a hex letter collapses
-- to, and the application already reads it as "nobody" (UsersService::
-- getUserNameAndSignature skips '0' explicitly). Clearing it says "not recorded",
-- which is the truth, rather than pointing at a user id that cannot exist.
--
-- Other truncations -- 19 and the like -- are deliberately left alone. They cannot
-- be turned back into a UUID, but user_details.user_id is a varchar and an old
-- instance may legitimately hold short numeric ids, on which the int cast was
-- lossless. A value that might still name a real user is not worth guessing about.
--
-- Note this compares mapped_by against a literal and never against
-- user_details.user_id. r_vl_art_regimen_alias is utf8mb4_general_ci while
-- user_details is typically utf8mb4_0900_ai_ci, so a cross-table string comparison
-- here fails outright with errno 1267 -- the same reason 5.6.4 kept every string
-- comparison for this table inside a single table.
UPDATE `r_vl_art_regimen_alias` SET `mapped_by` = NULL WHERE `mapped_by` = '0';


-- Half of the primary key of user_preferences, which makes this the worse of the
-- two: with the column typed INT, every user whose UUID starts with a hex letter
-- collapses to the same 0, and PRIMARY KEY (user_id, page_id) then hands all of
-- them a single shared preferences row -- one person's saved grid state served to
-- another. Nothing calls UsersService::savePreferences() today, so this has never
-- run in the field. Widening it now means it cannot land that way when it is
-- wired up.
ALTER TABLE `user_preferences`
  MODIFY COLUMN `user_id` VARCHAR(50) NOT NULL;


UPDATE `system_config` SET `value` = '5.7.30' WHERE `system_config`.`name` = 'sc_version';
