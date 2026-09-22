---
description: Lists every viral load report, the dashboard and the Sample Ageing Report, with what each one counts and where to find it.
audience: [lab-staff, lab-supervisor, requesting-facility]
module: [vl]
type: reference
reviewed: 2026-09-22
reviewed_against: 5.7.74
---

# Viral load reports

This page describes every report under **HIV VIRAL LOAD → Management**, the
viral load content of the dashboard, and the Sample Ageing Report.

Applies to InteLIS 5.7.74.

Every report page uses the same controls. Set the filters, select **Search**, and
use the export control where one is offered. See
[How to sign in and navigate InteLIS](signing-in.md) for those controls.

The reports under **ADMIN → Monitoring** are described in
[How to monitor and audit InteLIS](admin-monitoring.md).

## Dashboard

**Location:** **DASHBOARD**

Shows counts of samples registered, tested, rejected, and without a result, plus
facility-wise performance. One tab per test type enabled on the installation.
Cancelled samples are left out of every count.

Opens on the last 29 days, today included. The date range control at the top of
the page changes the period, and its **Last 30 Days** preset covers 30 days.

When **VL Monthly Target** is enabled, the viral load tab also shows the
testing and suppression targets. See [VL Testing Target Report](#vl-testing-target-report).

## Sample Status Report

**Location:** **HIV VIRAL LOAD → Management → Sample Status Report**

Shows three charts.

| Chart | Content |
|---|---|
| Samples Status Overview | The share of samples in each status |
| VL Suppression | The share of results suppressed against not suppressed |
| Laboratory Turnaround Time | Time taken between the stages of testing |

Each chart exports from the menu control at its top right corner.

Select a slice of the status chart to open the samples in that status on a
separate page, which exports to a spreadsheet. The sample table below the charts
exports with **Export to Excel**.

## Control Report

**Location:** **HIV VIRAL LOAD → Management → Control Report**

Charts the performance of controls run alongside patient samples.

Control results are loaded from a file. The page accepts the upload.

## Export Results

**Location:** **HIV VIRAL LOAD → Management → Export Results**

Produces a spreadsheet of results matching the filters. Contains data rows, not
patient reports.

## Print Result

**Location:** **HIV VIRAL LOAD → Management → Print Result**

Produces patient report PDFs. Split across two tabs, **Results not yet Printed**
and **Results already Printed**. The limit is 1000 results per print.

See [How to release results to the requesting facility](release-results.md).

## Clinic Reports

**Location:** **HIV VIRAL LOAD → Management → Clinic Reports**

Seven reports on one page, one per tab. The filters are collapsed. Select
**Filters** to open them. High Viral Load, Sample Rejection, Results Not
Available and Data Quality Check export to a spreadsheet. High VL and Virologic
Failure is not shown on screen. Set the filters and select **Generate report**
to download it.

| Tab | Content |
|---|---|
| **High Viral Load** | Patients whose result is above the viral load threshold set by the administrator |
| **High VL and Virologic Failure** | A downloaded workbook: every not-suppressed result, and a Virologic Failure sheet listing patients with more than one, with the days between collections |
| **Sample Rejection** | Rejected samples with their rejection reason |
| **Results Not Available** | Samples with no result yet, rejected samples excluded, with the date the lab received them. Set **Include Expired Samples** to **No** to leave out expired samples |
| **Data Quality Check** | Share of samples missing each key field, by field and by facility. Select a count to list the samples |
| **Sample Testing** | Samples collected in the period, by facility, with how many were tested and where the rest are |
| **Patient Test History** | Search a patient by ID or name and see every test recorded for that patient, across all test types, with trends and a link to each result PDF |

The **High Viral Load** tab records the follow-up made with the facility. Its
**Contact Status** filter separates patients whose contact is complete from the
rest.

## VL Lab Weekly Report

**Location:** **HIV VIRAL LOAD → Management → VL Lab Weekly Report**

Two reports on one page.

| Report | Content |
|---|---|
| VL Lab Weekly Report | Testing activity for the selected period, defaulting to the last 7 days |
| VL Lab Weekly Report - Female | The same activity for female patients, broken down by age |

Both export to a spreadsheet.

## Sample Rejection Report

**Location:** **HIV VIRAL LOAD → Management → Sample Rejection Report**

Counts rejected samples by lab, facility and rejection reason, including samples
rejected with no reason recorded. Exports to a spreadsheet.

## Sample Monitoring Report

**Location:** **HIV VIRAL LOAD → Management → Sample Monitoring Report**

Reports lab performance over a period, commonly a quarter. Exports to a
spreadsheet.

## VL Testing Target Report

**Location:** **HIV VIRAL LOAD → Management → VL Testing Target Report**

Compares samples tested against each testing lab's monthly target.

The targets are set per testing lab, on the lab's record under **ADMIN →
Facilities**.

| Field | Content |
|---|---|
| **Monthly Target** | Samples the lab is expected to test each month |
| **Suppressed Monthly Target** | Suppressed results expected each month. Viral load only |

**ADMIN → System Configuration → General Configuration** holds only the **VL
Monthly Target** switch. It shows or hides the target charts on the dashboard.

## Freezer/Storage Reports

**Location:** **HIV VIRAL LOAD → Management → Freezer/Storage Reports**

Gives the current freezer position of each sample and its storage history.
Exports to a spreadsheet.

See [How to record where a sample is stored](store-samples.md).

## Sample Ageing Report

**Location:** Not on the menu. Open `/reports/sample-ageing.php` at the
InteLIS web address. The role needs the **Sample Ageing Report** permission.

Shows how long samples have been waiting at each stage, so stalled samples can
be found before they expire.

| Stage | Content |
|---|---|
| At facility | Registered at the collection point. No lab has recorded receiving it |
| At lab, awaiting test | A lab has the sample but has not tested it yet. Includes failed, on hold and reordered |
| Tested, awaiting approval | Tested. The result is waiting for someone to approve it |
| Approved, awaiting release | The result is ready, but has not been printed, sent or downloaded |
| Released | The result was printed, sent to the facility, or downloaded by the facility system |
| Exits: Rejected, Expired, Lost or missing, Cancelled | Samples that left without a released result, listed so the totals reconcile |

The period is by collection date, or by request date when no collection date is
recorded. The **Test** selector covers every enabled test type.

The breakdown groups samples by **Collection Facility**, **Testing Lab** or
**Implementing Partner**.
