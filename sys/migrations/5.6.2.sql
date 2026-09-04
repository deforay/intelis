-- Migration file for version 5.6.2
--
-- Retains superseded test results so failures survive a re-test.
--
-- Every form_* table holds exactly one result. When a failed sample is re-tested --
-- routine practice, since labs collect more specimen than one run needs -- the new
-- result overwrites the old one and the failure is gone. The consequences run deeper
-- than lost detail:
--
--   * The Lab Performance Indicators failure rate counts result_status = 5 on the live
--     row. Re-testing resets that status, so every failure that was acted on drops out
--     of the metric. The rate only counts failures nobody has got to yet, and a lab
--     that re-tests promptly looks like a lab that never fails.
--   * form_tb and form_covid19 delete their child rows (tb_tests, covid19_tests) on
--     re-test, and neither child table carries audit triggers, so those values are
--     unrecoverable by any route at all.
--   * Nothing records which instrument, lot or operator produced a failure, so the
--     questions worth asking of failure data cannot be asked.
--
-- What existed before this migration: failed_result_retest_tracker, written by seven
-- copy-pasted endpoints, read by nothing. It kept the result text and status but not
-- the failure reason, tester, platform, instrument, lot or test date, and only fired on
-- the explicit Re-test button -- never on a result edit, import or instrument write.
-- Its rows are carried across below and it is left in place, no longer written to.
--
-- The model here is one row per completed testing attempt on a sample. attempt_number
-- is the re-test signal; no is_retest flag is added, because a boolean would collide
-- with the existing sample_reordered vocabulary, which means something different --
-- that the specimen was re-collected and registered as a NEW request row. Both are
-- recorded so reporting can separate a sample re-tested in-house from one abandoned in
-- favour of a fresh draw, which is the more useful operational distinction.
--
-- Columns worth filtering or grouping on are promoted out of the JSON. attempt_data
-- carries the whole superseded row plus any child result rows, so a module adding a
-- field later needs no migration to have it retained.

