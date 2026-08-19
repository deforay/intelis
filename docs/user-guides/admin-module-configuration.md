# How to maintain the request form lists

This guide maintains the dropdown lists on the request forms. Each test module
keeps its own lists, and a few lists are shared by every module.

An option a user cannot find on the request form is almost always an inactive
entry, or an entry added under a different module.

## Before starting

- An account with administrator rights

## One config section per module

The ADMIN menu carries one config section per module the installation runs. An
installation running one module carries one section.

| Config section | Lists it holds |
|---|---|
| VL Config | Sample Type, Rejection Reasons, Test Reasons, Results, ART Regimen, Test Failure Reasons, Recommended Corrective Actions |
| EID Config | Sample Type, Rejection Reasons, Test Reasons, Results |
| TB Config | Sample Type, Rejection Reasons, Test Reasons, Results |
| CD4 Config | Sample Type, Rejection Reasons, Test Reasons |
| Covid-19 Config | Sample Type, Rejection Reasons, Test Reasons, Results, Symptoms, Co-morbidities, Recommended Corrective Actions, QC Test Kits |
| Hepatitis Config | Sample Type, Rejection Reasons, Test Reasons, Results, Co-morbidities, Risk Factors |
| Other Lab Tests Config | Sample Types, Testing Reasons, Sample Rejection Reasons, Test Failure Reasons, Symptoms, Test Result Units, Test Methods, Test Categories, Test Type Configuration |

Adding a sample type under one module does not add it to another module's form.
Add it under each module that needs it.

## What each list controls

| List | Controls |
|---|---|
| Sample Type | The specimen types offered on the request form |
| Rejection Reasons | The reasons offered when rejecting a sample |
| Test Reasons | The indications for testing |
| Results | The result values for qualitative reporting |
| Test Failure Reasons | The reasons offered when a test fails |
| Symptoms, Co-morbidities, Risk Factors | The clinical checklists on the request form |
| ART Regimen | The regimen choices on the viral load request form |
| Recommended Corrective Actions | The actions suggested on high viral load results |
| QC Test Kits | The test kits offered for quality control records |
| Test Result Units, Test Methods, Test Categories | The properties available to a custom test type |

## Add an entry to a list

1. Open the list page under its module's config section.
2. Select the add option.
3. Enter the name.
4. Save.
5. Open the request form and confirm the entry appears in its dropdown.

Every list works the same way.

## Retire an entry

1. Open the list page.
2. Edit the entry.
3. Set the status to inactive.
4. Save.

Set entries inactive rather than deleting them. An inactive entry disappears from
the form and stays readable on the records that already use it. Deleting it
leaves those records unreadable.

## Maintain the shared lists

Four lists sit under **ADMIN → System Configuration** and serve every module.

| Page | Controls |
|---|---|
| Geographical Divisions | Provinces and districts |
| Implementation Partners | The partner list on the request form |
| Funding Sources | The funder list on the request form |
| Lab Storage | The freezers offered on the storage page |

For geographical divisions, leave the parent blank when adding a province. Set
the parent to a province when adding a district. A district added without a
parent does not appear under any province on the request form.

Renaming or removing a province or district breaks the facilities under it, and
the geographic filters on every report stop matching. Agree those changes with
the national team first.

## Configure a custom test type

**ADMIN → Other Lab Tests Config → Test Type Configuration** defines a test type
that is not one of the built-in modules.

The other lists under Other Lab Tests Config supply what that test type can use:
its result units, its test method, and its test category. Create those entries
before creating the test type that refers to them.

## Confirm it worked

| Change | Check |
|---|---|
| New entry | The entry appears in its dropdown on the request form |
| Retired entry | The entry leaves the form and stays readable on an existing record |
| New district | It appears under its province on the request form |
| New custom test type | It appears in the Other Lab Tests request form |
