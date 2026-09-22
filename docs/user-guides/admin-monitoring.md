---
description: Trace record changes, confirm that data sync and analyzers are working, and read lab performance, referral and page usage reports.
audience: [lab-admin, system-admin, lab-supervisor]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# How to monitor and audit InteLIS

Find who changed a record, check that data and analyzer results are moving, and
read how the lab is performing. The pages sit under **ADMIN → Monitoring**.

## Before starting

- An account whose role holds the Monitoring pages needed. The built-in Admin
  role sees them all
- On the Roles page, **Lab Performance Indicators**, **Interface Machine
  Activity** and **Sample Referral Network** are granted under **Reports**, not
  under Monitoring

??? info "Which pages appear"

    | Installation | Pages |
    | --- | --- |
    | Standalone or LIS | Every page except **Lab Sync Status** and **API Dashboard** |
    | STS | Every page |
    | Cloud | For users without the built-in Admin role: **User Activity Log**, **Audit Trail** and **Log File Viewer** only |

    **Page Usage** appears only for the built-in Admin role, and for roles
    given the **Page Usage** privilege on the Roles page. No other role holds it
    by default.

## Which page answers which question

| Question | Page |
| --- | --- |
| Who changed this sample, and to what? | Audit Trail |
| What did this user do, and when did they sign in? | User Activity Log |
| Which pages do users open, and for how long? | Page Usage |
| Did data reach the STS? | API History on a LIS. Lab Sync Status on the STS |
| Where did the STS requests come from, and did results go back? | API Dashboard |
| Is this analyzer still sending? | Interface Machine Activity |
| How long is the lab taking, and how often do tests fail? | Lab Performance Indicators |
| Where are samples lost between request and result? | Source of Requests |
| What does InteLIS hold behind a result? | Test Results Metadata |
| Which facilities send samples to which lab? | Sample Referral Network |
| What errors has the system logged? | Log File Viewer |

## Find who changed a sample

1. Go to **ADMIN → Monitoring → Audit Trail**.
2. Set **Test Type**.
3. Enter the **Sample ID or Remote Sample ID**.
4. Select **Submit**.
5. Read the **Table View**: one row per revision, with the user who saved it
   and when. Open **Changes Only** to see each changed field with its **Old
   Value** and **New Value**.

    ??? info "Other views"

        | View | Shows |
        | --- | --- |
        | Timeline View | Each revision as an entry on a timeline |
        | Compare Versions | Two chosen revisions side by side |
        | Export To CSV | The history as a file, to send to support or an auditor |

    ??? failure "If the page says the sample does not belong to the lab"

        On a cloud instance, the Audit Trail opens only samples of the user's
        own lab.

## See what a user did

1. Go to **ADMIN → Monitoring → User Activity Log**.
2. Under **Show**, pick **All**, **Actions** or **Logins**.
3. Set **Date Range** and **User**.
4. Read the entries. Each carries the user, the action, the IP address and the
   browser.
5. To follow one sign-in from start to end, select **Filter by this session** on
   one of its entries.

The User Activity Log records what users did. To see the old and new values of
a sample, use the Audit Trail.

## See which pages are used

1. Go to **ADMIN → Monitoring → Page Usage**.
2. Set **Date Range**. Set **User** to narrow it to one person.
3. Read the cards: **Users**, **Sessions**, **Pages Used**, **Page Opens** and
   **Time on Pages**.
4. Read **Most Used Pages** and **Most Active Users**.
5. Under **By User and Page**, select a session to show only that session, then
   select **Open in Activity Log** in the filter above the cards.

Time counts only while the page is the tab in front of the user. It is not the
length of the sign-in.

