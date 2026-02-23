# VL Analytics — Privacy & Privileges

## 1. Analytics Read-Only User

`insights_ro` has SELECT access ONLY on `intelis_insights`. Zero access to operational tables.

```sql
CREATE USER IF NOT EXISTS 'insights_ro'@'%'
  IDENTIFIED BY 'CHANGE_ME_IN_PRODUCTION';

REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'insights_ro'@'%';
GRANT SELECT ON `intelis_insights`.* TO 'insights_ro'@'%';
FLUSH PRIVILEGES;
```

### Verification

```sql
SHOW GRANTS FOR 'insights_ro'@'%';
-- Expected: USAGE on *.* + SELECT on intelis_insights.*
```

## 2. ETL User

```sql
CREATE USER IF NOT EXISTS 'insights_etl'@'localhost'
  IDENTIFIED BY 'CHANGE_ME_ETL_PRODUCTION';

-- SELECT on operational tables needed by ETL
GRANT SELECT ON `vlsm`.`form_vl` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`facility_details` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`r_sample_status` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`r_vl_sample_rejection_reasons` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`r_vl_test_reasons` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`r_vl_sample_type` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`geographical_divisions` TO 'insights_etl'@'localhost';

-- Write access on analytics schema
GRANT SELECT, INSERT, UPDATE, DELETE ON `intelis_insights`.* TO 'insights_etl'@'localhost';
GRANT EXECUTE ON `intelis_insights`.* TO 'insights_etl'@'localhost';
FLUSH PRIVILEGES;
```

## 3. PII Audit Query

Scan `intelis_insights` columns for PII naming patterns. Run periodically.

```sql
SELECT
  c.TABLE_NAME,
  c.COLUMN_NAME,
  c.DATA_TYPE,
  CASE
    WHEN LOWER(c.COLUMN_NAME) REGEXP
      'patient|first_name|last_name|middle_name|phone|mobile|address|email|dob|birth|national_id|insurance|anc_no|art_no|sample_code|unique_id|system_patient|clinician|physician|focal_person|contact_person|responsible_person|consent'
      THEN 'LIKELY PII - INVESTIGATE'
    WHEN LOWER(c.COLUMN_NAME) REGEXP 'name|person|phone|code|number'
      THEN 'POSSIBLE PII - REVIEW'
    ELSE 'OK'
  END AS pii_risk_flag
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_SCHEMA = 'intelis_insights'
ORDER BY
  FIELD(
    CASE
      WHEN LOWER(c.COLUMN_NAME) REGEXP 'patient|first_name|last_name|phone|address|dob|birth|national_id|art_no' THEN 'LIKELY'
      WHEN LOWER(c.COLUMN_NAME) REGEXP 'name|person|phone|code|number' THEN 'POSSIBLE'
      ELSE 'OK'
    END, 'LIKELY', 'POSSIBLE', 'OK'),
  c.TABLE_NAME, c.ORDINAL_POSITION;
```

