-- Migration file for version 5.7.56
-- Created on 2026-09-04
--
-- Indexes last_modified_datetime on every request form table, so the STS sync
-- stops reading the whole table.
--
-- The sync selects the rows a lab has touched since a date. On the DRC server
-- that query scanned all 1,462,163 rows of form_vl every time it ran: over one
-- 17.5 hour window the slow log held 2,556 entries, 2,524 of them this query,
-- totalling 15,043 seconds. The table carries indexes on lab_id and facility_id
-- but none on last_modified_datetime, so there was nothing for the date
-- predicate to read and the OR across lab_id/facility_id ruled out either of the
-- other two.
--
-- The index only pays off together with the matching change in
-- App\Services\STS\RequestsService, which stopped wrapping the column in DATE().
-- A function around a column puts every index on it out of reach, so with the
-- wrapper still in place this index would be built and never used. Measured on a
-- 1.6M row copy: 1,410,608 rows examined in 0.96s as it stands, still 1,410,608
-- in 0.93s with the index alone, and 49,488 in 0.05s once both landed. Same
-- answer each time. On the DRC data the gap is wider still -- only 6,749 rows
-- there fall inside a recent-date predicate, against 49,488 in the copy.
--
-- form_hepatitis already carries this index; the others never got it. It is
-- listed anyway so installs converge regardless of which ones they have, and
-- because the runner treats a duplicate key name as benign.
--
-- Building a secondary index on a table this size is an INPLACE rebuild: it
-- takes minutes on form_vl and needs a brief metadata lock at the start and end,
-- so it waits behind any long-running transaction on the table. Run it when the
-- sync is not mid-flight.
--
-- Every statement is written so re-running the migration cannot create duplicates.

ALTER TABLE `form_vl`        ADD INDEX `idx_last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_eid`       ADD INDEX `idx_last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_covid19`   ADD INDEX `idx_last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_tb`        ADD INDEX `idx_last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_cd4`       ADD INDEX `idx_last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_hepatitis` ADD INDEX `idx_last_modified_datetime` (`last_modified_datetime`);
ALTER TABLE `form_generic`   ADD INDEX `idx_last_modified_datetime` (`last_modified_datetime`);

UPDATE `system_config` SET `value` = '5.7.56' WHERE `system_config`.`name` = 'sc_version';