??? info "Page Usage is empty"

    Recording is switched by **Track Page Usage** in
    [General configuration](admin-general-configuration.md#global-settings).
    It is on by default.

## Check that data reached the STS

**Choose the installation, then follow its steps from top to bottom.**

=== "LIS"

    1. Go to **ADMIN → Monitoring → API History**.
    2. Set **Date Range**. Set **Test Type** to narrow it to one module.
    3. Read the most recent rows:

        | Column | Means |
        | --- | --- |
        | Transaction ID | The identifier of one sync |
        | Number of Records Synced | How many records the sync carried |
        | Sync Type | Which direction and which kind of data moved |
        | URL | The server the sync went to |
        | Synced On | When it ran |

    4. Check for a recent row with a non-zero **Number of Records Synced**.

    A lab whose data is missing nationally has no recent row, or rows carrying
    zero records.

=== "STS"

    1. Go to **ADMIN → Monitoring → Lab Sync Status**.
    2. Set **Province/State**, **District/County** or **Lab Name** to narrow the
       list, and select **Search**.
    3. Read the lab's row. Its colour follows its most recent activity:

        | Status | Means |
        | --- | --- |
        | Active | Synced within 2 weeks |
        | Falling behind | Synced 2 to 4 weeks ago |
        | Stopped | Synced before, but not for 4 weeks or more |
        | Never synced | Registered, never brought up |

    4. Compare **Last Results Sync from Lab** and **Last Requests Sync from
       STS**. A gap in the first means results are sitting on the lab machine.
       A gap in the second means the lab does not see new requests.
    5. To see which facilities are behind, select the lab's row. **Lab Sync
       Details** opens in a new tab with **Requests Sent to Lab** and **Results
       Received from Lab** per facility.

    To send a command to a lab from this page, see
    [Remote command plane](../guides/remote-command-plane.md).

    ??? info "API Dashboard"

        **ADMIN → Monitoring → API Dashboard** follows requests that arrived
        from an EMR or another system through the API. It shows how many were
        received at the lab, tested and answered, and flags possible duplicate
        patients. Set the filters and select **Refresh Dashboard**.

## Check that an analyzer is still sending

1. Go to **ADMIN → Monitoring → Interface Machine Activity**.
2. Read **Events (last 7 days)**, **Failures (last 7 days)** and **Last event**.
3. Set **Instrument** to the analyzer, and select **Search**.
4. Read the most recent **Occurred On**. A failed event carries a **Failure
   Code**.
5. If nothing recent appears, open the testing lab's **Interface Tool
   Connections** and read **Last Seen**. See
   [Interface Tool connections](admin-interface-tool-connections.md).

A stale **Last Seen** means the Interface Tool is not reaching InteLIS.

## Read the lab performance report

1. Go to **ADMIN → Monitoring → Lab Performance Indicators**.
2. Set **Test**, **Date Range**, **View By** and **Lab**. **Lab** appears only
   when the instance has testing labs.
3. Select **Apply**.
4. Open the tab needed. **Overview** appears when **Test** is **All Tests
   (Overview)**. The other tabs appear when one test is chosen.

    | Tab | Shows |
    | --- | --- |
    | Overview | Samples registered and tested, results available and awaiting a result |
    | Turnaround Time | Average days between collection, lab receipt, testing and release |
    | Testing Volume | Results by entry mode: **Manual Entry**, **Analyzer Interface**, **File Import**. Older results show as **Unclassified** |
    | Failures | Failed tests, the failure rate and the re-test rate |
    | Rejections | Rejected samples, the rejection rate and the top reasons |
    | Repeat Patients | Patients tested more than once, and result changes |

5. To keep a copy, select **Export**.

**How are these numbers calculated?** on the page explains each figure. A
sample tested twice counts as two tests, so a retest does not hide a failure.

## Find where samples are lost

1. Go to **ADMIN → Monitoring → Source of Requests**.
2. Set **Date Range** and **Test Type**. The page shows nothing until both are
   set.
3. Narrow it with **Province/State**, **Name of the Clinic**, **Name of the
   Testing Lab** or **Source of Request** if needed.
4. Select **Search**.
5. Compare the counts along each row: **No. of Samples Requested**,
   **Acknowledged**, **Received at Testing Lab**, **Tested** and **Results
   Returned**. The count that drops shows where samples stop.

## Read the record behind a result

1. Go to **ADMIN → Monitoring → Test Results Metadata**.
2. Set **Test Type**.
3. Set **Sample Test Date**, or enter a **Sample ID/Batch Code**.
4. Select **Search**.
5. Read the row. It holds the collection, receipt and test dates, the result
   and its status, who tested it and on which instrument, whether it was
   entered by hand, rejection details, any change with its reason, and a link
   to the imported file.
6. To send it to support, select **Export To Excel**.

## See the referral network

1. Go to **ADMIN → Monitoring → Sample Referral Network**.
2. Set **Date Range** and **Test Type**.
3. Set **Date Based On**: **Sample Collection Date**, **Sample Registration
   Date** or **Sample Tested Date**. Counting by tested date matches a lab's
   own testing figures.
4. Select **Search**.
5. Select a lab or facility on the map to show only its links. The table
   **Referrals by Lab and Test Type** lists every link.

A facility without latitude and longitude is left off the map and still counted
in the table. To add coordinates, see
[Facilities and testing labs](admin-facilities.md#add-a-facility).

## Read the log files

1. Go to **ADMIN → Monitoring → Log File Viewer**.
2. Set **Date** and **Log Type**: **System Error Logs** or **PHP Error Logs**.
3. Filter by level, or search the text.
4. Select **Export Log File** and send the file to support with the request.

The log records faults, not user actions.

## Confirm it worked

| Task | Check |
| --- | --- |
| Traced a change | The Audit Trail names the field, the old value, the new value and the user |
| Confirmed a sync | API History or Lab Sync Status shows a recent sync carrying records |
| Confirmed an analyzer is live | Interface Machine Activity holds a recent event, and **Last Seen** is recent |
| Found a missing sample | Source of Requests shows the stage where the count drops |
