-- Migration file for version 5.5.13
--
-- Repair broken province -> district hierarchy links in `geographical_divisions`.
--
-- Some imports left districts with an empty/zero `geo_parent` even though facilities
-- correctly reference them via `facility_district_id` (and the province via
-- `facility_state_id`). The facilities list shows the names fine (it resolves each id
-- independently), but the Edit Facility district dropdown is filtered by
-- `geo_parent = <province>`, so an unparented district can't be displayed or
-- re-selected and would be silently dropped on the next save.
--
-- This re-parents each unparented district to the province its facilities point to,
-- but ONLY when that mapping is unambiguous (every facility referencing the district
-- agrees on a single `facility_state_id`) and the target is an actual province
-- (geo_parent = 0). Districts whose facilities disagree on the province are left
-- untouched for manual review. `updated_datetime` is bumped so the change syncs
-- downstream (STS -> LIS) via the updated_datetime watermark.
--
-- Idempotent: once a district is parented it no longer matches the WHERE clause, so
-- re-running (and fresh installs, where nothing matches) is a no-op.

UPDATE `geographical_divisions` AS d
JOIN (
    SELECT f.facility_district_id AS district_id,
           MIN(f.facility_state_id) AS province_id
    FROM `facility_details` f
    WHERE f.facility_district_id IS NOT NULL AND f.facility_district_id > 0
      AND f.facility_state_id   IS NOT NULL AND f.facility_state_id   > 0
    GROUP BY f.facility_district_id
    HAVING COUNT(DISTINCT f.facility_state_id) = 1
) AS m ON m.district_id = d.geo_id
JOIN `geographical_divisions` AS p ON p.geo_id = m.province_id AND (p.geo_parent = 0 OR p.geo_parent IS NULL)
SET d.geo_parent = m.province_id,
    d.updated_datetime = NOW()
WHERE (d.geo_parent IS NULL OR d.geo_parent = 0)
  AND d.geo_id <> m.province_id;

UPDATE `system_config` SET `value` = '5.5.13' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
