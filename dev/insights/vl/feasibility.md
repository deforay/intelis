# VL Analytics — Feasibility Matrix

> Validated against `form_vl` schema, `r_sample_status`, `VlService`, form UI/helpers, and **existing reports** in `app/vl/program-management/`.

---

## Existing Report Baselines

The codebase already computes these metrics in `app/vl/program-management/getSampleStatus.php`. Our analytics tables must produce equivalent numbers.

| Existing Metric | Code Reference | Formula |
|----------------|----------------|---------|
| AvgCollectedTested | getSampleStatus.php:210 | `AVG(TIMESTAMPDIFF(DAY, sample_collection_date, sample_tested_datetime))` |
| AvgCollectedReceived | getSampleStatus.php:211 | `AVG(TIMESTAMPDIFF(DAY, sample_collection_date, sample_received_at_lab_datetime))` |
| AvgReceivedTested | getSampleStatus.php:212 | `AVG(TIMESTAMPDIFF(DAY, sample_received_at_lab_datetime, sample_tested_datetime))` |
| AvgCollectedPrinted | getSampleStatus.php:213 | `AVG(TIMESTAMPDIFF(DAY, sample_collection_date, result_printed_datetime))` |
| AvgTestedPrinted | getSampleStatus.php:214 | `AVG(TIMESTAMPDIFF(DAY, sample_tested_datetime, result_printed_datetime))` |

Suppression rate: `SUM(CASE WHEN vl_result_category LIKE 'suppressed%' THEN 1 ELSE 0 END)` — `getSuppressedTargetReport.php:36`

---

## Candidate Capability Matrix

| # | Capability | Classification | Notes |
|---|-----------|----------------|-------|
| 1 | Volume by time + status | **SUPPORTED** | `sample_collection_date` + `result_status` (1-13). Note: status is current snapshot, no history table. |
| 2 | Volume by org unit / geography | **SUPPORTED** | Facility/lab/province/country IDs exist and are sufficient for current-state reporting. **Note:** geography reflects current facility attributes, not "as-was at request time". If historical geography is ever required, a snapshot field would need to be added to operational writes. |
| 3 | Rejections breakdown | **SUPPORTED** | `is_sample_rejected`, `reason_for_sample_rejection` (FK to lookup with `rejection_type` grouping), `rejection_on`. |
| 4 | Result category aggregates | **SUPPORTED** | `vl_result_category` pre-computed by VlService. Suppression rate uses `LIKE 'suppressed%'` pattern. Filter to status=7 (Accepted) for valid suppression rates. |
| 5 | Pending backlog aging | **SUPPORTED** | Computable from `result_status` + `sample_collection_date`. Pending statuses: {6, 8, 9} (Registered at Lab, Awaiting Approval, Registered at Clinic). Status 13 (Referred) excluded by default but can be added if needed. |
| 6 | TAT (multi-definition) | **SUPPORTED** (13 pairs) | See detailed section below. 1 additional pair (hub-receipt) is PARTIALLY SUPPORTED. |

---

## Multi-Definition TAT

### Available Timestamps

| # | Column | Meaning | Populated How |
|---|--------|---------|---------------|
| T1 | `sample_collection_date` | Sample collected at clinic | Form entry (required) |
| T2 | `sample_dispatched_datetime` | Sample dispatched from clinic | Form entry |
| T3 | `sample_received_at_hub_datetime` | Received at transport hub | Form entry (hub workflows only) |
| T4 | `sample_received_at_lab_datetime` | Received at testing lab | Form entry |
| T5 | `sample_tested_datetime` | Sample tested at lab | Form / instrument import |
| T6 | `result_reviewed_datetime` | Result reviewed | Form entry |
| T7 | `result_approved_datetime` | Result approved | Form entry |
| T8 | `result_dispatched_datetime` | Result dispatched to facility | Form entry |
| T9 | `result_printed_datetime` | Result PDF first generated | Set by `generate-result-pdf.php` on first print |
| T10 | `result_printed_on_lis_datetime` | First print on LIS instance | Set by `generate-result-pdf.php` (LIS) |
| T11 | `result_printed_on_sts_datetime` | First print on STS instance | Set by `generate-result-pdf.php` (STS) |

### TAT Pair Feasibility

