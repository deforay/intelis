# Sample statuses

Every sample in InteLIS carries one status. This page lists all of them, what
sets each one, and how each one counts in reports.

## The statuses

| Status | Meaning |
| --- | --- |
| Sample Currently Registered at Health Center | Registered at a health facility. The testing lab has not received it |
| Sample Registered at Testing Lab | Received by the testing lab and waiting to be tested |
| Sample Referred to another Lab | Sent on to a different lab for testing |
| Awaiting Approval | A result is recorded and waiting for approval |
| Accepted | The result is approved and ready for release |
| Rejected | The sample was not fit to test. A rejection reason is recorded, and the rejection is released to the requesting facility so the sample can be collected again |
| Failed/Invalid | The test ran and did not produce a usable result |
| Hold | Paused pending a decision |
| Sample Reordered | Legacy status, kept so older records still read correctly |
| Lost | The sample cannot be found and will not be tested |
| Expired | The sample waited without a result for longer than the installation allows |
| No Result | The test ran and reported no result |
| Cancelled | Testing will not be performed. The request stands but no test is done |

## What sets each status

| Status | Set by |
| --- | --- |
| Sample Currently Registered at Health Center | Registering a request at a health facility |
| Sample Registered at Testing Lab | Registering a request at a lab, activating a manifest, or sending a sample for retest |
| Sample Referred to another Lab | Referring a sample to another lab |
| Awaiting Approval | Recording a result, or correcting one on the result form |
| Accepted | Approving a result in **Manage Results Status** or on the **Imported Results** screen, automatic approval of results sent in by the Interface Tool where the lab is configured for it, or recovering a result on the **Failed/Hold Samples** page |
| Rejected | Recording a rejection on the result form, applying **Rejected** in **Manage Results Status**, or choosing **Rejected** on the **Imported Results** screen |
| Failed/Invalid | Recording a result that reads as a failure, choosing **Failed** on the **Imported Results** screen, or applying **Accepted** to a result that reads as a failure |
| Hold | No viral load screen sets it. Choosing **Hold** on the **Imported Results** screen sets that row's result aside and leaves the sample's status unchanged |
| Sample Reordered | No current workflow. Retesting returns a sample to Sample Registered at Testing Lab. The **Sample Reordered** checkbox on the request form records a separate flag, not this status |
| Lost | Applying **Lost** in **Manage Results Status** |
| Expired | The nightly status update. A sample still in Hold, Sample Reordered, Sample Currently Registered at Health Center or Sample Registered at Testing Lab expires once it is older than **Sample Expiry Days** under **ADMIN → System Configuration → General Configuration**. The age counts from the collection date, or from the request date when no collection date is recorded. The default is 365 days |
| No Result | Entering `no result` as the result |
| Cancelled | Applying **Cancelled** in **Manage Results Status** and typing `CANCEL` to confirm |

## Statuses that record a reason

| Status | Reason recorded |
| --- | --- |
| Rejected | A rejection reason from the list under **ADMIN → VL Config → Rejection Reasons** |
| Failed/Invalid | A failure reason from the list under **ADMIN → VL Config → Test Failure Reasons** |

## How statuses count in reports

| Status | Effect |
| --- | --- |
| Accepted | Can be printed and emailed to the requesting facility |
| Rejected | Can be printed for the requesting facility. Rejected samples carry no result, and email sends only samples with a result. Appears in the sample rejection report with its reason |
| Failed/Invalid | Stays in the failure rate. Sending a failed sample for retest keeps the failed attempt on record, so both the failure and the retest are counted |
| Cancelled | Counted as never tested. Excluded from testing counts and from turnaround time |

## Related guides

- [How to review and approve results](approve-results.md)
- [How to handle failed and held samples](failed-and-held-samples.md)
- [How to release results to the requesting facility](release-results.md)
