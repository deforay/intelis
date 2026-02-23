# Intelis Insights — Implementation Guide

> How to build a privacy-safe analytics layer for any VLSM test type.
> Based on lessons from the VL (Viral Load) implementation in `dev/insights/vl/`.

---

## Quick Reference

| Test Type | Form Table | Rejection Reasons Table | Test Reasons Table | Sample Types Table | Result Category Field | App Directory |
|-----------|-----------|------------------------|--------------------|--------------------|----------------------|---------------|
| VL | `form_vl` | `r_vl_sample_rejection_reasons` | `r_vl_test_reasons` | `r_vl_sample_type` | `vl_result_category` | `app/vl/` |
| EID | `form_eid` | `r_eid_sample_rejection_reasons` | `r_eid_test_reasons` | `r_eid_sample_type` | `eid_result_category` | `app/eid/` |
| COVID-19 | `form_covid19` | `r_covid19_sample_rejection_reasons` | `r_covid19_test_reasons` | `r_covid19_sample_type` | `covid19_result_category` | `app/covid-19/` |
| Hepatitis | `form_hepatitis` | `r_hepatitis_sample_rejection_reasons` | `r_hepatitis_test_reasons` | `r_hepatitis_sample_type` | `hepatitis_result_category` | `app/hepatitis/` |
| TB | `form_tb` | `r_tb_sample_rejection_reasons` | `r_tb_test_reasons` | `r_tb_sample_type` | `tb_result_category` | `app/tb/` |
| Generic | `form_generic_tests` | `r_generic_sample_rejection_reasons` | `r_generic_test_reasons` | `r_generic_sample_type` | — | `app/generic-tests/` |

**Shared across all test types:**
- `r_sample_status` (13 statuses, same IDs everywhere)
- `facility_details` (facilities/labs)
- `geographical_divisions` (province/district hierarchy)
- `SAMPLE_STATUS` constants in `app/system/constants.php`

---

## Deliverables Checklist

For each test type, produce these files in `dev/insights/{testtype}/`:

```
dev/insights/{testtype}/
├── data_dictionary.md          # Task A: Column-by-column PII classification
├── feasibility.md              # Task B: What analytics are possible
├── privacy_and_privileges.md   # Task D: DB users, grants, validation queries
├── semantic/
│   └── {testtype}.yml          # Task E+F: Metric registry + blessed queries + disambiguation
└── sql/
    ├── 001_create_intelis_insights_schema.sql  # Shared (already exists)
    ├── 002_create_{testtype}_aggregate_tables.sql  # Task C: DDL
    └── 003_refresh_{testtype}_aggregates.sql       # Task C: ETL procedure
```

---

## Step-by-Step Process

### Task A: Data Dictionary

**Goal:** Classify every column in `form_{testtype}` as PII / Quasi / Safe.

1. Read `sql/init.sql` for the `form_{testtype}` CREATE TABLE
2. Read `app/{testtype}/requests/addXxxRequestHelper.php` to see which POST fields map to which columns
3. Read `app/{testtype}/requests/forms/` for UI field labels
4. Read the Service class (e.g., `EidService`, `Covid19Service`) for result category logic

**Classification rules (universal):**

| Category | Rule | Examples |
|----------|------|---------|
| **PII** | Identifies or locates a patient | Names, DOB, phone, address, ART no, sample codes, clinician names |
| **Quasi** | Could identify with context; use only bucketed/aggregated | Exact timestamps, age, result values, treatment dates |
| **Safe** | Organizational/categorical; safe for analytics | Status IDs, facility IDs, category labels, test platform |

**Key pattern:** Patient demographics are always PII. Facility/lab metadata is always Safe. Timestamps are Quasi (bucket to day/week/month).

### Task B: Feasibility Matrix

**Goal:** Validate which analytics capabilities are SUPPORTED by the schema.

**Standard capabilities to check for every test type:**

