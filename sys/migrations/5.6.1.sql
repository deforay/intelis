-- Migration file for version 5.6.1
--
-- Repairs EID results that were stored as raw instrument text instead of an
-- r_eid_results key. Instruments report the qualitative result in whatever language
-- they are configured in, and the importers only recognised the English wording, so a
-- French GeneXpert reporting "HIV-1 NON DETECTE" wrote that phrase straight into
-- form_eid.result. Those samples print with an empty result and are counted by no
-- report, because every display path looks the value up against r_eid_results.
--
-- Safety, in the order it matters:
--
--   * Untested samples are never touched. Both statements require a result that is
--     neither NULL nor blank, which is exactly what an untested or pending sample
--     holds, and neither statement can write a result onto a row that had none.
--   * Only unrecognised text is rewritten. Rows already holding a valid
--     r_eid_results key are excluded, so a correctly imported result is never
--     reinterpreted, and replaying the migration is a no-op: the first run leaves
--     every row it changed holding a valid key, which the guard then excludes.
--   * Multi-target results are left alone. A value carrying more than one target
--     ("HIV-1 Detected | HIV-2 Not Detected") is excluded outright, because deciding
--     which target sets the sample result is a clinical call and not one to make
--     unattended in a migration.
--   * Anything the wording does not settle -- invalid, error, no result, a bare
--     numeric -- matches neither statement and stays exactly as it is, still visible
--     on screen for someone to resolve by hand.
--
-- Comparisons run through utf8mb4_unicode_ci, which ignores both case and accents, so
-- "NON DETECTE", "non detecte" and the accented spelling all collapse onto the same
-- keyword. Hyphens and underscores become spaces first so "non-reactive" matches too.
--
-- data_sync is reset alongside the result so the correction reaches STS. Without it
-- the repaired value would sit locally while the remote copy kept the raw text.

-- Negative first. Every positive keyword is a substring of its negative counterpart
-- ("detecte" inside "non detecte"), so the negative wording has to be claimed before
-- the positive pass runs.
UPDATE `form_eid`
SET `result` = 'negative',
    `data_sync` = 0
WHERE `eid_id` IN (
    SELECT `eid_id` FROM (
        SELECT `eid_id`,
               CONVERT(REPLACE(REPLACE(`result`, '-', ' '), '_', ' ') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS `normalized`
        FROM `form_eid`
        WHERE `result` IS NOT NULL
          AND TRIM(`result`) <> ''
          AND `result` NOT LIKE '%|%'
          AND `result` NOT IN (SELECT `result_id` FROM `r_eid_results`)
    ) AS `candidates`
    WHERE `normalized` LIKE '%not detected%'
       OR `normalized` LIKE '%notdetected%'
       OR `normalized` LIKE '%non detecte%'
       OR `normalized` LIKE '%nondetecte%'
       OR `normalized` LIKE '%non reactive%'
       OR `normalized` LIKE '%non reactif%'
       OR `normalized` LIKE '%negative%'
       OR `normalized` LIKE '%negatif%'
);

-- Positive. The negative keywords are repeated as exclusions rather than relying on
-- the statement above having already claimed those rows, so this stands on its own if
-- the two are ever run out of order.
UPDATE `form_eid`
SET `result` = 'positive',
    `data_sync` = 0
WHERE `eid_id` IN (
    SELECT `eid_id` FROM (
        SELECT `eid_id`,
               CONVERT(REPLACE(REPLACE(`result`, '-', ' '), '_', ' ') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS `normalized`
        FROM `form_eid`
        WHERE `result` IS NOT NULL
          AND TRIM(`result`) <> ''
          AND `result` NOT LIKE '%|%'
          AND `result` NOT IN (SELECT `result_id` FROM `r_eid_results`)
    ) AS `candidates`
    WHERE (
              `normalized` LIKE '%detected%'
           OR `normalized` LIKE '%detecte%'
           OR `normalized` LIKE '%reactive%'
           OR `normalized` LIKE '%reactif%'
           OR `normalized` LIKE '%positive%'
           OR `normalized` LIKE '%positif%'
           OR `normalized` LIKE '%passed%'
          )
      AND `normalized` NOT LIKE '%not detected%'
      AND `normalized` NOT LIKE '%notdetected%'
      AND `normalized` NOT LIKE '%non detecte%'
      AND `normalized` NOT LIKE '%nondetecte%'
      AND `normalized` NOT LIKE '%non reactive%'
      AND `normalized` NOT LIKE '%non reactif%'
      AND `normalized` NOT LIKE '%negative%'
      AND `normalized` NOT LIKE '%negatif%'
);

UPDATE `system_config` SET `value` = '5.6.1' WHERE `system_config`.`name` = 'sc_version';
