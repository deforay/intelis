---
description: Add, retire and locate the dropdown list entries used on request forms for each module, and set up a Custom Test type.
audience: [lab-admin, system-admin]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---

# How to maintain the request form lists

Add, correct and retire the options offered in the dropdown lists of the
request forms. Each module keeps its own lists. A few lists are shared by every
module.

An option a user cannot find on the request form is almost always inactive, or
was added under a different module.

## Before starting

- An account with administrator rights

## Where each list is

Each test the installation runs has its own tab on **ADMIN → Test Settings**.
Each tab lists that test's pages.

| Test Settings tab | Lists |
| --- | --- |
| HIV Viral Load | Sample Types, Test Reasons, Results, Rejection Reasons, Test Failure Reasons, Recommended Corrective Actions, ART Regimen |
| EID | Sample Types, Test Reasons, Results, Rejection Reasons, Recommended Corrective Actions |
| COVID-19 | Sample Types, Test Reasons, Results, Rejection Reasons, Recommended Corrective Actions, Comorbidities, Symptoms, QC Test Kits |
| Hepatitis | Sample Types, Test Reasons, Results, Rejection Reasons, Comorbidities, Risk Factors |
| TB | Sample Types, Test Reasons, Results, Rejection Reasons, Recommended Corrective Actions |
| CD4 | Sample Types, Test Reasons, Rejection Reasons |
| Custom Tests | Test Type Configuration, Sample Types, Test Reasons, Rejection Reasons, Test Failure Reasons, Symptoms, Test Result Units, Test Methods, Test Categories |

The lists that serve every module sit under **ADMIN → Reference Lists**:
Geographical Divisions, Implementation Partners and Funding Sources. Lab
Storage sits under **ADMIN → Settings**.

A sample type added on the HIV Viral Load tab does not reach the EID form. Add
it on the tab of each test that needs it.

## Add an entry

**Choose the installation type, then follow its steps from top to bottom.**

=== "STS or standalone"

    1. Go to **ADMIN → Test Settings**, select the test's tab, then select the
       list. For example, **ADMIN → Test Settings**, the **HIV Viral Load** tab,
       then **Sample Types**. For a shared list, go to **ADMIN → Reference
       Lists** and select the list.
    2. Select the add button at the top right of the list. It is named after
       the list, such as **Add VL Sample Type**.

        ??? info "Add button on each list"

            | List | Button |
            | --- | --- |
            | HIV Viral Load tab | **Add VL Sample Type**, **Add VL Sample Rejection Reasons**, **Add VL Test Reasons**, **Add VL Results**, **Add VL ART Regimen**, **Add VL Test Reason** (on Test Failure Reasons), **Add Recommended Corrective Actions** |
            | EID tab | **Add EID Sample Type**, **Add EID Sample Rejection Reasons**, **Add EID Test Reasons**, **Add EID Results** |
            | COVID-19 tab | **Add Covid-19 Sample Type**, **Add Covid-19 Sample Rejection Reasons**, **Add Covid-19 Test Reasons**, **Add Covid-19 Results**, **Add Covid-19 Symptoms**, **Add Covid-19 Co-morbidities**, **Add New Covid-19 QC Test Kit** |
            | Hepatitis tab | **Add Hepatitis Sample Type**, **Add Hepatitis Sample Rejection Reasons**, **Add Hepatitis Test Reasons**, **Add Hepatitis Results**, **Add Hepatitis Co-morbidities**, **Add Hepatitis Risk Factors** |
            | TB tab | **Add TB Sample Type**, **Add TB Sample Rejection Reasons**, **Add TB Test Reasons**, **Add TB Results** |
            | CD4 tab | **Add CD4 Sample Type**, **Add CD4 Sample Rejection Reasons**, **Add CD4 Test Reasons** |
            | Custom Tests tab | **Add Sample Type**, **Add Testing Reason**, **Add Test Failure Reason**, **Add Symptoms**, **Add Sample Rejection Reasons**, **Add Test Result Units**, **Add Test Methods**, **Add Test Categories**, **Add Test Type** |
            | Reference Lists | **Add New Geographical Divisions**, **Add Implementation Partners**, **Add Funding Sources** |

    3. Enter the name of the entry, and its code where the form asks for one.
    4. Set the status to **Active**.
    5. Select **Submit**.
    6. Open the request form. The entry appears in its dropdown.

    ??? info "Adding a district"

        On **Geographical Divisions**, leave **Parent Geographical Division**
        blank when adding a province. Set it to the province when adding a
        district. A district with no parent appears under no province on the
        request form.

    ??? info "On a standalone installation"

        On a standalone installation, Geographical Divisions and the lists on
        the Custom Tests tab (except Test Type Configuration) show no add button.
        They are maintained on the STS only.

=== "LIS"

    A LIS shows these lists without add buttons. The lists come from the STS.

    1. Ask the STS administrator to add the entry on the STS.
    2. Once it is added, select **Force Remote Sync** at the bottom right of any
       LIS page.
    3. Open the request form. The entry appears in its dropdown.

    ??? info "Lab Storage is maintained by the lab"

        Lab Storage is maintained by the lab: on the LIS, or on the STS by a
        testing-lab user. **ADMIN → Settings → Lab Storage**
        belongs to the lab. Select **Add Lab Freezer/Storage** there to add a
        freezer.

## Retire an entry

Set entries inactive instead of deleting them. An inactive entry leaves the
form, and stays readable on the records that already use it.

1. Open the list, as in step 1 of [Add an entry](#add-an-entry).
2. In the entry's row, set the status to **Inactive**.
3. Select **OK** to confirm.
4. Open the request form. The entry is no longer offered.

??? info "If the row has no status list"

    Select **Edit** on the row, set the status to **Inactive**, and select
    **Submit**. On a LIS, the status cannot be changed. Retire the entry on the
    STS. On a standalone installation, retire entries on the Custom Tests tab
    on the STS.

??? warning "Renaming or removing a province or district"

    The facilities under it lose their link, and the location filters on every
    report stop matching. Agree the change with the national team first.

    To fold a duplicate province or district into another, use **Merge
    Geographical Divisions** on the Geographical Divisions list. It is on the
    STS only.

## Set up a Custom Test

A Custom Test is a test type that is not one of the built-in modules. It is
defined under **ADMIN → Test Settings**, on the **Custom Tests** tab, under
**Test Type Configuration**.

1. Add at least one entry under **Test Methods**. Every result group needs
   one. Add units under **Test Result Units** if results carry a unit.
2. Add the sample types, test reasons and rejection reasons it needs, under
   the matching lists on the Custom Tests tab.
3. Go to **ADMIN → Test Settings**, select the **Custom Tests** tab, then
   select **Test Type Configuration**.
4. Select **Add Test Type** and define the test, or select **Import Test
   Type** to load one exported from another installation.

## Confirm it worked

| Change | Check |
| --- | --- |
| New entry | It appears in its dropdown on the request form |
| Retired entry | It leaves the form and stays readable on an existing record |
| New district | It appears under its province on the request form |
| New Custom Test | It appears in the Custom Tests request form |