| # | Capability | What to verify |
|---|-----------|----------------|
| 1 | Volume by time + status | `sample_collection_date` + `result_status` exist? |
| 2 | Volume by org unit | `facility_id`, `lab_id`, `province_id` exist? |
| 3 | Rejections breakdown | `is_sample_rejected`, `reason_for_sample_rejection`, `rejection_on` exist? |
| 4 | Result category aggregates | Pre-computed result category column exists? What are the values? |
| 5 | Pending backlog aging | `result_status` + `sample_collection_date` available for pending statuses? |
| 6 | TAT (multi-definition) | Which timestamps are populated? Define all valid pairs. |

**For each capability:** classify as SUPPORTED, PARTIALLY SUPPORTED, or NOT SUPPORTED with evidence.

**Existing report baselines:** Check `app/{testtype}/program-management/` for existing TAT/status reports. Your analytics must produce equivalent numbers.

### Task C: Schema + ETL

**Standard aggregate tables (adapt prefix):**

| Table | Dimensions | Metrics |
|-------|-----------|---------|
| `{tt}_agg_volume_status` | period, status, country | n_samples |
| `{tt}_agg_volume_org` | period, facility, lab, province, country | n_samples (+ denormalized names) |
| `{tt}_agg_rejections` | period, reason, facility, lab, country | n_samples (+ reason name, rejection_type) |
| `{tt}_agg_result_category` | period, category, facility, lab, country | n_samples |
| `{tt}_agg_backlog_aging` | as_of_date, status, age_bucket, facility, lab, country | n_samples |
| `{tt}_agg_tat` | period, tat_metric, facility, lab, country | n_samples, avg_hours, p50/p90/p95, min/max |

**Reference tables (per test type):**

| Table | Source | Shared? |
|-------|--------|---------|
| `ref_sample_status` | `r_sample_status` | Yes — create once, all test types use it |
| `ref_facilities` | `facility_details` (safe cols only) | Yes — shared |
| `ref_geographical_divisions` | `geographical_divisions` | Yes — shared |
| `ref_{tt}_rejection_reasons` | `r_{tt}_sample_rejection_reasons` | No — per test type |
| `ref_{tt}_test_reasons` | `r_{tt}_test_reasons` | No — per test type |
| `ref_{tt}_sample_types` | `r_{tt}_sample_type` | No — per test type |

**Mandatory columns on every aggregate table:**

```sql
`period_type`             ENUM('day','week','month') NOT NULL,
`period_start_date`       DATE NOT NULL,
`vlsm_country_id`         INT DEFAULT NULL,
`n_samples`               BIGINT UNSIGNED NOT NULL DEFAULT 0,
`source_max_last_modified` DATETIME DEFAULT NULL,
`suppression_applied`     TINYINT(1) NOT NULL DEFAULT 1,
`refreshed_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
```

(Backlog uses `as_of_date` instead of `period_type` + `period_start_date`.)

### Task D: Privacy & Privileges

**Users (shared across test types — create once):**

```sql
-- Read-only analytics user
CREATE USER IF NOT EXISTS 'insights_ro'@'%'
  IDENTIFIED BY '<CHANGE_ME>';
GRANT SELECT ON `intelis_insights`.* TO 'insights_ro'@'%';

-- ETL user (add grants per test type)
CREATE USER IF NOT EXISTS 'insights_etl'@'localhost'
  IDENTIFIED BY '<CHANGE_ME>';
