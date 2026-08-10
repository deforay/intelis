# How to handle failed and held samples

Samples that failed on the analyzer, were put on hold, went missing, or expired
collect on one page. Use it to send them back for retesting, or to recover
results that were marked failed by mistake.

## Before starting

- Permission to view failed and held samples

## Find them

1. Go to **HIV VIRAL LOAD → Test Result Management → Failed/Hold Samples**.
2. Set **Result Status** to the group to work through.

| Status | Meaning |
|---|---|
| Failed | The analyzer returned a failure or an invalid reading |
| Hold | Someone paused the sample pending a decision |
| Lost | The sample cannot be found |
| Expired | The sample passed the storage life the lab allows |

3. Narrow with the other filters if needed, then select **Search**.

## Send samples for retesting

Use this when the sample is still viable and the lab has enough volume left.

1. Tick the samples to retest.
2. Select **Retest the selected samples**.

InteLIS confirms that the retest has been submitted.

Retesting clears the result and returns the sample to the untested queue with
the status **Sample Registered at Testing Lab**. The sample can then be added to
a new batch. See [How to batch samples for testing](batch-samples.md).

The failed attempt is not erased. InteLIS keeps it on record, so the lab
performance reports count both the failed run and the retest. The failure rate
stays accurate.

## Recover results marked failed by mistake

An import can mark a whole run failed when the results were sound. Use this
action to put those samples right.

1. Tick the affected samples.
2. Select **Move selected to Accepted**.
3. Confirm.

InteLIS moves back only the samples whose recorded result is usable. Samples
that genuinely failed on the analyzer are skipped, and InteLIS reports how many
moved.

If nothing moves, the samples are either genuine failures or already accepted.
Those need a retest, not recovery.

## Reprint a barcode label

Where a tube's label is damaged or missing, reprint it from this page.

1. Find the sample.
2. Use the printing option on the row.

If no printer is listed, select **Change/Retry** to pick one.

## Decide between retest and cancel

| Situation | Action | Where |
|---|---|---|
| Sample viable, enough volume | Retest | This page |
| Sample not viable, facility should resend | Reject with a reason | [Manage Results Status](approve-results.md) |
| Sample lost | Mark Lost | [Manage Results Status](approve-results.md) |
| Request entered twice, or withdrawn | Cancel | [Manage Results Status](approve-results.md) |

Cancellation and failure are counted differently. A cancelled sample is treated
as never tested and drops out of testing counts and turnaround time. A failed
sample stays in the failure rate. Using cancel to clear failures hides a real
quality signal.

## Confirm it worked

After a retest, search for the sample under **HIV VIRAL LOAD → Request
Management → View Test Requests**. It shows the status **Sample Registered at
Testing Lab** and no result.

After a recovery, the sample no longer appears on the Failed/Hold page under
**Failed**, and carries its result.
