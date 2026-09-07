-- Migration file for version 5.7.62
-- Created on 2026-09-07
--
-- Amit 07-Sep-2026
--
-- Give the API's duplicate check an index it can actually drive.
--
-- TestRequestsService::detectDuplicateSample() filters on facility, lab and a
-- 14-day window around the collection date. Only single-column indexes existed
-- for those, so MySQL index-merged two of them and applied the date range
-- afterwards as a filter. The most selective predicate in the query did no work.
--
-- Measured on vlsm.cbchs.cm, form_vl at 498,776 rows, EXPLAIN ANALYZE of one
-- record:
--
--   Limit: 10                                    (actual time=50.6..50.6)
--     Filter: [the COALESCE/TRIM/LIKE predicates]      rows=1733
--       Intersect rows sorted by row ID                rows=1733
--         Index range scan using facility_id  rows=7830
--         Index range scan using lab_id       rows=28837
--
-- 50.6ms and 36,667 index entries read, per record, to return at most ten rows.
-- The same probe against (facility_id, sample_collection_date) lands on 263.
-- Worst facility in that window is 317 rows and the average is 44.
--
-- lab_id is deliberately not in the index. A facility reports to one lab in
-- practice, so adding it changed the probe not at all: facility + 14 days and
-- facility + lab + 14 days both return exactly 263 rows. A third column that
-- removes no rows is width every write pays for and no read uses.
--
-- Only the three tables whose endpoints call the check. handleDuplicateDetection()
-- is reached from api/v1.1/{vl,eid,tb}/save-request.php and nowhere else, so
-- form_covid19, form_hepatitis, form_cd4 and form_generic would carry an index
-- no query asks for. All three endpoints make facilityId and sampleCollectionDate
-- mandatory, so the index applies on every call rather than most of them.
--
-- The standalone facility_id index on each table becomes a redundant leftmost
-- prefix. It is left in place. Dropping an index is a separate decision from
-- adding one, and bin/duplicate-indexes.php reports prefix redundancy already.
--
-- Each index is named, so add_index_if_missing() recognises it on a replay and
-- does not create a second copy. One action per statement, as in 5.7.61.
--
-- Verified against a 1,600,920-row form_vl copy before writing this. The same
-- probe, before and after:
--
--   before   193ms    Index lookup on facility_id, rows=2335, then a Sort
--   after   1.08ms    Index range scan on the new index, rows=8, no Sort
--
-- The sort disappears because the index already supplies collection date order,
-- which the query asks for. That was not the reason for adding it and is worth
-- knowing anyway.
--
-- Secondary index creation is ONLINE in MySQL 8 (ALGORITHM=INPLACE, LOCK=NONE),
-- so reads and writes continue while it builds. On that 1.6M-row copy it took
-- four seconds.

ALTER TABLE `form_vl` ADD INDEX `idx_facility_collection_date` (`facility_id`, `sample_collection_date`);

ALTER TABLE `form_eid` ADD INDEX `idx_facility_collection_date` (`facility_id`, `sample_collection_date`);

ALTER TABLE `form_tb` ADD INDEX `idx_facility_collection_date` (`facility_id`, `sample_collection_date`);


UPDATE `system_config` SET `value` = '5.7.62' WHERE `system_config`.`name` = 'sc_version';