GRANT SELECT, INSERT, UPDATE, DELETE ON `intelis_insights`.* TO 'insights_etl'@'localhost';
GRANT EXECUTE ON `intelis_insights`.* TO 'insights_etl'@'localhost';
```

**Per test type, add SELECT grants for the ETL user:**

```sql
GRANT SELECT ON `vlsm`.`form_{testtype}` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`r_{tt}_sample_rejection_reasons` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`r_{tt}_test_reasons` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`r_{tt}_sample_type` TO 'insights_etl'@'localhost';
-- Shared tables (grant once):
GRANT SELECT ON `vlsm`.`facility_details` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`r_sample_status` TO 'insights_etl'@'localhost';
GRANT SELECT ON `vlsm`.`geographical_divisions` TO 'insights_etl'@'localhost';
```

**Validation queries:** Write raw-vs-aggregate reconciliation queries for each table. See VL example (`privacy_and_privileges.md` section 4).

### Task E: Semantic YAML

Copy `vl/semantic/vl.yml` structure. Adapt:

1. `domain:` → `{testtype}`
2. `dimensions:` → update table names, result category values
3. `metrics:` → update table references
4. `reference_tables:` → update source tables
5. `tat_definitions:` → define test-type-specific pairs
6. `event_date_policy:` → verify which timestamp drives each table

---

## Key Design Decisions (Apply to All Test Types)

### 1. Suppression: Row Dropping

Groups with n < 5 are **dropped entirely** (`HAVING COUNT(*) >= 5`). Totals will be lower than raw counts.

- **For internal analytics:** This is fine. Analysts understand the gap.
- **For public dashboards:** Switch to bucketing — publish a single "Suppressed" row per period that captures residual count. See `vl/feasibility.md` "Small-n Suppression Behavior".
- **TAT tables:** Always drop. Percentiles from n < 5 are meaningless.

### 2. Denormalized Names

`insights_ro` has zero access to `vlsm.*`. Therefore:
- Aggregate tables store denormalized names (facility_name, status_name, etc.)
- Reference tables (`ref_*`) copy safe columns from operational lookups
- PII columns (contact_person, emails, phone, address, lat/long) are **never** copied

### 3. Event Date Policy

Each table has a defined "event date" — the timestamp that determines which time bucket a sample falls into. Document this per table. Typical:

| Table | Event Date |
|-------|-----------|
| Volume (status/org) | `sample_collection_date` |
| Rejections | `COALESCE(rejection_on, sample_collection_date)` |
| Result category | `sample_collection_date` |
| Backlog | `sample_collection_date` (age = as_of minus collection) |
| TAT | Start timestamp of the TAT pair (varies per metric) |

### 4. Week = ISO Monday

```sql
DATE_SUB(DATE(column), INTERVAL WEEKDAY(DATE(column)) DAY)
```
`WEEKDAY()` returns 0 for Monday. Stored as `period_start_date` (a DATE, no ambiguity).

### 5. TAT is Multi-Definition

Never allow a single ambiguous "TAT" metric. Every test type must define explicit pairs:

| Pair | Start | End |
|------|-------|-----|
| collection_to_receipt_lab | sample_collection_date | sample_received_at_lab_datetime |
| collection_to_testing | sample_collection_date | sample_tested_datetime |
| collection_to_printed | sample_collection_date | result_printed_datetime |
| ... | ... | ... |

Check which timestamps the test type actually populates. Some test types may have fewer viable pairs than VL's 13.

### 6. Pending Status Definition

Pending/in-flight statuses: **{6, 8, 9}** (Registered at Lab, Awaiting Approval, Registered at Clinic). Status 13 (Referred) excluded by default. This is universal across test types.

### 7. Backlog Age Buckets

Four buckets: `0-7d`, `8-14d`, `15-30d`, `30+d`. Stored as ENUM.

### 8. ETL Requirements

- **MySQL 8.0+** required (window functions for TAT percentiles)
- **Transaction wrapping:** `START TRANSACTION` / `COMMIT` around entire procedure
- **DELETE-then-INSERT** (not TRUNCATE — needs DROP privilege)
- **Idempotent:** safe to re-run for same date range
- **Default range:** last 180 days

---

## Gotchas We Learned

1. **TRUNCATE needs DROP privilege.** Use `DELETE FROM` instead for the ETL user.

2. **`label_column` in YAML must note which tables have it inline.** Not all aggregate tables denormalize facility/lab names. Add `label_inline_tables` and `label_ref_table` to tell the query compiler where to get labels.

3. **Validation queries must match ETL filters exactly.** If ETL filters `reason_for_sample_rejection IS NOT NULL`, the validation query must too, or you'll see false diffs.

4. **`vl_sample_count` spans multiple tables at different grains.** When a metric maps to multiple tables, document that the query compiler must pick ONE table based on requested dimensions — never sum across tables.

5. **`result_printed_datetime` is well-populated** for VL (set on first PDF generation). Verify equivalent timestamp population for other test types before committing to TAT pairs.

6. **Geography is point-in-time.** If a facility moves provinces, historical aggregates will reflect the new geography, not the old. Acceptable for initial implementation; snapshot fields needed for historical accuracy.

7. **YAML `time_buckets` can define quarter/year** but the ETL only pre-computes day/week/month. Quarter and year are derived at query time by re-aggregating stored data.

8. **Suppression rate formula:** The existing codebase uses `LIKE 'suppressed%'` but exact match `= 'suppressed'` is equivalent for current category values. Document this equivalence.

9. **`ref_facilities` must exclude PII:** contact_person, facility_emails, report_email, facility_mobile_numbers, address, latitude, longitude, facility_logo, sts_token, sts_token_expiry.

10. **PII audit query** should be run after schema changes. Regex scans column names for patient/name/phone/address patterns. `facility_name`, `status_name`, etc. will flag as "POSSIBLE PII" — these are safe (facility labels, not patient data).

11. **Semantic YAML is the product surface.** The YAML isn't just documentation — it's the contract a query compiler, chatbot, or API consumes. Every metric, dimension, and table must be machine-readable and unambiguous.

12. **Never allow a bare "TAT" query.** Generic TAT is meaningless — always force disambiguation to a specific timestamp pair. Same principle applies to other ambiguous terms like "positivity" (which denominator?) and "this month" (which date axis?).

13. **Re-aggregating percentiles is approximate.** When blessed queries aggregate p50/p90 across facilities or labs, they use weighted averages (`SUM(p50 * n) / SUM(n)`). This is an approximation — exact percentiles require row-level data. Document this in every TAT blessed query.

14. **Composite queries cross tables at application layer.** Rejection rate (rejections / volume) requires two tables at different grains. Never attempt this in a single SQL query joining aggregate tables — compose at the application layer with `sql_parts`.

15. **`HAVING tested >= 5` on derived rates.** When computing per-facility suppression/rejection rates, apply a minimum volume threshold to avoid publishing meaningless rates from tiny sample sizes. This is separate from ETL-level suppression.

---

## Test-Type-Specific Considerations

### EID (Early Infant Diagnosis)
- Result categories: likely `detected` / `not detected` / `indeterminate` (check `EidService`)
- Patient demographics include infant-specific fields (mother's info, feeding method)
- Smaller volume than VL typically — suppression may remove more groups

### COVID-19
- Result categories: `positive` / `negative` / `indeterminate` / `inconclusive`
- May have rapid-test vs PCR distinction
- High-volume surges — ETL performance matters more

### Hepatitis
- Multiple markers (HBV, HCV) — result categories vary by marker
- May need marker as an additional dimension

### TB
- Result categories: Mtb detected/not detected, rifampicin resistance
- Multiple test methods (GeneXpert, smear, culture) — method as dimension
- Longer TAT (culture takes weeks)

### Generic Tests
- No pre-defined result category field — may need custom derivation
- Form structure is more flexible; data dictionary requires more investigation

---

## File Naming Convention

```
dev/insights/
├── GUIDE.md                    # This file (shared)
├── vl/                         # Viral Load (complete, reference implementation)
│   ├── data_dictionary.md
│   ├── feasibility.md
│   ├── privacy_and_privileges.md
│   ├── semantic/vl.yml
│   └── sql/001..002..003..
├── eid/                        # Early Infant Diagnosis (future)
│   └── (same structure)
├── covid19/                    # COVID-19 (future)
│   └── (same structure)
├── hepatitis/                  # Hepatitis (future)
│   └── (same structure)
├── tb/                         # Tuberculosis (future)
│   └── (same structure)
└── generic/                    # Generic Tests (future)
    └── (same structure)
