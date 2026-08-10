# Viral load reports

This page describes every report under **HIV VIRAL LOAD → Management**, and the
viral load content of the dashboard and the admin monitoring pages.

Applies to InteLIS 5.6.2.

Every report page uses the same controls. Set the filters, select **Search**, and
use the export control where one is offered. See
[How to sign in and navigate InteLIS](signing-in.md) for those controls.

## Dashboard

**Location:** **DASHBOARD**

Shows counts of samples registered, tested, rejected, and without a result, plus
facility-wise performance. One tab per test type enabled on the installation.

Covers the last 30 days by default. The date range control at the top of the
page changes the period.

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
| High Viral Load Report | Patients whose result is above the viral load threshold set by the administrator |
| High VL and Virologic Failure Report | High viral load results with the virologic failure assessment |
| Sample Rejection Report | Rejected samples with their rejection reason |
| Results Not Available Report | Samples registered with no result recorded |
| Data Quality Check Report | Records with missing or inconsistent data |
| Sample Testing Report | Samples tested over the selected period |
| Patient Test History Report | Every test recorded for one patient |

The High Viral Load Report supports contact notes. A user records the follow-up
made with the facility and marks the contact complete.

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

Compares samples tested against the monthly testing target. The administrator
sets the target under **ADMIN → System Configuration → General Configuration**.

## Freezer/Storage Reports

**Location:** **HIV VIRAL LOAD → Management → Freezer/Storage Reports**

Gives the current freezer position of each sample and its storage history.
Exports to a spreadsheet.

See [How to record where a sample is stored](store-samples.md).

## Lab Performance Indicators

**Location:** **ADMIN → Monitoring → Lab Performance Indicators**

Reports turnaround time, volume by entry mode, failure rate, rejection rate, and
repeat patients, across every test type on the installation.

The failure rate counts test events. A sample tested twice counts as two events,
so a retest after a failure does not hide the original failure.

## Source of Requests

**Location:** **ADMIN → Monitoring → Source of Requests**

Reports how requests entered the system, separating requests typed into InteLIS
from requests received from other systems.

## Reports elsewhere

| Report | Location | Content |
|---|---|---|
| User Activity Log | **ADMIN → Monitoring → User Activity Log** | Pages each user opened and actions taken |
| Audit Trail | **ADMIN → Monitoring → Audit Trail** | Field-level record of changes to data |
| API History | **ADMIN → Monitoring → API History** | Exchanges with connected systems |
| Lab Sync Status | **ADMIN → Monitoring → Lab Sync Status** | Whether each lab's data has reached the central system |
| Test Results Metadata | **ADMIN → Monitoring → Test Results Metadata** | Detail recorded alongside each result |
