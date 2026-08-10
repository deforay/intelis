# How to review and approve results

A result is not released until it is approved. Approval is the check that the
result in InteLIS is the result the analyzer produced, and that it belongs to the
right sample.

Results captured through the Interface Tool may be approved automatically, if
the lab is configured that way. Results imported from a file or typed in by hand
always pass through this page.

## Before starting

- Results captured in InteLIS. See [How to capture viral load results](capture-results.md)
- Permission to manage result status
- The analyzer printout or worklist for the run

## Find the results waiting

1. Go to **HIV VIRAL LOAD → Test Result Management → Manage Results Status**.
2. Set **Show Samples that are** to **Not Approved/Rejected**.
3. Narrow further with the filters if needed.

| Filter | Use |
|---|---|
| Batch Code | One analyzer run |
| Sample Test Date | Everything tested on one date |
| Facility Name | Samples from one health facility |
| Sample Collection Date | A collection date range |
| Sample Type | One specimen type |
| Manifest Code | Samples from one incoming package |

4. Select **Search**.

Filtering by batch code is the most reliable approach. It puts one analyzer run
on screen, which can be checked against one printout.

!!! info "Screenshot"
    **Page:** HIV VIRAL LOAD → Test Result Management → Manage Results Status

    **Capture:** the list filtered to Not Approved/Rejected for one batch

    **Highlight:** the Show Samples that are filter

## Check each result

For every row, check three things against the analyzer printout.

1. The Sample ID matches.
2. The result value matches.
3. The patient shown is the patient expected for that Sample ID.

A mismatch between the Sample ID and the patient means the sample was registered
against the wrong patient, or loaded into the wrong analyzer position. Do not
approve it. Hold the sample and investigate.

## Approve

1. Tick the samples to approve.
2. In **Bulk Actions**, set **Status** to **Accepted**.
3. Set **Approver**, and set **Tester** and **Reviewer** if the lab records them.
4. Select **Apply**.

InteLIS asks for confirmation before applying. Confirm to proceed.

| Setting | Effect |
|---|---|
| **Replace existing** | Overwrites the names already recorded on those samples. Leave it off to fill in only what is blank |

Where the same person is chosen for more than one of approver, tester, and
reviewer, InteLIS either warns or refuses, depending on the lab's configuration.
Where it warns, confirm only if the lab permits one person to hold both roles.

!!! info "Screenshot"
    **Page:** HIV VIRAL LOAD → Test Result Management → Manage Results Status

    **Capture:** the Bulk Actions panel with Status set to Accepted and an approver chosen

    **Highlight:** the Apply button and the Replace existing checkbox

## Reject a sample

Use rejection when the sample itself was not fit to test, such as a haemolysed
or insufficient specimen.

1. Tick the samples.
2. Set **Status** to **Rejected**.
3. Choose a **Rejection Reason**.
4. Select **Apply**.

The reason appears on the report sent back to the facility, and in the sample
rejection report. Choose the reason that tells the facility what to do
differently next time.

## Mark a sample lost

Use **Lost** when the sample cannot be found and will not be tested.

1. Tick the samples.
2. Set **Status** to **Lost**.
3. Select **Apply**.

## Cancel a sample

Use **Cancel** only when testing will not happen at all, such as a request
entered twice or withdrawn by the clinician.

1. Set **Show Samples that are** to **Available for Cancellation**.
2. Select **Search**.
3. Tick the samples.
4. Set **Status** to **Cancelled**.
5. Select **Apply**.

InteLIS asks for a typed confirmation word before cancelling. This is
deliberate. Cancelling records that the sample was never tested, so it is
excluded from testing counts and turnaround time.

Do not cancel a sample that was tested and failed. Failure and cancellation mean
different things in the reports. See
[How to handle failed and held samples](failed-and-held-samples.md).

!!! info "Screenshot"
    **Page:** the cancellation confirmation dialog

    **Capture:** the dialog with the word to type visible

## Correct an approved result

1. Set **Show Samples that are** to **Already Approved/Rejected**.
2. Select **Search** and find the sample.
3. Apply the corrected status through **Bulk Actions**.

InteLIS asks before overwriting a result that already exists. Confirm only when
the replacement is the correct result.

Samples lock after a number of days set by the administrator. A locked sample
cannot be changed here. Ask the administrator.

## Confirm it worked

Set **Show Samples that are** to **Already Approved/Rejected** and search on the
batch code. Every sample from the run appears with its final status.

Samples still listed under **Not Approved/Rejected** have not been actioned.

## Next

[Release the results to the requesting facility](release-results.md).