```

---

## Task F: Blessed Queries & Disambiguation

**Goal:** Make the semantic YAML a usable product surface — not just a schema doc.

### Blessed Queries (10–20 per test type)

Add a `blessed_queries` section to the YAML. Each entry maps a natural-language question to an unambiguous SQL query.

**Standard query categories to cover:**

| # | Category | Example Natural Language | Key Decisions |
|---|----------|------------------------|---------------|
| 1 | Monthly volume | "How many samples per month?" | Table: `{tt}_agg_volume_status` |
| 2 | Volume by status | "Break down by status" | JOIN `ref_sample_status` for labels |
| 3 | Volume by facility | "Top 10 facilities" | Table: `{tt}_agg_volume_org` |
| 4 | Volume by province | "Volume by province" | Table: `{tt}_agg_volume_org` |
| 5 | Rejection rate | "Monthly rejection rate" | Cross-table: rejections / volume |
| 6 | Top rejection reasons | "Why are samples rejected?" | Table: `{tt}_agg_rejections`, GROUP BY reason |
| 7 | Suppression/positivity rate | "Suppression rate by month" | Table: `{tt}_agg_result_category`, clinical denominator |
| 8 | Result category breakdown | "Breakdown of results" | Table: `{tt}_agg_result_category` |
| 9 | TAT by metric | "Median collection-to-printed TAT" | Table: `{tt}_agg_tat`, specify `tat_metric` |
| 10 | TAT by lab | "Compare TAT across labs" | JOIN `ref_facilities` for lab names |
| 11 | Pending backlog | "How many pending?" | Table: `{tt}_agg_backlog_aging`, latest as_of_date |
| 12 | Backlog by lab | "Which labs have oldest samples?" | Filter age_bucket = '30+d' |
| 13 | Backlog trend | "Backlog trend last 30 days" | GROUP BY as_of_date |
| 14 | Weekly trend | "Weekly volume trend" | period_type = 'week' |
| 15 | Facility scorecard | "Scorecard for facility X" | Composite: 3 queries across tables |
| 16 | Lowest-performing facilities | "Worst suppression rates" | GROUP BY facility_id, HAVING tested >= 5 |

**Query structure:**

```yaml
blessed_queries:
  - id: q01_monthly_volume
    natural_language: "How many {TT} samples were collected each month in 2025?"
    metric: {tt}_sample_count
    table: {tt}_agg_volume_status
    filters:
      period_type: month
    group_by: [period_start_date]
    sql: >
      SELECT period_start_date, SUM(n_samples) AS total
      FROM {tt}_agg_volume_status
      WHERE period_type = 'month'
        AND period_start_date >= '2025-01-01'
      GROUP BY period_start_date
      ORDER BY period_start_date;