CREATE TABLE IF NOT EXISTS `test_result_attempts` (
  `attempt_id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Identity of the superseded attempt.
  `test_type`              VARCHAR(32)  NOT NULL,
  `form_table`             VARCHAR(64)  NOT NULL,
  `record_id`              BIGINT       NOT NULL,
  `attempt_number`         INT          NOT NULL DEFAULT 1,

  -- What replaced it: retest | result-edit | import | interface | api | bulk-status.
  `superseded_by`          VARCHAR(32)  NOT NULL DEFAULT 'retest',

  -- Denormalised so an attempt stays readable after the live row moves on.
  `lab_id`                 INT          NULL,
  `facility_id`            INT          NULL,
  `sample_code`            VARCHAR(256) NULL,
  `remote_sample_code`     VARCHAR(256) NULL,
  `batch_id`               VARCHAR(256) NULL,

  -- The result as it stood. result_failed/result_rejected are stored rather than
  -- derived so reports never have to repeat the status-vocabulary logic, which is
  -- inconsistent across the codebase.
  `result`                 TEXT         NULL,
  `result_status`          INT          NULL,
  `result_failed`          TINYINT(1)   NOT NULL DEFAULT 0,
  `result_rejected`        TINYINT(1)   NOT NULL DEFAULT 0,
  `reason_for_failure`     VARCHAR(256) NULL,

  -- Failure-analysis axes: which run, whose hands, which machine, which kit.
  `sample_tested_datetime` DATETIME     NULL,
  `tested_by`              VARCHAR(256) NULL,
  `test_platform`          VARCHAR(256) NULL,
  `instrument_id`          VARCHAR(256) NULL,
  `lot_number`             VARCHAR(256) NULL,
  `lot_expiration_date`    DATE         NULL,

  -- Re-collection flag as it stood at this attempt (see note above).
  `sample_reordered`       VARCHAR(3)   NULL,

  -- Full snapshot of the superseded row and its child result rows.
  `attempt_data`           JSON         NULL,
  `change_reason`          TEXT         NULL,

  `created_datetime`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`             VARCHAR(256) NULL,

  PRIMARY KEY (`attempt_id`),
  -- Makes archiving idempotent: re-running an archive for the same attempt cannot
  -- duplicate it, which matters because several write paths can fire in one request.
  UNIQUE KEY `u_attempt`  (`form_table`, `record_id`, `attempt_number`),
  KEY `k_record`   (`form_table`, `record_id`),
  KEY `k_failure`  (`test_type`, `result_failed`, `sample_tested_datetime`),
  KEY `k_lab`      (`lab_id`, `sample_tested_datetime`),
  KEY `k_platform` (`test_platform`, `result_failed`),
  KEY `k_lot`      (`lot_number`, `result_failed`),
  KEY `k_tester`   (`tested_by`, `result_failed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ----------------------------------------------------------------------------
-- Carry across failed_result_retest_tracker.
--
-- Its test_type column cannot be trusted: the TB and Hepatitis endpoints both wrote
-- 'vl' verbatim, so those modules' history is filed under the wrong test type. Rather
-- than believe the column, each row is matched to the form table that actually owns
-- both its primary key AND its sample_code. A tracker row whose pid/sample_code pair
-- matches no live row is skipped rather than guessed at.
--
-- Ambiguity is possible in principle (the same id and sample_code existing in two
-- modules) but is resolved deterministically: the statements run in a fixed order and
-- the unique key means the first module to claim a (form_table, record_id, attempt)
-- triple keeps it. Cross-module collisions land on different form_table values anyway,
-- so both copies are retained and neither is lost.
--
-- attempt_number is assigned per record in chronological order. These historical rows
-- carry only what the tracker held; the fields it never captured stay NULL, which reads
-- correctly as "not recorded" rather than "none".
-- ----------------------------------------------------------------------------

INSERT IGNORE INTO `test_result_attempts`
  (`test_type`, `form_table`, `record_id`, `attempt_number`, `superseded_by`,
   `facility_id`, `sample_code`, `remote_sample_code`, `batch_id`,
   `result`, `result_status`, `result_failed`, `result_rejected`,
   `created_datetime`, `created_by`)
SELECT `test_type`, `form_table`, `record_id`, `attempt_number`, 'retest',
       `facility_id`, `sample_code`, `remote_sample_code`, `batch_id`,
       `result`, `result_status`,
       CASE WHEN `result_status` = 5 THEN 1 ELSE 0 END,
       CASE WHEN `result_status` = 4 THEN 1 ELSE 0 END,
       `updated_datetime`, `updated_by`
FROM (
    SELECT m.*,
           ROW_NUMBER() OVER (
               PARTITION BY m.`form_table`, m.`record_id`
               ORDER BY m.`updated_datetime`, m.`frrt_id`
           ) AS `attempt_number`
    FROM (
        SELECT t.`frrt_id`, 'vl' AS `test_type`, 'form_vl' AS `form_table`,
               f.`vl_sample_id` AS `record_id`, t.`facility_id`, t.`sample_code`,
               t.`remote_sample_code`, t.`batch_id`, t.`result`, t.`result_status`,
               t.`updated_datetime`, t.`updated_by`
        FROM `failed_result_retest_tracker` t
        JOIN `form_vl` f
          ON f.`vl_sample_id` = t.`test_type_pid`
         AND f.`sample_code` <=> t.`sample_code`

        UNION ALL
        SELECT t.`frrt_id`, 'eid', 'form_eid', f.`eid_id`, t.`facility_id`,
               t.`sample_code`, t.`remote_sample_code`, t.`batch_id`, t.`result`,
               t.`result_status`, t.`updated_datetime`, t.`updated_by`
        FROM `failed_result_retest_tracker` t
        JOIN `form_eid` f
          ON f.`eid_id` = t.`test_type_pid`
         AND f.`sample_code` <=> t.`sample_code`

        UNION ALL
        SELECT t.`frrt_id`, 'tb', 'form_tb', f.`tb_id`, t.`facility_id`,
               t.`sample_code`, t.`remote_sample_code`, t.`batch_id`, t.`result`,
               t.`result_status`, t.`updated_datetime`, t.`updated_by`
        FROM `failed_result_retest_tracker` t
        JOIN `form_tb` f
          ON f.`tb_id` = t.`test_type_pid`
         AND f.`sample_code` <=> t.`sample_code`

        UNION ALL
        SELECT t.`frrt_id`, 'covid19', 'form_covid19', f.`covid19_id`, t.`facility_id`,
               t.`sample_code`, t.`remote_sample_code`, t.`batch_id`, t.`result`,
               t.`result_status`, t.`updated_datetime`, t.`updated_by`
        FROM `failed_result_retest_tracker` t
        JOIN `form_covid19` f
          ON f.`covid19_id` = t.`test_type_pid`
         AND f.`sample_code` <=> t.`sample_code`

        UNION ALL
        SELECT t.`frrt_id`, 'hepatitis', 'form_hepatitis', f.`hepatitis_id`,
               t.`facility_id`, t.`sample_code`, t.`remote_sample_code`, t.`batch_id`,
               t.`result`, t.`result_status`, t.`updated_datetime`, t.`updated_by`
        FROM `failed_result_retest_tracker` t
        JOIN `form_hepatitis` f
          ON f.`hepatitis_id` = t.`test_type_pid`
         AND f.`sample_code` <=> t.`sample_code`

        UNION ALL
        SELECT t.`frrt_id`, 'cd4', 'form_cd4', f.`cd4_id`, t.`facility_id`,
               t.`sample_code`, t.`remote_sample_code`, t.`batch_id`, t.`result`,
               t.`result_status`, t.`updated_datetime`, t.`updated_by`
        FROM `failed_result_retest_tracker` t
        JOIN `form_cd4` f
          ON f.`cd4_id` = t.`test_type_pid`
         AND f.`sample_code` <=> t.`sample_code`

        UNION ALL
        SELECT t.`frrt_id`, 'generic-tests', 'form_generic', f.`sample_id`,
               t.`facility_id`, t.`sample_code`, t.`remote_sample_code`, t.`batch_id`,
               t.`result`, t.`result_status`, t.`updated_datetime`, t.`updated_by`
        FROM `failed_result_retest_tracker` t
        JOIN `form_generic` f
          ON f.`sample_id` = t.`test_type_pid`
         AND f.`sample_code` <=> t.`sample_code`
    ) AS m
) AS ranked;


-- ----------------------------------------------------------------------------
-- Leave the old tracker readable but harmless.
--
-- sample_data was declared NOT NULL with no default and no code ever wrote it. The
-- fleet only tolerated that because sql_mode is forced empty at install; on a strict
-- MySQL or MariaDB the INSERT fails with error 1364 -- and because the endpoints wipe
-- the result BEFORE inserting, with no transaction, the failure was destroyed and
-- nothing was written in its place. Giving the column a default removes that trap for
-- any instance still running an older endpoint mid-upgrade.
-- ----------------------------------------------------------------------------
ALTER TABLE `failed_result_retest_tracker`
  MODIFY `sample_data` MEDIUMTEXT NULL DEFAULT NULL;


-- ----------------------------------------------------------------------------
-- Level the failure-analysis columns across every module.
--
-- These four columns are what turns a retained failure into something answerable --
-- which machine, which kit lot, why -- but they were only ever added where a specific
-- country form happened to need them:
--
--   reason_for_failure   added to form_vl alone, back in 5.0.9
--   instrument_id        never added to form_covid19 by any migration
--   lot_number / expiry  never added to form_tb or form_cd4
--
-- Adding them means the archiving service reads one shape for all seven modules instead
-- of tolerating gaps, and a failure breakdown can cover the whole lab rather than viral
-- load only. Types match the existing form_vl / form_eid definitions so the columns
-- behave identically wherever they already existed.
--
-- form_generic.instrument_id is deliberately NOT added here: 5.5.7 already added it as
-- VARCHAR(128), and repeating it at a different width would either fail or silently
-- disagree with that migration.
--
-- Deployed databases have drifted beyond what the migration history explains -- at least
-- one carries form_covid19.instrument_id that no migration creates -- so a column here
-- may already exist. That is safe: the migration runner treats error 1060 (duplicate
-- column) as benign, exactly so an ALTER can be replayed.
--
-- Every column is nullable with no default: adding them changes no existing row, and
-- historical samples read as "not recorded" rather than being given a fabricated value.
-- Audit triggers pick the columns up automatically, since the upgrade drops and
-- reinstalls them around this migration.
-- ----------------------------------------------------------------------------

ALTER TABLE `form_eid`       ADD COLUMN `reason_for_failure` INT DEFAULT NULL;
ALTER TABLE `form_tb`        ADD COLUMN `reason_for_failure` INT DEFAULT NULL;
ALTER TABLE `form_covid19`   ADD COLUMN `reason_for_failure` INT DEFAULT NULL;
ALTER TABLE `form_hepatitis` ADD COLUMN `reason_for_failure` INT DEFAULT NULL;
ALTER TABLE `form_cd4`       ADD COLUMN `reason_for_failure` INT DEFAULT NULL;
ALTER TABLE `form_generic`   ADD COLUMN `reason_for_failure` INT DEFAULT NULL;

ALTER TABLE `form_covid19`   ADD COLUMN `instrument_id` VARCHAR(50) DEFAULT NULL;

ALTER TABLE `form_tb`        ADD COLUMN `lot_number` TEXT,
                             ADD COLUMN `lot_expiration_date` DATE DEFAULT NULL;
ALTER TABLE `form_cd4`       ADD COLUMN `lot_number` TEXT,
                             ADD COLUMN `lot_expiration_date` DATE DEFAULT NULL;


-- ----------------------------------------------------------------------------
-- A shared failure-reason vocabulary.
--
-- reason_for_failure is an int FK, and the only table it could point at,
-- r_vl_test_failure_reasons, ships with no rows at all. That is why the existing
-- Lab Performance Indicators failure-reason breakdown reports "Not specified" for
-- practically everything: the column has nothing to reference. Rolling the column out
-- to six more modules without fixing that would just spread the same empty breakdown.
--
-- r_test_failure_reasons is shared by every module. test_type NULL means the reason
-- applies to all tests; a value scopes it to one module, so a lab can add
-- module-specific reasons without a table per module.
--
-- Existing r_vl_test_failure_reasons rows are copied across FIRST, preserving
-- failure_id, so any reasons a client added by hand keep working against the values
-- already stored in form_vl.reason_for_failure. The seeded defaults are then added by
-- INSERT IGNORE on failure_code, which takes fresh ids above whatever was copied and
-- cannot collide with client data. The old table is left untouched.
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `r_test_failure_reasons` (
  `failure_id`       INT          NOT NULL AUTO_INCREMENT,
  `failure_code`     VARCHAR(64)  NOT NULL,
  `failure_reason`   VARCHAR(256) DEFAULT NULL,
  `test_type`        VARCHAR(32)  DEFAULT NULL,
  `status`           VARCHAR(32)  DEFAULT 'active',
  `updated_datetime` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `data_sync`        INT          DEFAULT 0,
  PRIMARY KEY (`failure_id`),
  UNIQUE KEY `u_failure_code` (`failure_code`),
  KEY `k_test_type` (`test_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preserve any client-entered VL reasons at their original ids.
INSERT IGNORE INTO `r_test_failure_reasons`
  (`failure_id`, `failure_code`, `failure_reason`, `test_type`, `status`, `updated_datetime`)
SELECT `failure_id`,
       CONCAT('vl-legacy-', `failure_id`),
       `failure_reason`,
       'vl',
       COALESCE(`status`, 'active'),
       COALESCE(`updated_datetime`, CURRENT_TIMESTAMP)
FROM `r_vl_test_failure_reasons`
WHERE `failure_reason` IS NOT NULL AND TRIM(`failure_reason`) <> '';

-- r_generic_test_failure_reasons has drifted on at least one deployed database: the
-- table is present but holds only a subset of its columns, even though 5.2.0 declares
-- all six at creation. That table was therefore created by some route other than the
-- migration history, and 5.2.0's CREATE TABLE carries no IF NOT EXISTS, so a replay
-- raises 1050 and is skipped as benign rather than repairing the shape.
--
-- The SELECT below then fails with 1054, which is not in the benign list, so the whole
-- version halts and every later migration is held back for good. Restoring the shape
-- one column at a time is whack-a-mole -- the first field report fixed the code column
-- by hand and the next run failed on the one after it -- so every column 5.2.0 declares
-- is re-stated here. On a healthy install each is a clean skip through
-- add_column_if_missing(); on a drifted one the table is made whole in a single pass.
--
-- The two NOT NULL columns in 5.2.0 are declared NULL here. Rows already in the table
-- have nothing to backfill with, and a fabricated code would be copied across as a real
-- vocabulary entry. NULL reads correctly as "not recorded", and the WHERE clause below
-- skips those rows. Every write path generates a code, so new rows are unaffected.
--
-- data_sync is not read by this migration. It is included because this table is
-- metadata-synced from the STS, and a copy missing that column silently drops out of
-- the sync rather than failing loudly.
ALTER TABLE `r_generic_test_failure_reasons` ADD COLUMN `test_failure_reason_code` VARCHAR(256) NULL DEFAULT NULL;
ALTER TABLE `r_generic_test_failure_reasons` ADD COLUMN `test_failure_reason` VARCHAR(256) NULL DEFAULT NULL;
ALTER TABLE `r_generic_test_failure_reasons` ADD COLUMN `test_failure_reason_status` VARCHAR(256) NULL DEFAULT NULL;
ALTER TABLE `r_generic_test_failure_reasons` ADD COLUMN `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `r_generic_test_failure_reasons` ADD COLUMN `data_sync` INT NULL DEFAULT NULL;

-- Copy the custom-test reasons too, so this table is the single vocabulary. Their ids
-- are NOT preserved and do not need to be: form_generic had no reason_for_failure
-- column until this migration, so nothing references them yet. They come across scoped
-- to generic-tests and keyed by their existing code.
--
-- generic_test_failure_reason_map stays where it is. It maps a reason to individual
-- custom test types, which is finer-grained than the module-level test_type column
-- here; re-pointing it is part of retiring the old tables, not of creating this one.
INSERT IGNORE INTO `r_test_failure_reasons`
  (`failure_code`, `failure_reason`, `test_type`, `status`, `updated_datetime`)
SELECT `test_failure_reason_code`,
       `test_failure_reason`,
       'generic-tests',
       COALESCE(`test_failure_reason_status`, 'active'),
       COALESCE(`updated_datetime`, CURRENT_TIMESTAMP)
FROM `r_generic_test_failure_reasons`
WHERE `test_failure_reason_code` IS NOT NULL
  AND TRIM(`test_failure_reason_code`) <> '';

-- Baseline reasons common to molecular and serological testing. Deliberately short:
-- a list nobody can navigate gets answered with whatever sits at the top, which is
-- worse for analysis than a small list that gets used honestly.
INSERT IGNORE INTO `r_test_failure_reasons` (`failure_code`, `failure_reason`, `test_type`) VALUES
  ('instrument-error',        'Instrument error',                          NULL),
  ('invalid-control',         'Control failed / out of range',             NULL),
  ('insufficient-volume',     'Insufficient sample volume',                NULL),
  ('sample-quality',          'Poor sample quality',                       NULL),
  ('reagent-kit-issue',       'Reagent or kit issue',                      NULL),
  ('expired-reagent',         'Expired reagent or kit',                    NULL),
  ('power-interruption',      'Power interruption during run',             NULL),
  ('operator-error',          'Operator error',                            NULL),
  ('contamination',           'Suspected contamination',                   NULL),
  ('no-amplification',        'No amplification',                          NULL),
  ('indeterminate-result',    'Indeterminate or inconclusive result',      NULL),
  ('sample-mix-up',           'Sample mix-up or labelling error',          NULL),
  ('other',                   'Other',                                     NULL);


UPDATE `system_config` SET `value` = '5.6.2' WHERE `system_config`.`name` = 'sc_version';
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
