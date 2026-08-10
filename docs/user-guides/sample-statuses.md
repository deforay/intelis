# Sample statuses

Every sample in InteLIS carries one status. This page lists all of them.

Applies to InteLIS 5.6.2.

## The statuses

| Status | Meaning |
|---|---|
| Sample Currently Registered at Health Center | Registered at a health facility. The testing lab has not received it |
| Sample Registered at Testing Lab | Received by the testing lab and waiting to be tested |
| Sample Referred to another Lab | Sent on to a different lab for testing |
| Awaiting Approval | A result is recorded and waiting for approval |
| Accepted | The result is approved and available for release |
| Rejected | The sample was not fit to test. A rejection reason is recorded |
| Failed/Invalid | The test ran and did not produce a usable result |
| Hold | Paused pending a decision |
| Sample Reordered | Sent back for testing again |
| Lost | The sample cannot be found and will not be tested |
| Expired | The sample passed the storage life the installation allows |
| No Result | No result is recorded for the sample |
| Cancelled | Testing will not be performed. The request stands but no test is done |

## Where each status is set

| Status | Set by |
|---|---|
| Sample Currently Registered at Health Center | Registering a request at a health facility |
| Sample Registered at Testing Lab | Registering a request at a lab, activating a manifest, or sending a sample for retest |
| Sample Referred to another Lab | Referring a sample to another lab |
| Awaiting Approval | Recording a result |
| Accepted | Approving a result, or recovering one from the Failed/Hold page |
| Rejected | Recording a rejection on the result form, or applying Rejected in Manage Results Status |
| Failed/Invalid | Recording a test failure, or an import marking the row failed |
| Hold | Applying Hold to a sample |
| Sample Reordered | Reordering a sample for testing |
| Lost | Applying Lost in Manage Results Status |
| Expired | The installation's sample expiry period elapsing |
| Cancelled | Applying Cancelled in Manage Results Status, with a typed confirmation |

## Statuses that pair with a reason

| Status | Reason recorded |
|---|---|
| Rejected | A rejection reason from the list under **ADMIN → VL Config → Rejection Reasons** |
| Failed/Invalid | A failure reason from the list under **ADMIN → VL Config → Test Failure Reasons** |
| Cancelled | A typed confirmation, entered at the time of cancelling |

## How statuses affect reports

**Cancelled** samples are treated as never tested. They are excluded from testing
counts and from turnaround time.

**Failed/Invalid** samples stay in the failure rate. Sending a failed sample for
retest keeps the failed attempt on record, so both the failure and the retest are
counted.

**Rejected** samples appear in the sample rejection report with their reason.

**Accepted** samples are the only ones available to print and to email.

## Related guides

- [How to review and approve results](approve-results.md)
- [How to handle failed and held samples](failed-and-held-samples.md)
- [How to release results to the requesting facility](release-results.md)
