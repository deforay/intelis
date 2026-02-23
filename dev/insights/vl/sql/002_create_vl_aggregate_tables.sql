-- ============================================================
-- Intelis Insights — VL Aggregate Tables (Merged Design)
-- ============================================================
-- Merges best of both designs:
--   - period_type ENUM for day/week/month in same table
--   - source_max_last_modified for incremental refresh tracking
--   - suppression_applied flag for audit
--   - Denormalized status/facility/rejection names (insights_ro
--     cannot join to operational tables)
--   - tat_start_field/tat_end_field for self-documentation
--   - ETL refresh log
--   - 4-bucket backlog aging
--   - p50/p90/p95/min/max for TAT
--   - Reference/lookup tables (status, facilities, geo, rejection
--     reasons, test reasons, specimen types) so insights_ro can
--     resolve IDs without accessing operational tables
-- ============================================================

USE `intelis_insights`;

-- ----------------------------------------------------------
-- 1. VL Volume by Status
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vl_agg_volume_status` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_type`             ENUM('day','week','month') NOT NULL,
  `period_start_date`       DATE NOT NULL,
  `result_status_id`        INT NOT NULL                 COMMENT 'FK r_sample_status.status_id',
  `result_status_name`      VARCHAR(255) NOT NULL         COMMENT 'Denormalized status label',
  `vlsm_country_id`         INT DEFAULT NULL,
  `n_samples`               BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `source_max_last_modified` DATETIME DEFAULT NULL        COMMENT 'Max last_modified_datetime in source rows',
  `suppression_applied`     TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = groups < 5 excluded',
  `refreshed_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vol_status` (`period_type`, `period_start_date`, `result_status_id`, `vlsm_country_id`),
  KEY `idx_vol_date` (`period_start_date`, `period_type`)
) ENGINE=InnoDB COMMENT='Aggregate: VL sample counts by status and time';


-- ----------------------------------------------------------
-- 2. VL Volume by Organization Unit
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vl_agg_volume_org` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_type`             ENUM('day','week','month') NOT NULL,
  `period_start_date`       DATE NOT NULL,
  `facility_id`             INT DEFAULT NULL,
  `facility_name`           VARCHAR(255) DEFAULT NULL     COMMENT 'Denormalized from facility_details',
  `lab_id`                  INT DEFAULT NULL,
  `lab_name`                VARCHAR(255) DEFAULT NULL     COMMENT 'Denormalized from facility_details',
  `province_id`             INT DEFAULT NULL,
  `province_name`           VARCHAR(255) DEFAULT NULL     COMMENT 'Denormalized from geographical_divisions',
  `vlsm_country_id`         INT DEFAULT NULL,
  `n_samples`               BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `source_max_last_modified` DATETIME DEFAULT NULL,
  `suppression_applied`     TINYINT(1) NOT NULL DEFAULT 1,
  `refreshed_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vol_org` (`period_type`, `period_start_date`, `facility_id`, `lab_id`, `province_id`, `vlsm_country_id`),
  KEY `idx_org_date` (`period_start_date`, `period_type`),
  KEY `idx_org_facility` (`facility_id`),
  KEY `idx_org_lab` (`lab_id`)
) ENGINE=InnoDB COMMENT='Aggregate: VL sample counts by org unit';


-- ----------------------------------------------------------
-- 3. VL Rejections Breakdown
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vl_agg_rejections` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_type`             ENUM('day','week','month') NOT NULL,
  `period_start_date`       DATE NOT NULL,
  `rejection_reason_id`     INT NOT NULL,
  `rejection_reason_name`   VARCHAR(255) NOT NULL         COMMENT 'Denormalized from r_vl_sample_rejection_reasons',
  `rejection_type`          VARCHAR(255) NOT NULL DEFAULT 'general' COMMENT 'general/whole blood/plasma/dbs/testing',
  `facility_id`             INT DEFAULT NULL,
  `lab_id`                  INT DEFAULT NULL,
  `vlsm_country_id`         INT DEFAULT NULL,
  `n_samples`               BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `source_max_last_modified` DATETIME DEFAULT NULL,
  `suppression_applied`     TINYINT(1) NOT NULL DEFAULT 1,
  `refreshed_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rej` (`period_type`, `period_start_date`, `rejection_reason_id`, `facility_id`, `lab_id`, `vlsm_country_id`),
  KEY `idx_rej_date` (`period_start_date`, `period_type`),
  KEY `idx_rej_reason` (`rejection_reason_id`)
) ENGINE=InnoDB COMMENT='Aggregate: VL rejection counts by reason and org unit';


