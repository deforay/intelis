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

<!-- SCREENSHOT NEEDED
     Page: ADMIN → Access Control → Users → Add User
     Capture: the form with name, role, testing lab, and login ID filled
     Highlight: the Role and Testing Lab fields
-->

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

Set the **Test Type**. A facility not linked to viral load does not appear in the
facility list on the viral load request form. This is the usual reason a facility
is "missing" from the form.

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

<!-- SCREENSHOT NEEDED
     Page: ADMIN → Facilities → (a testing lab) → Interface Tool Connections
     Capture: a generated connection code with its expiry, and the connected installations table below
     Highlight: the three code groups and the InteLIS URL
-->

## Maintain the viral load option lists

The dropdowns on the viral load request form come from lists under **ADMIN → VL
Config**.

| List | Controls |
|---|---|
| ART Regimen | The regimen choices on the request form |
| Rejection Reasons | The reasons offered when rejecting a sample |
| Sample Type | The specimen types |
| Results | The result values for qualitative reporting |
| Test Reasons | The indications for testing |
| Test Failure Reasons | The reasons offered when a test fails |
| Recommended Corrective Actions | The actions suggested on high viral load results |

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

Two settings deserve care.

**Same user can Review and Approve.** Turning this on lets one person enter and
sign off their own result. Turn it off wherever staffing allows.

**Auto Approve Interface Results.** Turning this on releases analyzer results
without a human check. It is safe where the analyzer is trusted and the batch
workflow is followed. It is not safe where Sample IDs are entered on the analyzer
by hand.

## Check who did what

| Page | Answers |
|---|---|
| **ADMIN → Monitoring → User Activity Log** | Which pages a user opened, and when |
| **ADMIN → Monitoring → Audit Trail** | Which field changed, from what to what, by whom |
| **ADMIN → Monitoring → Log File Viewer** | System messages, for support requests |

Use the Audit Trail when a result is questioned. It shows the change history for
the record.

## Confirm it worked

| Change | Check |
|---|---|
| New user | The user signs in and sees the expected menu |
| Role change | Sign in as a user with that role, or check the Permission filter on the Roles page |
| New facility | The facility appears in the facility list on the viral load request form |
| New analyzer | The analyzer appears in Testing Platform when creating a batch |
| Interface Tool connection | The installation shows under Connected Installations with a recent Last Seen |
| Option list entry | The entry appears in its dropdown on the request form |
