# How to administer InteLIS

This guide covers the tasks an in-lab administrator does inside InteLIS:
creating logins, defining what each role can reach, maintaining facilities and
analyzers, and keeping the dropdown lists on the request form correct.

It does not cover installing, updating, or backing up InteLIS. Those are server
tasks and live under the installation and maintenance guides.

## Before starting

- An account with administrator rights

Everything below sits under **ADMIN** in the sidebar.

## Add a user

InteLIS has no self-registration. Every login is created by an administrator.

1. Go to **ADMIN → Access Control → Users**.
2. Select **Add User**.
3. Fill in the details.

| Field | What to enter |
|---|---|
| Full Name | The user's name as it appears on reports and in the activity log |
| Email | The user's address |
| Phone Number | The user's number |
| Role | The role that sets what this user can reach |
| Testing Lab | The lab this user works in |
| Mobile App Access | Whether the account can use the mobile app |
| Signature | A signature image, used on result PDFs. 100 by 100 pixels |
| Login ID | The identifier the user signs in with |
| Password and Confirm Password | The starting password |

4. Select **Submit**.
5. Give the Login ID and password to the user directly.

Set the **Testing Lab** on every user. It scopes what the user sees to their own
lab's work.

Never share one login between staff. The activity log and the tester, reviewer,
and approver names on reports record whoever was signed in. A shared login makes
those records worthless.

To disable a departing user, edit the account and set the status to inactive. Do
not reuse the login for someone else. The old records stay attached to the name.

## Add or change a role

A role is a named set of permissions. Users get their permissions from their
role, never individually.

1. Go to **ADMIN → Access Control → Roles**.
2. Select **Add Role**, or select **Edit** on an existing role.
3. Fill in the details.

| Field | What to enter |
|---|---|
| Role Name | A name staff recognise, such as Lab Technician |
| Role Code | A short unique code |
| Landing Page | The page users with this role open on after signing in |
| Access Type | **Testing Lab** for lab staff, **Collection Site** for facility staff |
| Status | Active or inactive |
| Privileges | Tick each page this role can reach |

4. Select **Submit**.

Set the **Landing Page** to the page the role uses most. Data entry staff open
straight onto the request form.

Use the permission search box to find a page rather than scrolling the list.

Give each role the permissions its work needs and no more. Approval is the check
on result quality. A role that can both enter and approve its own results
removes that check.

To find out which roles hold a given permission, use the **Permission** filter on
the Roles page.

## Add a facility

Every sample is attached to a health facility and a testing lab. Both are
records on the Facilities page.

1. Go to **ADMIN → Facilities**.
2. Select **Add Facility**.
3. Fill in the details.

| Field | What to enter |
|---|---|
| Facility Name | The name staff will search for |
| Facility Code | The national unique code |
| Facility Type | Health facility or testing lab |
| Email(s) | Addresses for emailed results, separated by commas |
| Report Email(s) | Addresses for report distribution |
| Testing Point(s) | The service points, such as VCT or PMTCT |
| Lab Manager | The contact person |
| Province/State and District/County | The location |
| Linked Hub Name | The hub this facility routes samples through, if any |
| Test Type | Every test type this facility takes part in |

4. Select **Submit**.

Set the **Test Type**, and tick every test type the facility takes part in. A
facility not linked to a test type does not appear in the facility list on that
test type's request form. A facility ticked for one test type stays missing from
every other test type's form. This is the usual reason a facility is "missing".

For a testing lab, upload the signatories. They appear on result PDFs issued by
that lab.

To find facilities whose province or district is missing or wrongly linked, set
**Show Orphaned Facilities** on the Facilities page. Those facilities behave
unpredictably in the geographic filters until fixed.

To load many facilities at once, use **Bulk Upload**.

## Add an analyzer

1. Go to **ADMIN → System Configuration → Instruments**.
2. Select **Add Instrument**.
3. Enter the name, the test types it runs, and the configuration file that tells
   InteLIS how to read its result files.
4. Select **Submit**.

The configuration file is what makes file import work for that analyzer. Without
it, results exported from the analyzer cannot be read. See
[How to capture viral load results](capture-results.md).

## Connect the Interface Tool

The Interface Tool passes results from an analyzer into InteLIS without anyone
typing them. Each installation of the tool connects to InteLIS once.

1. Go to **ADMIN → Facilities**.
2. Open the testing lab.
3. Scroll to **Interface Tool Connections** at the foot of the page.
4. Select **Generate Connection Code**.
5. Enter the three groups of the code, and the InteLIS URL shown above them,
   into the Interface Tool on the lab computer.

The code expires. The page shows the time remaining. If it expires, generate
another.