-- ----------------------------------------------------------
-- 4. VL Result Category Aggregates
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vl_agg_result_category` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_type`             ENUM('day','week','month') NOT NULL,
  `period_start_date`       DATE NOT NULL,
  `vl_result_category`      VARCHAR(32) NOT NULL          COMMENT 'suppressed|not suppressed|rejected|invalid|failed',
  `facility_id`             INT DEFAULT NULL,
  `lab_id`                  INT DEFAULT NULL,
  `vlsm_country_id`         INT DEFAULT NULL,
  `n_samples`               BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `source_max_last_modified` DATETIME DEFAULT NULL,
  `suppression_applied`     TINYINT(1) NOT NULL DEFAULT 1,
  `refreshed_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rescat` (`period_type`, `period_start_date`, `vl_result_category`, `facility_id`, `lab_id`, `vlsm_country_id`),
  KEY `idx_rescat_date` (`period_start_date`, `period_type`),
  KEY `idx_rescat_cat` (`vl_result_category`)
) ENGINE=InnoDB COMMENT='Aggregate: VL result category counts (suppressed, not suppressed, etc.)';


-- ----------------------------------------------------------
-- 5. VL Pending Backlog Aging (Snapshot)
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vl_agg_backlog_aging` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `as_of_date`              DATE NOT NULL                 COMMENT 'Date this snapshot was taken',
  `result_status_id`        INT NOT NULL,
  `result_status_name`      VARCHAR(255) NOT NULL,
  `age_bucket`              ENUM('0-7d','8-14d','15-30d','30+d') NOT NULL,
  `facility_id`             INT DEFAULT NULL,
  `lab_id`                  INT DEFAULT NULL,
  `vlsm_country_id`         INT DEFAULT NULL,
  `n_samples`               BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `source_max_last_modified` DATETIME DEFAULT NULL,
  `suppression_applied`     TINYINT(1) NOT NULL DEFAULT 1,
  `refreshed_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backlog` (`as_of_date`, `result_status_id`, `age_bucket`, `facility_id`, `lab_id`, `vlsm_country_id`),
  KEY `idx_backlog_date` (`as_of_date`),
  KEY `idx_backlog_bucket` (`age_bucket`)
) ENGINE=InnoDB COMMENT='Snapshot: VL pending backlog by aging bucket';


-- ----------------------------------------------------------
-- 6. VL TAT Percentiles (Multi-Definition)
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vl_agg_tat` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_type`             ENUM('day','week','month') NOT NULL,
  `period_start_date`       DATE NOT NULL,
  `tat_metric`              VARCHAR(96) NOT NULL          COMMENT 'Canonical TAT metric name',
  `tat_start_field`         VARCHAR(64) NOT NULL          COMMENT 'Start timestamp column name (self-documenting)',
  `tat_end_field`           VARCHAR(64) NOT NULL          COMMENT 'End timestamp column name',
  `facility_id`             INT DEFAULT NULL,
  `lab_id`                  INT DEFAULT NULL,
  `vlsm_country_id`         INT DEFAULT NULL,
  `n_samples`               BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Rows with valid start+end (after suppression)',
  `avg_hours`               DECIMAL(12,2) DEFAULT NULL,
  `p50_hours`               DECIMAL(12,2) DEFAULT NULL    COMMENT 'Median',
  `p90_hours`               DECIMAL(12,2) DEFAULT NULL,
  `p95_hours`               DECIMAL(12,2) DEFAULT NULL,
  `min_hours`               DECIMAL(12,2) DEFAULT NULL,
  `max_hours`               DECIMAL(12,2) DEFAULT NULL,
  `source_max_last_modified` DATETIME DEFAULT NULL,
  `suppression_applied`     TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = groups < 5 excluded',
  `refreshed_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tat` (`period_type`, `period_start_date`, `tat_metric`, `facility_id`, `lab_id`, `vlsm_country_id`),
  KEY `idx_tat_date` (`period_start_date`, `period_type`),
  KEY `idx_tat_metric` (`tat_metric`),
  KEY `idx_tat_lab` (`lab_id`)
) ENGINE=InnoDB COMMENT='Aggregate: VL TAT percentiles by metric, period, and org unit';


-- ----------------------------------------------------------
-- 7. Reference: Sample Status
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ref_sample_status` (
  `status_id`   INT NOT NULL,
  `status_name` VARCHAR(255) DEFAULT NULL,
  `status`      VARCHAR(45) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB COMMENT='Reference: sample lifecycle statuses (from r_sample_status)';


-- ----------------------------------------------------------
-- 8. Reference: Facilities
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ref_facilities` (
  `facility_id`         INT NOT NULL,
  `facility_name`       VARCHAR(255) DEFAULT NULL,
  `facility_code`       VARCHAR(255) DEFAULT NULL,
  `vlsm_instance_id`    VARCHAR(255) DEFAULT NULL,
  `facility_state_id`   INT DEFAULT NULL,
  `facility_district_id` INT DEFAULT NULL,
  `facility_state`      VARCHAR(255) DEFAULT NULL    COMMENT 'Denormalized state name',
  `facility_district`   VARCHAR(255) DEFAULT NULL    COMMENT 'Denormalized district name',
  `facility_type`       INT DEFAULT NULL,
  `status`              VARCHAR(255) DEFAULT NULL,
  `test_type`           VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`facility_id`),
  KEY `idx_ref_fac_state` (`facility_state_id`),
  KEY `idx_ref_fac_district` (`facility_district_id`)
) ENGINE=InnoDB COMMENT='Reference: facilities — safe columns only, no PII (no contact/email/phone/address)';


-- ----------------------------------------------------------
-- 9. Reference: Geographical Divisions
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ref_geographical_divisions` (
  `geo_id`     INT NOT NULL,
  `geo_name`   VARCHAR(256) DEFAULT NULL,
  `geo_code`   VARCHAR(256) DEFAULT NULL,
  `geo_parent` VARCHAR(256) NOT NULL DEFAULT '0',
  `geo_status` VARCHAR(256) DEFAULT NULL,
  PRIMARY KEY (`geo_id`),
  KEY `idx_ref_geo_parent` (`geo_parent`)
) ENGINE=InnoDB COMMENT='Reference: geographical hierarchy (province/district/etc.)';


-- ----------------------------------------------------------
-- 10. Reference: VL Rejection Reasons
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ref_vl_rejection_reasons` (
  `rejection_reason_id`     INT NOT NULL,
  `rejection_reason_name`   VARCHAR(255) DEFAULT NULL,
  `rejection_type`          VARCHAR(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` VARCHAR(255) DEFAULT NULL,
  `rejection_reason_code`   VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB COMMENT='Reference: VL sample rejection reasons (from r_vl_sample_rejection_reasons)';


-- ----------------------------------------------------------
-- 11. Reference: VL Test Reasons
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ref_vl_test_reasons` (
  `test_reason_id`     INT NOT NULL,
  `test_reason_name`   VARCHAR(255) DEFAULT NULL,
  `parent_reason`      INT DEFAULT 0,
  `test_reason_status` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`test_reason_id`),
  KEY `idx_ref_reason_parent` (`parent_reason`)
) ENGINE=InnoDB COMMENT='Reference: VL test/request reasons (from r_vl_test_reasons)';


-- ----------------------------------------------------------
-- 12. Reference: VL Sample Types
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ref_vl_sample_types` (
  `sample_id`   INT NOT NULL,
  `sample_name` VARCHAR(255) DEFAULT NULL,
  `status`      VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB COMMENT='Reference: VL specimen types (from r_vl_sample_type)';


-- ----------------------------------------------------------
-- 13. ETL Refresh Log
-- ----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `etl_refresh_log` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name`        VARCHAR(128) NOT NULL,
  `refresh_type`      VARCHAR(32) NOT NULL               COMMENT 'incremental|full|snapshot',
  `date_range_start`  DATE DEFAULT NULL,
  `date_range_end`    DATE DEFAULT NULL,
  `rows_affected`     BIGINT UNSIGNED DEFAULT 0,
  `started_at`        DATETIME NOT NULL,
  `completed_at`      DATETIME DEFAULT NULL,
  `status`            VARCHAR(16) NOT NULL DEFAULT 'running' COMMENT 'running|completed|failed',
  `error_message`     TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_refresh_table` (`table_name`),
  KEY `idx_refresh_status` (`status`)
) ENGINE=InnoDB COMMENT='ETL refresh audit log';