**Expected:** Columns like `facility_name`, `lab_name`, `province_name`, `result_status_name`, `rejection_reason_name`, `test_reason_name`, `sample_name`, `geo_name`, `geo_code`, `facility_code` will flag as `POSSIBLE PII - REVIEW`. These are **facility/status/lookup labels, not patient PII** — safe by design. All patient-level columns should show `OK` (because they don't exist in analytics tables).

The `ref_*` tables contain only operational metadata (names, codes, statuses) — no contact details, addresses, emails, phone numbers, or other PII from the source tables.

## 4. Admin Validation Queries

Run as admin user with access to BOTH schemas.

### 4a. Volume totals

```sql
SET @from = '2025-01-01';
SET @to   = '2025-01-31';

SELECT 'raw' AS src, COUNT(*) AS n
FROM vlsm.form_vl
WHERE sample_collection_date IS NOT NULL
  AND DATE(sample_collection_date) BETWEEN @from AND @to
UNION ALL
SELECT 'agg_day', SUM(n_samples)
FROM intelis_insights.vl_agg_volume_status
WHERE period_type = 'day'
  AND period_start_date BETWEEN @from AND @to;
```

Note: Aggregates may be lower than raw due to n < 5 suppression.

### 4b. Volume by status — detailed

```sql
SET @from = '2025-01-01';
SET @to   = '2025-01-31';

SELECT
  COALESCE(r.result_status, a.result_status_id) AS status,
  r.raw_n, a.agg_n,
  COALESCE(r.raw_n, 0) - COALESCE(a.agg_n, 0) AS diff
FROM (
  SELECT result_status, COUNT(*) AS raw_n
  FROM vlsm.form_vl
  WHERE sample_collection_date IS NOT NULL
    AND DATE(sample_collection_date) BETWEEN @from AND @to
  GROUP BY result_status
) r
LEFT JOIN (
  SELECT result_status_id, SUM(n_samples) AS agg_n
  FROM intelis_insights.vl_agg_volume_status
  WHERE period_type = 'day' AND period_start_date BETWEEN @from AND @to
  GROUP BY result_status_id
) a ON r.result_status = a.result_status_id;
```

### 4c. Rejection totals

```sql
SET @from = '2025-01-01';
SET @to   = '2025-01-31';

SELECT 'raw' AS src, COUNT(*) AS n
FROM vlsm.form_vl
WHERE is_sample_rejected = 'yes'
  AND reason_for_sample_rejection IS NOT NULL
  AND COALESCE(rejection_on, DATE(sample_collection_date)) BETWEEN @from AND @to
UNION ALL
SELECT 'agg_day', SUM(n_samples)
FROM intelis_insights.vl_agg_rejections
WHERE period_type = 'day' AND period_start_date BETWEEN @from AND @to;
```

### 4d. Result category totals

```sql
SET @from = '2025-01-01';
SET @to   = '2025-01-31';

SELECT
  COALESCE(r.cat, a.cat) AS category,
  r.raw_n, a.agg_n
FROM (
  SELECT vl_result_category AS cat, COUNT(*) AS raw_n
  FROM vlsm.form_vl
  WHERE sample_collection_date IS NOT NULL
    AND DATE(sample_collection_date) BETWEEN @from AND @to
    AND vl_result_category IS NOT NULL AND vl_result_category <> ''
  GROUP BY vl_result_category
) r
LEFT JOIN (
  SELECT vl_result_category AS cat, SUM(n_samples) AS agg_n
  FROM intelis_insights.vl_agg_result_category
  WHERE period_type = 'day' AND period_start_date BETWEEN @from AND @to
  GROUP BY vl_result_category
) a ON r.cat = a.cat;
```

### 4e. TAT sample sizes

```sql
SELECT tat_metric, period_type, period_start_date,
       SUM(n_samples) AS total_n
FROM intelis_insights.vl_agg_tat
WHERE period_start_date >= '2025-01-01'
GROUP BY tat_metric, period_type, period_start_date
ORDER BY tat_metric, period_type, period_start_date;
```

### 4f. Reference table row counts

```sql
SELECT 'ref_sample_status' AS tbl, COUNT(*) AS analytics_n FROM intelis_insights.ref_sample_status
UNION ALL SELECT 'r_sample_status', COUNT(*) FROM vlsm.r_sample_status
UNION ALL SELECT 'ref_facilities', COUNT(*) FROM intelis_insights.ref_facilities
UNION ALL SELECT 'facility_details', COUNT(*) FROM vlsm.facility_details
UNION ALL SELECT 'ref_geographical_divisions', COUNT(*) FROM intelis_insights.ref_geographical_divisions
UNION ALL SELECT 'geographical_divisions', COUNT(*) FROM vlsm.geographical_divisions
UNION ALL SELECT 'ref_vl_rejection_reasons', COUNT(*) FROM intelis_insights.ref_vl_rejection_reasons
UNION ALL SELECT 'r_vl_sample_rejection_reasons', COUNT(*) FROM vlsm.r_vl_sample_rejection_reasons
UNION ALL SELECT 'ref_vl_test_reasons', COUNT(*) FROM intelis_insights.ref_vl_test_reasons
UNION ALL SELECT 'r_vl_test_reasons', COUNT(*) FROM vlsm.r_vl_test_reasons
UNION ALL SELECT 'ref_vl_sample_types', COUNT(*) FROM intelis_insights.ref_vl_sample_types
UNION ALL SELECT 'r_vl_sample_type', COUNT(*) FROM vlsm.r_vl_sample_type;
```

Each `ref_*` row count should exactly match its source table.

## 5. Privacy Safety Summary

| Guarantee | How |
|-----------|-----|
| No patient identifiers | Analytics tables contain only IDs for facilities/labs, status codes, and aggregate counts |
| Aggregate-only storage | All tables store COUNT, AVG, percentiles. No row-level patient data. |
| Suppression (n < 5) | Groups with fewer than 5 samples are **dropped entirely** (HAVING >= 5). Totals will be lower than raw counts. For public dashboards, a bucketing approach (publishing a 'Suppressed' placeholder row) is recommended — see feasibility.md. TAT tables always drop since percentiles from n < 5 are statistically meaningless. |
| Date bucketing | Timestamps bucketed to day/week/month. No exact datetime in analytics. |
| No patient demographics | No age, gender, DOB, or patient-level demographic fields. Facility/lab IDs and date buckets are present as dimensions but are not patient-identifying. |
| Schema isolation | `insights_ro` has SELECT only on `intelis_insights.*`; no access to `vlsm.*` |
| Denormalized names | Facility/status names stored in analytics tables so `insights_ro` never needs to join to operational tables |
| Reference tables | `ref_*` tables copy safe columns only (names, codes, statuses). PII columns (contact_person, emails, mobile_numbers, address, latitude, longitude) are explicitly excluded. |
| No drilldowns | No foreign keys from analytics back to `form_vl`. No path to individual patients. |

| Threat | Mitigation |
|--------|------------|
| SQL injection on dashboard | `insights_ro` = SELECT only; cannot modify data |
| Credential compromise | Attacker sees only aggregate counts |
| Small-cell re-identification | n < 5 suppression; monitor small facilities |
| Inference from facility+date+status | Low risk (typically many samples); suppress at presentation for edge cases |
| ETL user compromise | Localhost-only; SELECT on limited operational tables |
