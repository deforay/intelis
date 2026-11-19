---
description: Lists the reports for EID, TB, Hepatitis, CD4, Custom Tests and COVID-19, with where to find each one and what it shows.
audience: [lab-staff, lab-supervisor, lab-admin]
module: [eid, tb, hepatitis, cd4, custom-tests, covid19]
type: reference
reviewed: 2026-11-19
reviewed_against: 5.8.8
---

# Reports for other tests

[Viral load reports](reports.md) covers viral load. This page
lists the reports under the **Management** menu of every other test, and the
test's tab on the dashboard.

Applies to InteLIS 5.8.8.

Every report page uses the same controls. Set the filters, select **Search**, and
use the export control if the page has one. See
[How to sign in and navigate InteLIS](signing-in.md) for those controls.

A menu entry shows only when the installation runs that test and the role has
permission for that report.

## Early infant diagnosis (EID)

### Dashboard

**Location:** **DASHBOARD**, **Early Infant Diagnosis** tab

Shows the same counts and charts as the viral load tab. See
[Dashboard](reports.md#dashboard).

When **VL Monthly Target** is on, the tab also shows a line saying how many
EID testing labs did not meet the monthly test target. Its **more** link opens
the [EID Testing Target Report](#eid-testing-target-report).

### Sample Status Report

**Location:** **EARLY INFANT DIAGNOSIS (EID) → Management → Sample Status Report**

Works like the viral load version, with an **EID Results** chart in place of VL
Suppression. See [Sample Status Report](reports.md#sample-status-report).

### Export Results

**Location:** **EARLY INFANT DIAGNOSIS (EID) → Management → Export Results**

Works like the viral load version. See [Export Results](reports.md#export-results).

### Print Result

**Location:** **EARLY INFANT DIAGNOSIS (EID) → Management → Print Result**

Works like the viral load version. See [Print Result](reports.md#print-result).

### Sample Rejection Report

**Location:** **EARLY INFANT DIAGNOSIS (EID) → Management → Sample Rejection
Report**

Works like the viral load version. See
[Sample Rejection Report](reports.md#sample-rejection-report).

### Clinic Reports

**Location:** **EARLY INFANT DIAGNOSIS (EID) → Management → Clinic Reports**

Works like the viral load version. See [Clinic Reports](reports.md#clinic-reports).
[Clinic report tabs per test](#clinic-report-tabs-per-test) lists the tabs.

### EID Testing Target Report

**Location:** **EARLY INFANT DIAGNOSIS (EID) → Management → EID Testing Target
Report**

Compares the samples each testing lab tested in a month against the lab's
monthly target.

The target is the **Monthly Target** set for EID on the testing lab's record
under **ADMIN → Facilities**. See
[Set up a testing lab](admin-facilities.md#set-up-a-testing-lab).

| Filter | Content |
|---|---|
| **Sample Test Date** | The period, by the date the sample was tested |
| **Lab Name** | One or more testing labs |
| **Target Type** | **Monthly Targets not met**, **Monthly Targets met** or **Show all** |

Each row is one testing lab, one requesting facility and one month of testing.

| Column | Content |
|---|---|
| **Lab Name** | The testing lab |
| **Facility Name** | The facility that sent the samples |
| **Month** | The month the samples were tested |
| **Number of Samples Received** | Samples in the row with no test date |
| **Number of Samples Rejected** | Rejected samples in the row |
| **Number of Samples Tested** | Samples in the row. **Target Type** compares this count with the target |
| **Monthly Test Target** | The lab's monthly target for EID |

The report counts only samples with a result. **Export to excel** downloads the rows.

### PMTCT Cascade Report

**Location:** **EARLY INFANT DIAGNOSIS (EID) → Management → PMTCT Cascade Report**

Follows HIV-exposed children and their mothers. It links each EID request to
the mother's viral load requests. It then shows how many mothers have a high
viral load and how many children test positive.

The report pairs a child with a mother when the **Mother ID** on the EID request
matches the ART number on a viral load request. Each child counts once, even with
more than one EID test. Cancelled samples are left out.

| Filter | Content |
|---|---|
| **EID Sample Collection Date** | The period, by the EID sample's collection date. Opens on the last 12 months |
| **Province** | The province of the EID request |
| **Testing Lab** | The EID testing lab |
| **Match Status** | **All children**, **Matched (mother has VL test)** or **Not matched**. Changes only the **Line-Level Records** table |

The mother's viral load tests are read from any date, not only the selected
period. Tests before the birth usually fall outside the period.

A high viral load is 1000 cp/mL or more. Suppressed is below 1000 cp/mL. The
report uses the mother's latest viral load result.

The **PMTCT Snapshot** opens with four cards.

| Card | Content |
|---|---|
| **Maternal Viral Suppression** | Matched mothers whose latest result is suppressed, as a share of the mothers with a result |
| **Mothers with High VL (latest ≥1000 cp/mL)** | Matched mothers whose latest result is high. **View mothers** lists them |
| **HIV-Positive Children** | Matched children with a positive EID result in the period. **View children** lists them |
| **Positive Children: Mother High VL During Pregnancy** | Positive children whose mother had a high result in the 9 months before the child's date of birth |

Two rows of boxes follow the cards. Read each row from left to right. The
percentage under a box is its share of the box to its left.

| Row | Boxes |
|---|---|
| **Maternal VL Risk** | **Children w/ Mother ID**, **Matched Mothers**, **Mothers w/ VL Result**, **Mothers w/ High VL ≥1000** |
| **Infant EID Outcome** | **Matched Children**, **With EID Result**, **HIV-Positive** |

**Matched Mothers** has no percentage, because several children can have the
same mother. The notes under the rows count the children not matched to a
mother, and the mothers at high viral load who are pregnant or breastfeeding.

The **Line-Level Records** table lists the records behind the numbers. An EID
test appears once for each viral load test of the mother.

**Export to Excel** downloads a workbook with four sheets: **Summary**, **EID
Data**, **VL Data** and **Positive Children History**.

The page explains its terms under **How to read this report**.

### Sample Ageing Report

**Location:** **EARLY INFANT DIAGNOSIS (EID) → Management → Sample Ageing Report**

Opens with EID selected. See [Sample Ageing Report](reports.md#sample-ageing-report).

## Tuberculosis (TB)

### Dashboard

**Location:** **DASHBOARD**, **Tuberculosis** tab

Shows the same counts and charts as the viral load tab. See
[Dashboard](reports.md#dashboard). The tab also holds the
[Cascade Funnel](#cascade-funnel).

When **VL Monthly Target** is on, the tab also shows a line saying how many
TB testing labs did not meet the monthly test target. Its **more** link opens the
[TB Testing Target Report](#tb-testing-target-report).

### Cascade Funnel

**Location:** **DASHBOARD**, **Tuberculosis** tab

Shows the funnel and the referral branch of the
[TB Cascade Report](#tb-cascade-report), for the date range set on the tab.

**Full Cascade Report** opens the TB Cascade Report.

### Sample Status Report

**Location:** **TUBERCULOSIS → Management → Sample Status Report**

Works like the viral load version, with a **TB Final Result Counts** chart in
place of VL Suppression. See
[Sample Status Report](reports.md#sample-status-report).

### Print Result

**Location:** **TUBERCULOSIS → Management → Print Result**

Works like the viral load version. See [Print Result](reports.md#print-result).

### Export Results

**Location:** **TUBERCULOSIS → Management → Export Results**

Works like the viral load version. See [Export Results](reports.md#export-results).

### Sample Rejection Report

**Location:** **TUBERCULOSIS → Management → Sample Rejection Report**

Works like the viral load version. See
[Sample Rejection Report](reports.md#sample-rejection-report).

### Clinic Reports

**Location:** **TUBERCULOSIS → Management → Clinic Reports**

Works like the viral load version. See [Clinic Reports](reports.md#clinic-reports).
[Clinic report tabs per test](#clinic-report-tabs-per-test) lists the tabs.

### TB Cascade Report

**Location:** **TUBERCULOSIS → Management → TB Cascade Report**

Shows how far TB samples got, from registration to a dispatched or printed
result, including samples referred to another lab.

| Filter | Content |
|---|---|
| **Sample Collection Date** | The period, by collection date. Opens on the last 12 months |
| **Province** | The province of the request |
| **Testing Lab** | The testing lab |
| **Collection Site** | The facility that collected the sample |
| **Test Type** | One TB test type, or **-- Any --** |
| **Final Result Entered** | **Yes**, **No** or both |

The page has four parts.

| Part | Content |
|---|---|
| Cards | **Total Samples**, then where they are now: **In Pipeline**, **Accepted** and **Rejected / Failed** |
| Stats line | Average days from collection to reception and from reception to testing, and the results dispatched or printed |
| **Cascade Funnel** | The samples at each stage, read from left to right |
| Referral branch | Samples referred to another lab, then received, tested and accepted there |

The percentage under a funnel box is its share of the box to its left. A large
drop between two boxes shows where samples stop moving.

| Stage | Content |
|---|---|
| **Registered** | Samples registered in the period |
| **Received at Lab** | The sample has a reception date at the lab, or was tested |
| **Tested** | The sample has a test date, a result, or a result on one of its tests. A rejected sample does not count as tested |
| **Final Result Entered** | The final result is entered, or the result is marked final |
| **Accepted** | The final result is approved |
| **Dispatched / Printed** | The accepted result was dispatched or printed |

The referral branch has the stages **Referred**, **Received at Target**,
**Tested at Target** and **Accepted at Target**.

**In Pipeline** counts each sample in one place only. The places are: at the
collection site, at the lab and not tested, awaiting approval, or referred and
not yet received.

Under **Explore:**, three buttons open tables that use the same filters.

| Button | Content |
|---|---|
| **Per-Lab Breakdown** | Totals per lab. Originator samples were registered at the lab. Referred-in samples came from another lab |
| **Referral Pairs** | Samples per referring lab and receiving lab, with how far each got |
| **Detail Samples** | One row per sample, test and lab |

The page explains its terms under **How to read this report**.

### TB Testing Target Report

**Location:** **TUBERCULOSIS → Management → TB Testing Target Report**

Compares the samples each testing lab tested in a month against the lab's
monthly target. Added in InteLIS 5.8.5.

Has the same filters and columns as the
[EID Testing Target Report](#eid-testing-target-report). The target is the
**Monthly Target** set for TB on the testing lab's record under **ADMIN →
Facilities**. Cancelled samples are left out.

A role that could open the TB **Clinic Reports** received this report on the
upgrade to 5.8.5.

### Sample Ageing Report

**Location:** **TUBERCULOSIS → Management → Sample Ageing Report**

Opens with TB selected. See [Sample Ageing Report](reports.md#sample-ageing-report).

## Hepatitis

### Dashboard

**Location:** **DASHBOARD**, **Hepatitis** tab

Shows the same counts and charts as the viral load tab. See
[Dashboard](reports.md#dashboard).

When **VL Monthly Target** is on, the tab also shows a line saying how many
hepatitis testing labs did not meet the monthly test target. Its **more** link
opens the [Hepatitis Testing Target Report](#hepatitis-testing-target-report).

### Sample Status Report

**Location:** **HEPATITIS → Management → Sample Status Report**

Works like the viral load version. See
[Sample Status Report](reports.md#sample-status-report).

### Export Results

**Location:** **HEPATITIS → Management → Export Results**

Works like the viral load version. See [Export Results](reports.md#export-results).

### Print Result

**Location:** **HEPATITIS → Management → Print Result**

Works like the viral load version. See [Print Result](reports.md#print-result).

### Sample Rejection Report

**Location:** **HEPATITIS → Management → Sample Rejection Report**

Works like the viral load version. See
[Sample Rejection Report](reports.md#sample-rejection-report).

### Clinic Reports

**Location:** **HEPATITIS → Management → Clinic Reports**

Works like the viral load version. See [Clinic Reports](reports.md#clinic-reports).
[Clinic report tabs per test](#clinic-report-tabs-per-test) lists the tabs.

### Hepatitis Testing Target Report

**Location:** **HEPATITIS → Management → Hepatitis Testing Target Report**

Has the same filters and columns as the
[EID Testing Target Report](#eid-testing-target-report), for hepatitis samples.

### Sample Ageing Report

**Location:** **HEPATITIS → Management → Sample Ageing Report**

Opens with hepatitis selected. See
[Sample Ageing Report](reports.md#sample-ageing-report).

## CD4

### Dashboard

**Location:** **DASHBOARD**, **CD4** tab

Shows the same counts and charts as the viral load tab. See
[Dashboard](reports.md#dashboard).

### Sample Status Report

**Location:** **CD4 → Management → Sample Status Report**

Works like the viral load version. See
[Sample Status Report](reports.md#sample-status-report).

### Export Results

**Location:** **CD4 → Management → Export Results**

Works like the viral load version. See [Export Results](reports.md#export-results).

### Print Result

**Location:** **CD4 → Management → Print Result**

Works like the viral load version. See [Print Result](reports.md#print-result).

### Sample Rejection Report

**Location:** **CD4 → Management → Sample Rejection Report**

Works like the viral load version. See
[Sample Rejection Report](reports.md#sample-rejection-report).

### Clinic Report

**Location:** **CD4 → Management → Clinic Report**

Works like the viral load **Clinic Reports**. See
[Clinic Reports](reports.md#clinic-reports). The tabs are listed in
[Clinic report tabs per test](#clinic-report-tabs-per-test) lists the tabs.

### Sample Ageing Report

**Location:** **CD4 → Management → Sample Ageing Report**

Opens with CD4 selected. See [Sample Ageing Report](reports.md#sample-ageing-report).

## Custom Tests

### Dashboard

**Location:** **DASHBOARD**, **Other Tests** tab

Shows the same counts and charts as the viral load tab. See
[Dashboard](reports.md#dashboard).

### Sample Status Report

**Location:** **OTHER LAB TESTS → Management → Sample Status Report**

Works like the viral load version. See
[Sample Status Report](reports.md#sample-status-report).

### Export Results

**Location:** **OTHER LAB TESTS → Management → Export Results**

Works like the viral load version. See [Export Results](reports.md#export-results).

### Print Result

**Location:** **OTHER LAB TESTS → Management → Print Result**

Works like the viral load version. See [Print Result](reports.md#print-result).

### Sample Rejection Report

**Location:** **OTHER LAB TESTS → Management → Sample Rejection Report**

Works like the viral load version. See
[Sample Rejection Report](reports.md#sample-rejection-report).

### Clinic Reports

**Location:** **OTHER LAB TESTS → Management → Clinic Reports**

Works like the viral load version, with fewer tabs. See
[Clinic Reports](reports.md#clinic-reports). The tabs are listed in
[Clinic report tabs per test](#clinic-report-tabs-per-test) lists the tabs.

### Sample Ageing Report

**Location:** **OTHER LAB TESTS → Management → Sample Ageing Report**

Opens with Custom Tests selected. See
[Sample Ageing Report](reports.md#sample-ageing-report).

## COVID-19

| Report | Location |
|---|---|
| Dashboard | **DASHBOARD**, **Covid-19** tab |
| Sample Status Report | **COVID-19 → Management → Sample Status Report** |
| Export Results | **COVID-19 → Management → Export Results** |
| Print Result | **COVID-19 → Management → Print Result** |
| Sample Rejection Report | **COVID-19 → Management → Sample Rejection Report** |
| Clinic Reports | **COVID-19 → Management → Clinic Reports** |
| COVID-19 Testing Target Report | **COVID-19 → Management → COVID-19 Testing Target Report** |
| Sample Ageing Report | **COVID-19 → Management → Sample Ageing Report** |

## Clinic report tabs per test

The tabs work as described in [Clinic Reports](reports.md#clinic-reports).
**Positivity** takes the place of **High Viral Load**.

| Test | Tabs |
|---|---|
| EID, TB, Hepatitis, CD4, COVID-19 | **Positivity**, **Sample Rejection**, **Results Not Available**, **Data Quality Check**, **Sample Testing**, **Patient Test History** |
| Custom Tests | **Sample Testing**, **Patient Test History**, **Data Quality Check** |
