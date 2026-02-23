-- ============================================================
-- Intelis Insights — VL ETL Refresh Stored Procedure
-- ============================================================
-- Run by insights_etl user (SELECT on vlsm, write on intelis_insights).
-- NEVER run by insights_ro.
--
-- Usage:
--   CALL intelis_insights.refresh_vl_aggregates(NULL, NULL, NULL);
--   CALL intelis_insights.refresh_vl_aggregates('2025-01-01', '2025-03-31', NULL);
--
-- Defaults: last 180 days, as_of_date = today.
-- Suppression: Groups with n < 5 are DROPPED (not bucketed).
--   Totals will be lower than raw counts by the sum of suppressed groups.
--   See feasibility.md "Small-n Suppression Behavior" for bucketing alternative.
-- Requires: MySQL 8.0+ (window functions for TAT percentiles).
-- Crash safety: entire procedure is wrapped in a transaction.
-- ============================================================

USE `intelis_insights`;

DELIMITER $$

DROP PROCEDURE IF EXISTS `refresh_vl_aggregates` $$

CREATE PROCEDURE `refresh_vl_aggregates`(
    IN p_start_date DATE,
    IN p_end_date   DATE,
    IN p_as_of_date DATE
)
BEGIN
    DECLARE v_start DATE;
    DECLARE v_end   DATE;
    DECLARE v_as_of DATE;
    DECLARE v_min_n INT DEFAULT 5;
    DECLARE v_run_started DATETIME DEFAULT NOW();

    SET v_end   = COALESCE(p_end_date,   CURDATE());
    SET v_start = COALESCE(p_start_date, DATE_SUB(v_end, INTERVAL 180 DAY));
    SET v_as_of = COALESCE(p_as_of_date, v_end);

    IF v_start > v_end THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'p_start_date cannot be after p_end_date';
    END IF;

    -- Wrap entire refresh in a transaction so a crash mid-run
    -- rolls back all changes rather than leaving partial state.
    START TRANSACTION;

    -- ======================================================
    -- 1) Volume by status (delete-insert for refresh window)
    -- ======================================================
    DELETE FROM `vl_agg_volume_status`
    WHERE `period_start_date` BETWEEN v_start AND v_end;

    INSERT INTO `vl_agg_volume_status`
      (`period_type`,`period_start_date`,`result_status_id`,`result_status_name`,
       `vlsm_country_id`,`n_samples`,`source_max_last_modified`,`suppression_applied`,`refreshed_at`)
    WITH base AS (
        SELECT
            fv.result_status,
            fv.vlsm_country_id,
            fv.last_modified_datetime,
            DATE(fv.sample_collection_date) AS d,
            DATE_SUB(DATE(fv.sample_collection_date), INTERVAL WEEKDAY(DATE(fv.sample_collection_date)) DAY) AS w,
            DATE_FORMAT(fv.sample_collection_date, '%Y-%m-01') AS m
        FROM vlsm.form_vl fv
        WHERE fv.sample_collection_date IS NOT NULL
          AND DATE(fv.sample_collection_date) BETWEEN v_start AND v_end
    ), periodized AS (
        SELECT 'day' AS pt, d AS pd, result_status, vlsm_country_id, last_modified_datetime FROM base
        UNION ALL
        SELECT 'week', w, result_status, vlsm_country_id, last_modified_datetime FROM base
        UNION ALL
        SELECT 'month', m, result_status, vlsm_country_id, last_modified_datetime FROM base
    )
    SELECT
        pt, pd, p.result_status,
        COALESCE(s.status_name, CONCAT('status_', p.result_status)),
        p.vlsm_country_id,
        COUNT(*),
        MAX(p.last_modified_datetime),
        1, NOW()
    FROM periodized p
    LEFT JOIN vlsm.r_sample_status s ON s.status_id = p.result_status
    GROUP BY pt, pd, p.result_status, s.status_name, p.vlsm_country_id
    HAVING COUNT(*) >= v_min_n;


    -- ======================================================
    -- 2) Volume by org unit (delete-insert)
    -- ======================================================
    DELETE FROM `vl_agg_volume_org`
    WHERE `period_start_date` BETWEEN v_start AND v_end;

    INSERT INTO `vl_agg_volume_org`
      (`period_type`,`period_start_date`,`facility_id`,`facility_name`,`lab_id`,`lab_name`,
       `province_id`,`province_name`,`vlsm_country_id`,`n_samples`,
       `source_max_last_modified`,`suppression_applied`,`refreshed_at`)
    WITH base AS (
        SELECT
            fv.facility_id,
            fv.lab_id,
            fv.province_id,
            fv.vlsm_country_id,
            fv.last_modified_datetime,
            fd_fac.facility_name,
            fd_lab.facility_name AS lab_name,
            gd.geo_name AS province_name,
            DATE(fv.sample_collection_date) AS d,
            DATE_SUB(DATE(fv.sample_collection_date), INTERVAL WEEKDAY(DATE(fv.sample_collection_date)) DAY) AS w,
            DATE_FORMAT(fv.sample_collection_date, '%Y-%m-01') AS m
        FROM vlsm.form_vl fv
        LEFT JOIN vlsm.facility_details fd_fac ON fd_fac.facility_id = fv.facility_id
        LEFT JOIN vlsm.facility_details fd_lab ON fd_lab.facility_id = fv.lab_id
        LEFT JOIN vlsm.geographical_divisions gd ON gd.geo_id = fv.province_id
        WHERE fv.sample_collection_date IS NOT NULL
          AND DATE(fv.sample_collection_date) BETWEEN v_start AND v_end
    ), periodized AS (
        SELECT 'day' AS pt, d AS pd, facility_id, facility_name, lab_id, lab_name,
               province_id, province_name, vlsm_country_id, last_modified_datetime FROM base
        UNION ALL
        SELECT 'week', w, facility_id, facility_name, lab_id, lab_name,
               province_id, province_name, vlsm_country_id, last_modified_datetime FROM base
        UNION ALL
        SELECT 'month', m, facility_id, facility_name, lab_id, lab_name,
               province_id, province_name, vlsm_country_id, last_modified_datetime FROM base
    )
    SELECT
        pt, pd, facility_id, MAX(facility_name), lab_id, MAX(lab_name),
        province_id, MAX(province_name), vlsm_country_id,
        COUNT(*),
        MAX(last_modified_datetime),
        1, NOW()
    FROM periodized
    GROUP BY pt, pd, facility_id, lab_id, province_id, vlsm_country_id
    HAVING COUNT(*) >= v_min_n;


    -- ======================================================
    -- 3) Rejections breakdown (delete-insert)
    -- ======================================================
    DELETE FROM `vl_agg_rejections`
    WHERE `period_start_date` BETWEEN v_start AND v_end;

    INSERT INTO `vl_agg_rejections`
      (`period_type`,`period_start_date`,`rejection_reason_id`,`rejection_reason_name`,
       `rejection_type`,`facility_id`,`lab_id`,`vlsm_country_id`,
       `n_samples`,`source_max_last_modified`,`suppression_applied`,`refreshed_at`)
    WITH base AS (
        SELECT
            fv.reason_for_sample_rejection,
            rr.rejection_reason_name,
            rr.rejection_type,
            fv.facility_id,
            fv.lab_id,
            fv.vlsm_country_id,
            fv.last_modified_datetime,
            DATE(COALESCE(fv.rejection_on, fv.sample_collection_date)) AS d,
            DATE_SUB(DATE(COALESCE(fv.rejection_on, fv.sample_collection_date)),
                     INTERVAL WEEKDAY(DATE(COALESCE(fv.rejection_on, fv.sample_collection_date))) DAY) AS w,
            DATE_FORMAT(COALESCE(fv.rejection_on, fv.sample_collection_date), '%Y-%m-01') AS m
        FROM vlsm.form_vl fv
        LEFT JOIN vlsm.r_vl_sample_rejection_reasons rr
          ON rr.rejection_reason_id = fv.reason_for_sample_rejection
        WHERE fv.is_sample_rejected = 'yes'
          AND fv.reason_for_sample_rejection IS NOT NULL
          AND COALESCE(fv.rejection_on, DATE(fv.sample_collection_date)) IS NOT NULL
          AND DATE(COALESCE(fv.rejection_on, fv.sample_collection_date)) BETWEEN v_start AND v_end
    ), periodized AS (
        SELECT 'day' AS pt, d AS pd, reason_for_sample_rejection, rejection_reason_name,
               rejection_type, facility_id, lab_id, vlsm_country_id, last_modified_datetime FROM base
        UNION ALL
        SELECT 'week', w, reason_for_sample_rejection, rejection_reason_name,
               rejection_type, facility_id, lab_id, vlsm_country_id, last_modified_datetime FROM base
        UNION ALL
        SELECT 'month', m, reason_for_sample_rejection, rejection_reason_name,
               rejection_type, facility_id, lab_id, vlsm_country_id, last_modified_datetime FROM base
    )
    SELECT
        pt, pd, reason_for_sample_rejection,
        COALESCE(MAX(rejection_reason_name), CONCAT('reason_', reason_for_sample_rejection)),
        COALESCE(MAX(rejection_type), 'unknown'),
        facility_id, lab_id, vlsm_country_id,
        COUNT(*),
        MAX(last_modified_datetime),
        1, NOW()
    FROM periodized
    GROUP BY pt, pd, reason_for_sample_rejection, facility_id, lab_id, vlsm_country_id
    HAVING COUNT(*) >= v_min_n;


    -- ======================================================
    -- 4) Result category aggregates (delete-insert)
    -- ======================================================
    DELETE FROM `vl_agg_result_category`
    WHERE `period_start_date` BETWEEN v_start AND v_end;

    INSERT INTO `vl_agg_result_category`
      (`period_type`,`period_start_date`,`vl_result_category`,
       `facility_id`,`lab_id`,`vlsm_country_id`,
       `n_samples`,`source_max_last_modified`,`suppression_applied`,`refreshed_at`)
    WITH base AS (
        SELECT
            fv.vl_result_category,
            fv.facility_id,
            fv.lab_id,
            fv.vlsm_country_id,
            fv.last_modified_datetime,
            DATE(fv.sample_collection_date) AS d,
            DATE_SUB(DATE(fv.sample_collection_date), INTERVAL WEEKDAY(DATE(fv.sample_collection_date)) DAY) AS w,
            DATE_FORMAT(fv.sample_collection_date, '%Y-%m-01') AS m
        FROM vlsm.form_vl fv
        WHERE fv.sample_collection_date IS NOT NULL
          AND DATE(fv.sample_collection_date) BETWEEN v_start AND v_end
          AND fv.vl_result_category IS NOT NULL
          AND fv.vl_result_category <> ''
    ), periodized AS (
        SELECT 'day' AS pt, d AS pd, vl_result_category, facility_id, lab_id, vlsm_country_id, last_modified_datetime FROM base
        UNION ALL
        SELECT 'week', w, vl_result_category, facility_id, lab_id, vlsm_country_id, last_modified_datetime FROM base
        UNION ALL
        SELECT 'month', m, vl_result_category, facility_id, lab_id, vlsm_country_id, last_modified_datetime FROM base
    )
    SELECT
        pt, pd, vl_result_category,
        facility_id, lab_id, vlsm_country_id,
        COUNT(*),
        MAX(last_modified_datetime),
        1, NOW()
    FROM periodized
    GROUP BY pt, pd, vl_result_category, facility_id, lab_id, vlsm_country_id
    HAVING COUNT(*) >= v_min_n;


    -- ======================================================
    -- 5) Pending backlog aging (snapshot for as_of_date)
    -- ======================================================
    DELETE FROM `vl_agg_backlog_aging` WHERE `as_of_date` = v_as_of;

    INSERT INTO `vl_agg_backlog_aging`
      (`as_of_date`,`result_status_id`,`result_status_name`,`age_bucket`,
       `facility_id`,`lab_id`,`vlsm_country_id`,
       `n_samples`,`source_max_last_modified`,`suppression_applied`,`refreshed_at`)
    SELECT
        v_as_of,
        fv.result_status,
        COALESCE(ss.status_name, CONCAT('status_', fv.result_status)),
        CASE
            WHEN DATEDIFF(v_as_of, DATE(fv.sample_collection_date)) BETWEEN 0  AND 7   THEN '0-7d'
            WHEN DATEDIFF(v_as_of, DATE(fv.sample_collection_date)) BETWEEN 8  AND 14  THEN '8-14d'
            WHEN DATEDIFF(v_as_of, DATE(fv.sample_collection_date)) BETWEEN 15 AND 30  THEN '15-30d'
            ELSE '30+d'
        END AS age_bucket,
        fv.facility_id,
        fv.lab_id,
        fv.vlsm_country_id,
        COUNT(*),
        MAX(fv.last_modified_datetime),
        1, NOW()
    FROM vlsm.form_vl fv
    LEFT JOIN vlsm.r_sample_status ss ON ss.status_id = fv.result_status
    WHERE fv.sample_collection_date IS NOT NULL
      AND DATE(fv.sample_collection_date) <= v_as_of
      AND fv.result_status IN (6, 8, 9)
    GROUP BY fv.result_status, ss.status_name, age_bucket,
             fv.facility_id, fv.lab_id, fv.vlsm_country_id
    HAVING COUNT(*) >= v_min_n;


    -- ======================================================
    -- 6) TAT percentiles — all metrics via UNION ALL
    -- ======================================================
    -- 13 SUPPORTED pairs. Each row in tat_base has one TAT
    -- observation. Periodized into day/week/month, ranked for
    -- percentile computation, then aggregated.
    --
    -- Negative TAT (end < start) excluded by WHERE clause.
    -- Groups with n < v_min_n suppressed by HAVING.

    DELETE FROM `vl_agg_tat`
    WHERE `period_start_date` BETWEEN v_start AND v_end;

    INSERT INTO `vl_agg_tat`
      (`period_type`,`period_start_date`,`tat_metric`,`tat_start_field`,`tat_end_field`,
       `facility_id`,`lab_id`,`vlsm_country_id`,
       `n_samples`,`avg_hours`,`p50_hours`,`p90_hours`,`p95_hours`,`min_hours`,`max_hours`,
       `source_max_last_modified`,`suppression_applied`,`refreshed_at`)
    WITH tat_base AS (
        -- 1: collection → lab receipt
        SELECT 'tat_collection_to_receipt_lab' AS tat_metric,
               'sample_collection_date' AS sf, 'sample_received_at_lab_datetime' AS ef,
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_collection_date) AS event_day,
               TIMESTAMPDIFF(MINUTE, fv.sample_collection_date, fv.sample_received_at_lab_datetime) / 60.0 AS tat_hours,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_collection_date IS NOT NULL
          AND fv.sample_received_at_lab_datetime IS NOT NULL
          AND fv.sample_received_at_lab_datetime >= fv.sample_collection_date
          AND DATE(fv.sample_collection_date) BETWEEN v_start AND v_end

        UNION ALL
        -- 2: collection → testing
        SELECT 'tat_collection_to_testing', 'sample_collection_date', 'sample_tested_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_collection_date),
               TIMESTAMPDIFF(MINUTE, fv.sample_collection_date, fv.sample_tested_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_collection_date IS NOT NULL AND fv.sample_tested_datetime IS NOT NULL
          AND fv.sample_tested_datetime >= fv.sample_collection_date
          AND DATE(fv.sample_collection_date) BETWEEN v_start AND v_end

        UNION ALL
        -- 3: collection → approval
        SELECT 'tat_collection_to_approval', 'sample_collection_date', 'result_approved_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_collection_date),
               TIMESTAMPDIFF(MINUTE, fv.sample_collection_date, fv.result_approved_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_collection_date IS NOT NULL AND fv.result_approved_datetime IS NOT NULL
          AND fv.result_approved_datetime >= fv.sample_collection_date
          AND DATE(fv.sample_collection_date) BETWEEN v_start AND v_end

        UNION ALL
        -- 4: collection → dispatch
        SELECT 'tat_collection_to_dispatch', 'sample_collection_date', 'result_dispatched_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_collection_date),
               TIMESTAMPDIFF(MINUTE, fv.sample_collection_date, fv.result_dispatched_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_collection_date IS NOT NULL AND fv.result_dispatched_datetime IS NOT NULL
          AND fv.result_dispatched_datetime >= fv.sample_collection_date
          AND DATE(fv.sample_collection_date) BETWEEN v_start AND v_end

        UNION ALL
        -- 5: collection → printed
        SELECT 'tat_collection_to_printed', 'sample_collection_date', 'result_printed_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_collection_date),
               TIMESTAMPDIFF(MINUTE, fv.sample_collection_date, fv.result_printed_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_collection_date IS NOT NULL AND fv.result_printed_datetime IS NOT NULL
          AND fv.result_printed_datetime >= fv.sample_collection_date
          AND DATE(fv.sample_collection_date) BETWEEN v_start AND v_end

        UNION ALL
        -- 6: dispatch → lab receipt
        SELECT 'tat_dispatch_to_receipt_lab', 'sample_dispatched_datetime', 'sample_received_at_lab_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_dispatched_datetime),
               TIMESTAMPDIFF(MINUTE, fv.sample_dispatched_datetime, fv.sample_received_at_lab_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_dispatched_datetime IS NOT NULL AND fv.sample_received_at_lab_datetime IS NOT NULL
          AND fv.sample_received_at_lab_datetime >= fv.sample_dispatched_datetime
          AND DATE(fv.sample_dispatched_datetime) BETWEEN v_start AND v_end

        UNION ALL
        -- 7: lab receipt → testing
        SELECT 'tat_receipt_lab_to_testing', 'sample_received_at_lab_datetime', 'sample_tested_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_received_at_lab_datetime),
               TIMESTAMPDIFF(MINUTE, fv.sample_received_at_lab_datetime, fv.sample_tested_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_received_at_lab_datetime IS NOT NULL AND fv.sample_tested_datetime IS NOT NULL
          AND fv.sample_tested_datetime >= fv.sample_received_at_lab_datetime
          AND DATE(fv.sample_received_at_lab_datetime) BETWEEN v_start AND v_end

        UNION ALL
        -- 8: lab receipt → approval
        SELECT 'tat_receipt_lab_to_approval', 'sample_received_at_lab_datetime', 'result_approved_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_received_at_lab_datetime),
               TIMESTAMPDIFF(MINUTE, fv.sample_received_at_lab_datetime, fv.result_approved_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_received_at_lab_datetime IS NOT NULL AND fv.result_approved_datetime IS NOT NULL
          AND fv.result_approved_datetime >= fv.sample_received_at_lab_datetime
          AND DATE(fv.sample_received_at_lab_datetime) BETWEEN v_start AND v_end

        UNION ALL
        -- 9: lab receipt → dispatch
        SELECT 'tat_receipt_lab_to_dispatch', 'sample_received_at_lab_datetime', 'result_dispatched_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_received_at_lab_datetime),
               TIMESTAMPDIFF(MINUTE, fv.sample_received_at_lab_datetime, fv.result_dispatched_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_received_at_lab_datetime IS NOT NULL AND fv.result_dispatched_datetime IS NOT NULL
          AND fv.result_dispatched_datetime >= fv.sample_received_at_lab_datetime
          AND DATE(fv.sample_received_at_lab_datetime) BETWEEN v_start AND v_end

        UNION ALL
        -- 10: testing → approval
        SELECT 'tat_testing_to_approval', 'sample_tested_datetime', 'result_approved_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_tested_datetime),
               TIMESTAMPDIFF(MINUTE, fv.sample_tested_datetime, fv.result_approved_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_tested_datetime IS NOT NULL AND fv.result_approved_datetime IS NOT NULL
          AND fv.result_approved_datetime >= fv.sample_tested_datetime
          AND DATE(fv.sample_tested_datetime) BETWEEN v_start AND v_end

        UNION ALL
        -- 11: testing → printed
        SELECT 'tat_testing_to_printed', 'sample_tested_datetime', 'result_printed_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_tested_datetime),
               TIMESTAMPDIFF(MINUTE, fv.sample_tested_datetime, fv.result_printed_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_tested_datetime IS NOT NULL AND fv.result_printed_datetime IS NOT NULL
          AND fv.result_printed_datetime >= fv.sample_tested_datetime
          AND DATE(fv.sample_tested_datetime) BETWEEN v_start AND v_end

        UNION ALL
        -- 12: testing → dispatch
        SELECT 'tat_testing_to_dispatch', 'sample_tested_datetime', 'result_dispatched_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.sample_tested_datetime),
               TIMESTAMPDIFF(MINUTE, fv.sample_tested_datetime, fv.result_dispatched_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.sample_tested_datetime IS NOT NULL AND fv.result_dispatched_datetime IS NOT NULL
          AND fv.result_dispatched_datetime >= fv.sample_tested_datetime
          AND DATE(fv.sample_tested_datetime) BETWEEN v_start AND v_end

        UNION ALL
        -- 13: approval → dispatch
        SELECT 'tat_approval_to_dispatch', 'result_approved_datetime', 'result_dispatched_datetime',
               fv.facility_id, fv.lab_id, fv.vlsm_country_id,
               DATE(fv.result_approved_datetime),
               TIMESTAMPDIFF(MINUTE, fv.result_approved_datetime, fv.result_dispatched_datetime) / 60.0,
               fv.last_modified_datetime
        FROM vlsm.form_vl fv
        WHERE fv.result_approved_datetime IS NOT NULL AND fv.result_dispatched_datetime IS NOT NULL
          AND fv.result_dispatched_datetime >= fv.result_approved_datetime
          AND DATE(fv.result_approved_datetime) BETWEEN v_start AND v_end
    ),
    -- Periodize into day/week/month
    tat_periodized AS (
        SELECT 'day' AS pt, event_day AS pd, tat_metric, sf, ef,
               facility_id, lab_id, vlsm_country_id, tat_hours, last_modified_datetime
        FROM tat_base
        UNION ALL
        SELECT 'week', DATE_SUB(event_day, INTERVAL WEEKDAY(event_day) DAY),
               tat_metric, sf, ef, facility_id, lab_id, vlsm_country_id, tat_hours, last_modified_datetime
        FROM tat_base
        UNION ALL
        SELECT 'month', DATE_FORMAT(event_day, '%Y-%m-01'),
               tat_metric, sf, ef, facility_id, lab_id, vlsm_country_id, tat_hours, last_modified_datetime
        FROM tat_base
    ),
    -- Rank for percentile computation
    ranked AS (
        SELECT
            pt, pd, tat_metric, sf, ef,
            facility_id, lab_id, vlsm_country_id,
            tat_hours, last_modified_datetime,
            ROW_NUMBER() OVER (
                PARTITION BY pt, pd, tat_metric, facility_id, lab_id, vlsm_country_id
                ORDER BY tat_hours
            ) AS rn,
            COUNT(*) OVER (
                PARTITION BY pt, pd, tat_metric, facility_id, lab_id, vlsm_country_id
            ) AS n
        FROM tat_periodized
    )
    SELECT
        pt, pd, tat_metric, MAX(sf), MAX(ef),
        facility_id, lab_id, vlsm_country_id,
        MAX(n),
        ROUND(AVG(tat_hours), 2),
        ROUND(MAX(CASE WHEN rn = CEIL(n * 0.50) THEN tat_hours END), 2),
        ROUND(MAX(CASE WHEN rn = CEIL(n * 0.90) THEN tat_hours END), 2),
        ROUND(MAX(CASE WHEN rn = CEIL(n * 0.95) THEN tat_hours END), 2),
        ROUND(MIN(tat_hours), 2),
        ROUND(MAX(tat_hours), 2),
        MAX(last_modified_datetime),
        1, NOW()
    FROM ranked
    GROUP BY pt, pd, tat_metric, facility_id, lab_id, vlsm_country_id
    HAVING MAX(n) >= v_min_n;


    -- ======================================================
    -- 7) Reference tables — truncate and reload
    -- ======================================================
    -- These are small lookup tables. Full reload is simplest
    -- and avoids stale data from renames or deactivations.

    -- DELETE instead of TRUNCATE — TRUNCATE requires DROP privilege
    -- which insights_etl intentionally does not have.

    DELETE FROM `ref_sample_status`;
    INSERT INTO `ref_sample_status` (`status_id`, `status_name`, `status`)
    SELECT `status_id`, `status_name`, `status`
    FROM vlsm.`r_sample_status`;

    DELETE FROM `ref_facilities`;
    INSERT INTO `ref_facilities`
      (`facility_id`, `facility_name`, `facility_code`, `vlsm_instance_id`,
       `facility_state_id`, `facility_district_id`, `facility_state`, `facility_district`,
       `facility_type`, `status`, `test_type`)
    SELECT
      `facility_id`, `facility_name`, `facility_code`, `vlsm_instance_id`,
      `facility_state_id`, `facility_district_id`, `facility_state`, `facility_district`,
      `facility_type`, `status`, `test_type`
    FROM vlsm.`facility_details`;

    DELETE FROM `ref_geographical_divisions`;
    INSERT INTO `ref_geographical_divisions`
      (`geo_id`, `geo_name`, `geo_code`, `geo_parent`, `geo_status`)
    SELECT `geo_id`, `geo_name`, `geo_code`, `geo_parent`, `geo_status`
    FROM vlsm.`geographical_divisions`;

    DELETE FROM `ref_vl_rejection_reasons`;
    INSERT INTO `ref_vl_rejection_reasons`
      (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`,
       `rejection_reason_status`, `rejection_reason_code`)
    SELECT `rejection_reason_id`, `rejection_reason_name`, `rejection_type`,
           `rejection_reason_status`, `rejection_reason_code`
    FROM vlsm.`r_vl_sample_rejection_reasons`;

    DELETE FROM `ref_vl_test_reasons`;
    INSERT INTO `ref_vl_test_reasons`
      (`test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`)
    SELECT `test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`
    FROM vlsm.`r_vl_test_reasons`;

    DELETE FROM `ref_vl_sample_types`;
    INSERT INTO `ref_vl_sample_types` (`sample_id`, `sample_name`, `status`)
    SELECT `sample_id`, `sample_name`, `status`
    FROM vlsm.`r_vl_sample_type`;


    -- ======================================================
    -- 8) Log this refresh
    -- ======================================================
    INSERT INTO `etl_refresh_log`
      (`table_name`, `refresh_type`, `date_range_start`, `date_range_end`,
       `started_at`, `completed_at`, `status`)
    VALUES
      ('vl_agg_volume_status',    'incremental', v_start, v_end, v_run_started, NOW(), 'completed'),
      ('vl_agg_volume_org',       'incremental', v_start, v_end, v_run_started, NOW(), 'completed'),
      ('vl_agg_rejections',       'incremental', v_start, v_end, v_run_started, NOW(), 'completed'),
      ('vl_agg_result_category',  'incremental', v_start, v_end, v_run_started, NOW(), 'completed'),
      ('vl_agg_backlog_aging',    'snapshot',    v_as_of, v_as_of, v_run_started, NOW(), 'completed'),
      ('vl_agg_tat',              'incremental', v_start, v_end, v_run_started, NOW(), 'completed'),
      ('ref_sample_status',       'full',        NULL,    NULL,  v_run_started, NOW(), 'completed'),
      ('ref_facilities',          'full',        NULL,    NULL,  v_run_started, NOW(), 'completed'),
      ('ref_geographical_divisions','full',      NULL,    NULL,  v_run_started, NOW(), 'completed'),
      ('ref_vl_rejection_reasons','full',        NULL,    NULL,  v_run_started, NOW(), 'completed'),
      ('ref_vl_test_reasons',     'full',        NULL,    NULL,  v_run_started, NOW(), 'completed'),
      ('ref_vl_sample_types',     'full',        NULL,    NULL,  v_run_started, NOW(), 'completed');

    COMMIT;

END $$

DELIMITER ;

-- ============================================================
-- Usage Examples:
-- ============================================================
-- Full refresh (last 180 days):
--   CALL intelis_insights.refresh_vl_aggregates(NULL, NULL, NULL);
--
-- Custom date range:
--   CALL intelis_insights.refresh_vl_aggregates('2025-01-01', '2025-06-30', NULL);
--
-- Initial backfill (1 year):
--   CALL intelis_insights.refresh_vl_aggregates(DATE_SUB(CURDATE(), INTERVAL 365 DAY), CURDATE(), NULL);
