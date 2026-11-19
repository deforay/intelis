---
description: The words InteLIS and these guides use, each with a one-line meaning and the page that covers it.
audience: [lab-staff, lab-supervisor, lab-admin, system-admin, requesting-facility]
module: [all]
type: reference
reviewed: 2026-11-19
reviewed_against: 5.8.8
---
# Words used in these guides

Each word below has one meaning across InteLIS and these guides.

## Where InteLIS runs

| Word | Meaning |
| --- | --- |
| InteLIS | The laboratory information system these guides describe. It was called VLSM before, and some folders and the database keep that name. |
| STS | Sample Tracking System. The central InteLIS server that labs and health facilities connect to. Facilities register requests on it, and labs send their results to it. |
| LIS | Lab Information System. InteLIS running on a lab's own machine. It takes requests from the STS and sends results back. |
| Standalone | InteLIS that connects to no STS. The lab enters and keeps all its data there. |
| Sync | The regular exchange of requests and results between a LIS and the STS. It runs every 5 minutes while the network is up. See [Results Not Reaching the STS](../guides/sts-sync-not-working.md). |
| InteLIS machine | The computer that runs InteLIS for a lab or for the STS. The pages under **Installation**, **Updates**, **Backup & Restore** and **Troubleshooting** are about this machine. |

## Places and people

| Word | Meaning |
| --- | --- |
| Facility | The health facility that collects the sample and requests the test. Also called the requesting facility or clinic. See [For requesting facilities](for-requesting-facilities.md). |
| Testing lab | The laboratory that tests the sample. See [How to manage facilities and testing labs](admin-facilities.md). |
| Role | A set of permissions given to a user. A missing menu or button usually means the role lacks that permission. See [How to manage users and roles](admin-users-and-roles.md). |
| Administrator | A user whose role can manage users, facilities and settings under **ADMIN**. See [Administer InteLIS](administer-intelis.md). |

## Samples and their journey

| Word | Meaning |
| --- | --- |
| Test request | The record of one sample and the test asked for it. See [How to register a viral load test request](register-a-request.md). |
| Sample ID | The code the testing lab gives a sample. The facility's own code for the sample stays on the record next to it. |
| Manifest | The list of samples in one package sent from a facility to a testing lab. It has a manifest code. See [How to send samples on a manifest](send-samples-on-a-manifest.md). |
| Activate a manifest | Receive a manifest's samples at the testing lab in one action, using its manifest code. See [How to receive samples sent on a manifest](receive-referred-samples.md). |
| Batch | A group of samples tested together in one analyzer run. It has a batch code. See [How to batch samples for testing](batch-samples.md). |
| Status | Where a sample is in its journey, for example **Awaiting Approval** or **Accepted**. See [Sample statuses](sample-statuses.md). |
| Hold | A status for a sample paused until someone decides what it needs. See [How to handle failed and held samples](failed-and-held-samples.md). |
| Approve | Confirm a result is correct before it is released. See [How to review and approve results](approve-results.md). |
| Release | Send an approved result to the facility, by print, email or export. See [How to release results](release-results.md). |
| Locked | An accepted or rejected sample that has not changed for the number of days in **Sample Lock Days**. Only users with permission to edit locked samples can change it. See [How to review and approve results](approve-results.md). |
| Reject | Refuse a sample that cannot be tested, with a reason the facility can read. |

## Instruments

| Word | Meaning |
| --- | --- |
| Analyzer | The machine that tests the samples. InteLIS menus call it an **Instrument**. See [How to set up an instrument](admin-instruments.md). |
| Interface Tool | The program on a lab computer that carries results from the analyzer into InteLIS. See [Connect an Instrument to InteLIS](../guides/setting-up-interfacing-tool.md). |
| Import results | Load results from a file the analyzer exported, when there is no Interface Tool. See [How to capture viral load results](capture-results.md). |

## Machine care

| Word | Meaning |
| --- | --- |
| `intelis` command | The command typed in a terminal on the InteLIS machine to check, update, back up and repair it. See [Maintenance Scripts and Tools](../guides/maintenance.md). |
| Off-machine backup | A copy of the lab's data kept on another computer, a shared folder or a USB drive. See [How to set up off-machine backups](../guides/setting-up-off-machine-backups.md). |
| Job aid | A one-page card to print and pin up next to the work. See [Printable job aids](../job-aids/index.md). |
