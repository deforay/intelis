# How to monitor and audit InteLIS

This guide covers **ADMIN → Monitoring**, the pages that answer who did what,
whether data is moving, and how the lab is performing.

Use it when a result is questioned, when results stop arriving, or when a lab
asks why its data is missing from a national report.

## Before starting

- An account with administrator rights

## Which page answers which question

| Question | Page |
|---|---|
| Who changed this result, and to what? | Audit Trail |
| Which pages did this user open, and when? | User Activity Log |
| Did this installation reach the national server? | API History |
| Is this analyzer still sending? | Interface Machine Activity |
| How long is this lab taking to return results? | Lab Performance Indicators |
| Where did these requests come from? | Sources of Requests |
| What does the raw result record hold? | Test Results Metadata |
| Which facilities refer to which lab? | Sample Referral Network |
| What is the system reporting to support? | Log File Viewer |

## Find who changed a record

1. Go to **ADMIN → Monitoring → Audit Trail**.
2. Filter to the record in question.
3. Read the change history.

The Audit Trail shows which field changed, its old value, its new value, and the
user who saved the change. Use it whenever a result is questioned.

**User Activity Log** answers a different question. It records which pages a user
opened and when, not what they changed.

## Check whether data reached the national server

1. Go to **ADMIN → Monitoring → API History**.
2. Read the most recent rows.

| Column | Means |
|---|---|
| Transaction ID | The identifier of one sync |
| Number of Records Synced | How many records that sync carried |
| Sync Type | Which direction and which kind of data moved |
| Test Type | Which module the records belong to |
| URL | The server the sync went to |
| Synced On | When it ran |

A lab whose data is missing nationally has either no recent row here, or rows
carrying zero records.

**Lab Sync Status** and **API Dashboard** answer the same question from the other
end. They appear on the national server only, and are absent from a lab
installation by design.

## Check whether an analyzer is still sending

1. Go to **ADMIN → Monitoring → Interface Machine Activity**.
2. Filter to the lab.
3. Read the most recent occurrence for that machine.

If nothing recent appears, open the testing lab under **ADMIN → Facilities** and
read the **Last Seen** time under Connected Installations. A stale Last Seen
means the Interface Tool is not reaching InteLIS. See
[Instruments and interfacing](admin-instruments.md).

## Read the lab performance report

**ADMIN → Monitoring → Lab Performance Indicators** covers every module on the
installation.

| Indicator | Shows |
|---|---|
| Turnaround time | How long samples take at each stage |
| Volume by entry mode | How many samples arrived by manual entry, file import, and the Interface Tool |
| Failure rate | How often tests fail |
| Rejection rate | How often samples are rejected |
| Repeat patients | Patients tested more than once |

Custom test types are broken out per test type rather than pooled.

## Trace where requests came from

**ADMIN → Monitoring → Sources of Requests** counts, per clinic and testing lab,
the samples requested, received at the lab, acknowledged, tested, and returned.

Select the date range and the test type first. The page shows nothing until both
are set.

Use it to find clinics whose samples are requested but never received, and labs
that receive samples but return no results.

## Read the raw result record

**ADMIN → Monitoring → Test Results Metadata** shows what InteLIS holds behind a
result: the collection, received, tested and modified dates, the result and its
status, whether the sample was rejected and why, whether the result was entered
manually, the reason recorded for any change, and the link to the imported file.

Search by sample test date, or by Sample ID or batch code. Export to Excel to
send it to support.

## See the referral network

**ADMIN → Monitoring → Sample Referral Network** maps which facilities refer
samples to which labs, per test type. Select a lab or facility on the map to see
only its links.

Facilities appear on the map only where their latitude and longitude are set. See
[Facilities and testing labs](admin-facilities.md).

## Read the log files

**ADMIN → Monitoring → Log File Viewer** shows the system messages InteLIS
records.

Read it before contacting support, and send the relevant entries with the
request. It reports faults, not user actions.

## Confirm it worked

| Task | Check |
|---|---|
| Traced a change | The Audit Trail names the field, the old value, the new value, and the user |
| Confirmed a sync | API History holds a recent row carrying a non-zero record count |
| Confirmed an analyzer is live | Interface Machine Activity holds a recent entry, and Last Seen is recent |
| Diagnosed a missing lab | Sources of Requests shows where the count drops between stages |