```

**Key rules:**
- Every query must specify exactly ONE table (never join/sum across aggregate tables at different grains)
- Use `ref_*` tables for label resolution (JOIN `ref_facilities`, `ref_sample_status`, etc.)
- For composite queries (e.g., rejection rate = rejections / volume), use `sql_parts` with separate queries composed at the application layer
- Re-aggregating percentiles (TAT p50/p90) across groups uses weighted approximation — document this

### Disambiguation Rules

Add a `disambiguation` section to the YAML. Each entry defines:
1. **Triggers** — phrases that indicate ambiguity
2. **Options** — the possible interpretations with labels and context
3. **Default suggestion** — what to assume if the user says "just pick one"
4. **Response format** — structured output: `{ clarification_required: true, ... }`

**Standard disambiguation cases (apply to all test types):**

| Case | Trigger Phrases | What's Ambiguous | Options |
|------|----------------|-----------------|---------|
| TAT | "TAT", "turnaround time" | Which timestamp pair | List all `tat_definitions` |
| Time period | "this month", "last week", "recently" | Which date axis (collection? rejection? TAT start?) | Event date policy per table |
| Result rate | "positivity", "suppression rate" | Denominator: clinical vs all-tested vs all-received | 2-3 denominator choices |
| Rejection scope | "rejection rate" | Overall vs by-reason vs by-facility | Dimension choices |
| Volume grain | "how many samples" | Which breakdown (by status? facility? result?) | Table/dimension choices |

**Disambiguation structure:**

```yaml
disambiguation:
  tat:
    triggers: ["TAT", "turnaround time"]
    action: clarify
    default_suggestion: tat_collection_to_printed
    message: "TAT can be measured between N different process steps. Which one?"
    options:
      - tat_collection_to_receipt_lab
      - tat_collection_to_testing
      # ... all tat_definitions
    response_format:
      clarification_required: true
      dimension: tat_metric

  time_period:
    triggers: ["this month", "last month", "this week"]
    action: clarify
    message: "Which date axis should 'this month' apply to?"
    options:
      - label: "Collection date"
        applies_to: [volume, result_category tables]
      - label: "Rejection date"
        applies_to: [rejections table]
      - label: "TAT start date"
        applies_to: [TAT table]
    default_suggestion: "Collection date"
