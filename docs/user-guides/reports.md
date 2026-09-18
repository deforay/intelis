# Viral load reports

This page describes every report under **HIV VIRAL LOAD → Management**, the
viral load content of the dashboard, and the Sample Ageing Report.

Applies to InteLIS 5.7.72.

Every report page uses the same controls. Set the filters, select **Search**, and
use the export control where one is offered. See
[How to sign in and navigate InteLIS](signing-in.md) for those controls.

The reports under **ADMIN → Monitoring** are described in
[How to monitor and audit InteLIS](admin-monitoring.md).

## Dashboard

**Location:** **DASHBOARD**

Shows counts of samples registered, tested, rejected, and without a result, plus
facility-wise performance. One tab per test type enabled on the installation.

Opens on the last 29 days, today included. The date range control at the top of
the page changes the period, and its **Last 30 Days** preset covers 30 days.

When **VL Monthly Target** is enabled, the viral load tab also shows the
testing and suppression targets. See [VL Testing Target Report](#vl-testing-target-report).

## Sample Status Report

**Location:** **HIV VIRAL LOAD → Management → Sample Status Report**

Shows three charts.

| Chart | Content |
|---|---|
| Sample status | The share of samples in each status |
| VL suppression | The share of results suppressed against not suppressed |
| Laboratory turnaround time | Time taken between the stages of testing |

Each chart exports from the menu control at its top right corner.

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

Seven tabular reports on one page. Each exports to a spreadsheet.

| Tab | Content |
|---|---|
| **High Viral Load** | Patients whose result is above the viral load threshold set by the administrator |
| **High VL and Virologic Failure** | High viral load results with the virologic failure assessment |
| **Sample Rejection** | Rejected samples with their rejection reason |
| **Results Not Available** | Samples registered with no result recorded |
| **Data Quality Check** | Records with missing or inconsistent data |
| **Sample Testing** | Samples tested over the selected period |
| **Patient Test History** | Every test recorded for one patient |

The **High Viral Load** tab records the follow-up made with the facility. Its
**Contact Status** filter separates patients whose contact is complete from the
rest.

## VL Lab Weekly Report

**Location:** **HIV VIRAL LOAD → Management → VL Lab Weekly Report**

Two reports on one page.

| Report | Content |
|---|---|
| VL Lab Weekly Report | Testing activity for the selected period, defaulting to the last 7 days |
| VL Lab Weekly Report, Female | The same activity for female patients, broken down by age |

Both export to a spreadsheet.

## Sample Rejection Report

**Location:** **HIV VIRAL LOAD → Management → Sample Rejection Report**

Lists rejected samples with the rejection reason for each. Exports to a
spreadsheet.

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

The breakdown groups samples by **Collection Facility**, **Testing Lab** or
**Implementing Partner**.
