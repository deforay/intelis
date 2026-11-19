---
description: Lists every viral load report, the dashboard and the Sample Ageing Report, with what each one counts and where to find it.
audience: [lab-staff, lab-supervisor, requesting-facility]
module: [vl]
type: reference
reviewed: 2026-11-19
reviewed_against: 5.8.8
---

# Viral load reports

This page describes every report under **HIV VIRAL LOAD → Management**, the
viral load content of the dashboard, and the Sample Ageing Report.

Applies to InteLIS 5.8.8.

Every report page uses the same controls. Set the filters, select **Search**, and
use the export control where one is offered. See
[How to sign in and navigate InteLIS](signing-in.md) for those controls.

**Lab Performance Indicators**, **Instrument Activity** and **Sample Referral
Network** sit under **ADMIN → Reports**. They are described in
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

Two settings above **Download** shape the sheet:

| Setting | Effect |
|---|---|
| **Export with Patient ID and Name** | **Yes** keeps the patient's ART number, name and contact number. **No** leaves them out, for data that leaves the lab for analysis |
| **Include lab and audit details** | Adds the batch, platform and analyzer run. Also adds who tested, reviewed and approved the result, the sample status, the turnaround, and who created and last changed the record. Left clear, the sheet stays compact |

The file downloads when it is ready. A large export runs in the background, so
work can go on meanwhile. Progress shows in the **Exports** menu at the top of
the page. That menu also offers **Download again** for 5 minutes after the
export finishes.

See [How to release results to the requesting facility](release-results.md).

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

The page title is **VL Quarterly Monitoring Tool**. It fills in the quarterly
monitoring questionnaire for the samples collected in a period, commonly a
quarter.

Filters are **Sample Collection Date**, **Region/Province/State**,
**District/County** and **Lab Name**. **Search** lists the matching samples.
**Export to excel** downloads the questionnaire as a spreadsheet:

| Question | Content |
|---|---|
| Q1 to Q1.5 | Viral load tests reported, split into suppressed (< 1000 copies/mL) and not, by sex, age, pregnancy and breastfeeding |
| Q2, Q2.1 | Backlog: samples collected over a month ago and not yet tested, by sample type |
| Q3 | Average days from sample collection to test |
| Q7 | Invalid results per month |
| Q4, Q5, Q6, Q8 | Procurement, EID testing and equipment breakdown. Left empty for the lab to fill in |

## VL Testing Target Report

**Location:** **HIV VIRAL LOAD → Management → VL Testing Target Report**

Compares samples tested against each testing lab's monthly target.

The targets are set per testing lab, on the lab's record under **ADMIN →
Facilities**.

| Field | Content |
|---|---|
| **Monthly Target** | Samples the lab is expected to test each month |
| **Suppressed Monthly Target** | Suppressed results expected each month. Viral load only |

**ADMIN → Settings → General Configuration** holds only the **VL
Monthly Target** switch. It shows or hides the target charts on the dashboard.

## Freezer/Storage Reports

**Location:** **HIV VIRAL LOAD → Management → Freezer/Storage Reports**

Gives the current freezer position of each sample and its storage history.
Exports to a spreadsheet.

See [How to record where a sample is stored](store-samples.md).

## Sample Ageing Report

**Location:** **HIV VIRAL LOAD → Management → Sample Ageing Report**. Every
other test has the same entry under its own **Management** menu. The role needs
the **Sample Ageing Report** permission.

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
