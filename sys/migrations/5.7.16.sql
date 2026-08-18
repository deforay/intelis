-- Migration file for version 5.7.16
-- Created on 2026-08-18 16:24:42


UPDATE `system_config` SET `value` = '5.7.16' WHERE `system_config`.`name` = 'sc_version';


-- Facility on each flagged record, so the "Needs attention" card can be scoped
-- the way every other listing is. Flags were only broken down by lab, which
-- left a facility-scoped user either seeing counts that included samples they
-- cannot open, or -- as shipped in 5.7.15 -- seeing nothing at all. Neither is
-- much use: there is no point being told about conflicted data outside your own
-- scope of access.
--
-- Denormalised alongside lab_id for the same reason: the card reads these counts
-- in front of a user and must not join back to a table of millions of rows.
ALTER TABLE `s_data_issues`
    ADD COLUMN `facility_id` INT NOT NULL DEFAULT 0 AFTER `lab_id`,
    ADD KEY `idx_facility` (`test_type`, `facility_id`);

-- Existing flags predate the column and would all read as facility 0, which is
-- in nobody's scope. Clearing both tables makes the next nightly run a full
-- rescan that fills the column in properly; the scan is idempotent, so the only
-- cost is one full pass.
DELETE FROM `s_data_issues`;
DELETE FROM `s_data_issues_scan`;
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