| Canonical Metric | Start | End | Status | Matches Existing Report? |
|-----------------|-------|-----|--------|--------------------------|
| `tat_collection_to_receipt_hub` | T1 | T3 | **PARTIALLY** — T3 only in hub workflows | No |
| `tat_collection_to_receipt_lab` | T1 | T4 | **SUPPORTED** | Yes: `AvgCollectedReceived` |
| `tat_collection_to_testing` | T1 | T5 | **SUPPORTED** | Yes: `AvgCollectedTested` |
| `tat_collection_to_approval` | T1 | T7 | **SUPPORTED** | No (new) |
| `tat_collection_to_dispatch` | T1 | T8 | **SUPPORTED** | No (new) |
| `tat_collection_to_printed` | T1 | T9 | **SUPPORTED** | Yes: `AvgCollectedPrinted` |
| `tat_dispatch_to_receipt_lab` | T2 | T4 | **SUPPORTED** | No (new) |
| `tat_receipt_lab_to_testing` | T4 | T5 | **SUPPORTED** | Yes: `AvgReceivedTested` |
| `tat_receipt_lab_to_approval` | T4 | T7 | **SUPPORTED** | No (new) |
| `tat_receipt_lab_to_dispatch` | T4 | T8 | **SUPPORTED** | No (new) |
| `tat_testing_to_approval` | T5 | T7 | **SUPPORTED** | No (new) |
| `tat_testing_to_printed` | T5 | T9 | **SUPPORTED** | Yes: `AvgTestedPrinted` |
| `tat_testing_to_dispatch` | T5 | T8 | **SUPPORTED** | No (new) |
| `tat_approval_to_dispatch` | T7 | T8 | **SUPPORTED** | No (new) |

### Metrics Selected for Implementation

All 13 SUPPORTED pairs above. The hub-receipt pair (`tat_collection_to_receipt_hub`) is excluded as PARTIALLY SUPPORTED due to low population of `sample_received_at_hub_datetime` outside hub-based workflows.

### TAT Notes

1. **"TAT" is ambiguous.** If a user asks "TAT" generically, the system MUST ask which pair. Default suggestion: `tat_collection_to_printed` (matches the most common existing report metric).

2. **Existing reports use DAY granularity** (`TIMESTAMPDIFF(DAY, ...)`). Our analytics stores hours for precision; presentation converts to days.

3. **`result_printed_datetime`** is confirmed well-populated — set on first PDF generation in `generate-result-pdf.php`. Only null for samples never printed.

4. **Negative TAT** (end < start) excluded as data quality issues.

5. **Suppression** (n < 5) applied to all TAT aggregate groups.

---

## Small-n Suppression Behavior

### Current Implementation: Row Dropping

Groups with n < 5 are **dropped entirely** (`HAVING COUNT(*) >= 5`). No "Suppressed" placeholder rows are published.

**Consequence:** `SUM(n_samples)` across detailed rows will be **less than** the true raw count. The gap equals the total samples in suppressed groups. Validation queries (see `privacy_and_privileges.md` section 4) already document this: "Aggregates may be lower than raw due to n < 5 suppression."

### Alternative: Bucketing (recommended for public dashboards)

For public-facing dashboards, a bucketing approach preserves totals:

1. Compute all groups (remove `HAVING`)
2. For groups where n < 5, replace dimension values with sentinels and re-aggregate:
   - `result_status_id = -1, result_status_name = 'Suppressed'`
   - `rejection_reason_id = -1, rejection_reason_name = 'Suppressed'`
   - `vl_result_category = 'suppressed_group'`
   - `facility_id = -1` (aggregate across suppressed facilities)
3. This produces one "Suppressed" row per period that captures the residual count
4. `SUM(n_samples)` now equals the true total

**Trade-off:** Bucketing adds ETL complexity (two-pass insert or post-insert merge). For internal analytics where analysts understand the gap, row dropping is simpler and sufficient. For public dashboards, bucketing is strongly recommended.

### TAT Suppression

TAT tables suppress differently: when n < 5, no meaningful percentile can be computed, so dropping is the correct behavior (not bucketing). AVG/p50/p90/p95 from fewer than 5 observations are statistically meaningless.

---

## Percentile Computation

### Method