Only one code can be outstanding at a time. To start again, cancel the current
code first.

Once connected, the installation appears under **Connected Installations** with a
status and a **Last Seen** time. Use **Last Seen** when results stop arriving.

| Action | When to use it |
|---|---|
| **Reconnect / Reinstall** | The lab computer is rebuilt or the tool is reinstalled |
| **Revoke** | The computer is retired or lost. Other installations are unaffected |

## Maintain the request form lists

Each test module keeps its own lists. They fill the dropdowns on that module's
request form. Adding a sample type under one module does not add it to another
module's form. Add it under each module that needs it.

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

What each list controls:

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
| Test Result Units, Test Methods, Test Categories | The properties available to a custom test type |

Each list works the same way. Open the page, select the add option, enter the
name, and save. To retire an entry, edit it and set the status to inactive.

Set entries inactive rather than deleting them. An inactive entry disappears from
the form but stays readable on the records that already use it.

## Maintain geography, partners, funders, and freezers

| Page | Controls |
|---|---|
| **ADMIN → System Configuration → Geographical Divisions** | Provinces and districts |
| **ADMIN → System Configuration → Implementation Partners** | The partner list on the request form |
| **ADMIN → System Configuration → Funding Sources** | The funder list on the request form |
| **ADMIN → System Configuration → Lab Storage** | The freezers offered on the storage page |

For geographical divisions, leave the parent blank when adding a province. Set
the parent to a province when adding a district. A district added without a
parent does not appear under any province on the request form.

## Change general configuration

**ADMIN → System Configuration → General Configuration** holds the settings that
change how InteLIS behaves across the installation.

| Setting group | Examples |
|---|---|
| Appearance | The report header and logo |
| Formats | Time zone, date format, barcode format |
| Sample IDs | The Sample ID format and prefix, per test type |
| Results | Result PDF layout, the viral load threshold, the monthly testing target |
| Locking | How many days before a sample locks, and how many before it expires |
| Approval | Whether interface results approve automatically, and whether one person may both review and approve |

Change one setting at a time and check the effect. These settings apply to
everyone on the installation at once.

## Changes that need approval before making them

The changes below take effect across the whole installation the moment they are
saved. Setting them back does not undo their effect on the records already
created. Agree each one with the national team first.

| Change | Where | Why it needs approval |
|---|---|---|
| Sample ID format or prefix | General Configuration → Sample IDs | Every sample registered from that moment carries the new format. The samples already registered keep the old one, leaving the lab with two schemes |
| Locking and expiry days | General Configuration → Locking | Decides when a record stops accepting edits. Too short and the lab cannot correct a result. Too long and results stay editable after release |
| Same user can Review and Approve | General Configuration → Approval | Lets one person enter and sign off their own result. Approval is the only check on result quality. Turn it off wherever staffing allows |
| Auto Approve Interface Results | General Configuration → Approval | Releases analyzer results with no human check. Safe where the analyzer is trusted and the batch workflow is followed. Not safe where Sample IDs are typed on the analyzer by hand |
| Role privileges | Access Control → Roles | Applies at once to every user holding the role |
| Deleting a list entry | Any module config page | Set the entry inactive instead. Deleting it leaves the records that used it unreadable |
| Renaming or removing a province or district | System Configuration → Geographical Divisions | The facilities under it lose their link, and the geographic filters on every report stop matching |

## Check who did what

| Page | Answers |
|---|---|
| **ADMIN → Monitoring → User Activity Log** | Which pages a user opened, and when |
| **ADMIN → Monitoring → Audit Trail** | Which field changed, from what to what, by whom |
| **ADMIN → Monitoring → API History** | Whether this installation reached the national server, and how many records went |
| **ADMIN → Monitoring → Interface Machine Activity** | Whether each connected analyzer is still sending |
| **ADMIN → Monitoring → Lab Performance Indicators** | Turnaround time, volumes by entry mode, failure and rejection rates |
| **ADMIN → Monitoring → Log File Viewer** | System messages, for support requests |

Use the Audit Trail when a result is questioned. It shows the change history for
the record.

**Lab Sync Status** and **API Dashboard** appear on the national server only.
They are absent from a lab installation by design.

## Confirm it worked

| Change | Check |
|---|---|
| New user | The user signs in and sees the expected menu |
| Role change | Sign in as a user with that role, or check the Permission filter on the Roles page |
| New facility | The facility appears on the request form of each test type it was ticked for |
| New analyzer | The analyzer appears in Testing Platform when creating a batch |
| Interface Tool connection | The installation shows under Connected Installations with a recent Last Seen |
| Option list entry | The entry appears in its dropdown on the request form |
