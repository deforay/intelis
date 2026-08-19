-- Migration file for version 5.7.26
--
-- Brings form_covid19 back in line with the migration chain, so the table can be
-- converted to utf8mb4 at all.
--
-- 4.4.3 changed seven of these columns from VARCHAR to TEXT. sql/init.sql, which
-- every fresh install starts from, was dumped from a database where that change
-- had only partly landed: patient_name and patient_surname made it, these seven
-- did not. Because init.sql seeds sc_version = 5.3.2, the runner never replays
-- 4.4.3, so nothing has ever corrected them, and an install created from the
-- baseline carries a form_covid19 roughly 11,000 bytes wider than one that came
-- up through upgrades.
--
-- That width is not cosmetic. A row's declared size has a hard 65535-byte ceiling
-- with VARCHARs counted at 4 bytes per character under utf8mb4, and these seven
-- push the table over it. Any rebuild then fails with
--
--   ERROR 1118 (42000): Row size too large ... maximum row size ... is 65535
--
-- which includes ALTER TABLE ... CONVERT TO CHARACTER SET. The consequence found
-- in the field: an instance whose tables straddle two collations cannot be
-- normalised, because the one table that has to move is the one that cannot be
-- rebuilt, and any migration joining form_covid19 to a differently-collated table
-- dies on "Illegal mix of collations".
--
-- Each TEXT column costs about 12 bytes against that ceiling instead of 4n+2, so
-- these seven return roughly 11,030 bytes -- patient_address alone is 3,990 -- and
-- take the table clear of the limit.
--
-- No CHARACTER SET clause on purpose. The columns keep whatever the table's own
-- default is, so this changes width only and cannot introduce a third collation
-- into a database that already has two. Run in one statement so the table is
-- rebuilt once rather than seven times; form_covid19 is empty on most instances,
-- since a lab that never ran covid19 testing still has the table.
--
-- Safe to apply where the columns are already TEXT: the statement is then a no-op
-- rebuild, not an error.

ALTER TABLE `form_covid19`
  CHANGE `patient_address`       `patient_address`       TEXT NULL DEFAULT NULL,
  CHANGE `reason_of_visit`       `reason_of_visit`       TEXT NULL DEFAULT NULL,
  CHANGE `sample_code_format`    `sample_code_format`    TEXT NULL DEFAULT NULL,
  CHANGE `tested_by`             `tested_by`             TEXT NULL DEFAULT NULL,
  CHANGE `source_of_alert`       `source_of_alert`       TEXT NULL DEFAULT NULL,
  CHANGE `source_of_alert_other` `source_of_alert_other` TEXT NULL DEFAULT NULL,
  CHANGE `last_modified_by`      `last_modified_by`      TEXT NULL DEFAULT NULL;


UPDATE `system_config` SET `value` = '5.7.26' WHERE `system_config`.`name` = 'sc_version';