TAT percentiles use MySQL 8.0 window functions:
```sql
ROW_NUMBER() OVER (PARTITION BY ... ORDER BY tat_hours) AS rn,
COUNT(*)     OVER (PARTITION BY ...)                     AS n
-- then: MAX(CASE WHEN rn = CEIL(n * 0.50) THEN tat_hours END) for p50
```

This is a nearest-rank method. For small n (e.g., n=5), percentile resolution is coarse but acceptable given the suppression floor.

### Requirements

- **MySQL 8.0+** required (window functions not available in 5.7 or MariaDB < 10.2).
- Deployments on older MySQL must use a subquery-based rank or `GROUP_CONCAT` + `SUBSTRING_INDEX` workaround.

### Performance

The TAT ETL is the heaviest step: 13 UNION ALL branches scan `form_vl`, then periodize (x3), then rank, then aggregate. For large deployments:

1. **Acceptable for nightly batch** on tables up to ~5M rows with proper indexing on `sample_collection_date`.
2. **If slow:** add a composite index `(sample_collection_date, result_status)` on `form_vl` if not already present. The ETL already filters on date range (180 days default), limiting the scan.
3. **If still slow:** pre-compute TAT hours into a staging temp table first, then periodize and rank from the staging table (avoids re-scanning `form_vl` 13 times).

---

## ETL Crash Safety

### Aggregate Tables

The DELETE-then-INSERT pattern means a crash between DELETE and INSERT loses data for that refresh window. Mitigation:

1. **Transaction wrapping:** The stored procedure wraps all operations in a single transaction (`START TRANSACTION` / `COMMIT`). If any step fails, the entire refresh rolls back — no partial state.
2. **Idempotent re-run:** Re-calling the procedure for the same date range safely re-deletes and re-inserts.

### Reference Tables

DELETE-then-INSERT on ref tables: if ETL crashes after DELETE but before INSERT, the ref table is empty. Mitigation:

1. For small ref tables (hundreds of rows), the window between DELETE and INSERT is milliseconds — risk is minimal.
2. **For production hardening:** use a swap-table pattern: populate `ref_table_staging`, then `RENAME TABLE ref_table TO ref_table_old, ref_table_staging TO ref_table`, then `DROP TABLE ref_table_old`. This is atomic.
3. Current implementation uses the simpler approach; swap-table is documented as a future hardening step.

---

## Event Date Policy

Each metric has a defined **event date** — the timestamp that determines which time bucket a sample falls into:

| Table | Event Date | Rationale |
|-------|-----------|-----------|
| `vl_agg_volume_status` | `sample_collection_date` | Volume is counted by when the sample was collected |
| `vl_agg_volume_org` | `sample_collection_date` | Same — collection date drives volume |
| `vl_agg_rejections` | `COALESCE(rejection_on, sample_collection_date)` | Rejection date if available, otherwise collection date |
| `vl_agg_result_category` | `sample_collection_date` | Result category is attributed to collection period |
| `vl_agg_backlog_aging` | `sample_collection_date` (age = `as_of_date - collection_date`) | Age is days since collection |
| `vl_agg_tat` | Start timestamp of the TAT pair (varies per metric) | e.g., `tat_receipt_lab_to_testing` uses `sample_received_at_lab_datetime` as event date |

**Important:** TAT event dates vary by metric. `tat_collection_to_testing` buckets by `sample_collection_date`, but `tat_receipt_lab_to_testing` buckets by `sample_received_at_lab_datetime`. This means the same sample may appear in different time buckets for different TAT metrics. This is intentional — each metric measures from its own start point.

---

## Week Definition

Weeks use **ISO week start (Monday)**:
```sql
DATE_SUB(DATE(column), INTERVAL WEEKDAY(DATE(column)) DAY)
```
`WEEKDAY()` returns 0 for Monday, so this always produces the Monday of the week. Stored as `period_start_date` (a DATE, not a week number) — no ambiguity.

---

## Open Notes

1. **Geography "as-was":** Current approach uses current facility geography (point-in-time). If historical geography is ever needed, a snapshot field would need to be added to operational writes. Not blocking for initial implementation.

2. **Suppression threshold:** Default 1000 cp/mL but configurable per instance. Pre-computed `vl_result_category` already reflects the instance-specific threshold. Cross-instance comparison may need threshold normalization in the future.