```

**This works without a UI.** A CLI tool, API, or chatbot can consume the structured `{ clarification_required: true }` response and present options however it needs to.

### Test-Type Adaptations

- **EID/COVID/Hepatitis/TB:** Same disambiguation structure. Adapt TAT pairs (fewer pairs for some types), result categories, and denominator definitions.
- **TB:** Add `test_method` disambiguation ("Which test method — GeneXpert, smear, culture?")
- **Hepatitis:** Add `marker` disambiguation ("Which marker — HBV, HCV?")
- **Generic Tests:** May need broader disambiguation since there's no pre-defined result category

---

## Review Checklist

Before finalizing any test type implementation, verify:

- [ ] Every column selected from `form_{testtype}` in ETL is classified Safe or Quasi (never PII)
- [ ] No patient identifiers in any `intelis_insights` table
- [ ] `ref_facilities` excludes all contact/address/auth fields
- [ ] All table names match across DDL, ETL, ETL log entries, YAML, privacy doc
- [ ] All column lists in DDL match ETL INSERT column lists
- [ ] TAT metric names match across YAML (dimensions + tat_definitions) and ETL
- [ ] TAT start/end fields match between YAML and ETL
- [ ] Status IDs match between YAML allowed_values and data dictionary
- [ ] Age buckets match across DDL ENUM, ETL CASE, YAML
- [ ] ETL user grants cover all operational tables accessed by stored procedure
- [ ] Suppression threshold consistent across YAML, ETL, privacy doc
- [ ] Validation queries use same filters as ETL (no false diffs)
- [ ] `label_column` references in YAML match actual DDL columns (with `label_inline_tables` for partial)
- [ ] Existing report baselines from `program-management/` are mapped to analytics metrics
- [ ] PII audit query run and results documented
- [ ] Blessed queries cover all 6 aggregate table types (volume×2, rejections, result_category, backlog, TAT)
- [ ] Every blessed query specifies exactly ONE table (no cross-grain joins)
- [ ] Blessed queries use `ref_*` tables for label resolution, not operational tables
- [ ] Disambiguation covers at minimum: TAT, time period, result rate denominator
- [ ] Every disambiguation entry has `triggers`, `options`, `default_suggestion`, and `response_format`
- [ ] Composite queries use `sql_parts` (not single SQL joining aggregate tables)
