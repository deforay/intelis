# How to handle failed and held samples

Samples that failed on the analyzer, were put on hold, or went missing collect on
one page. Use it to send them back for testing, or to recover results marked
failed by mistake.

## Before starting

- Permission to view failed and held samples
- Permission to edit test requests, for the per-row buttons and for recovery

## Decide what the sample needs

| Situation | Action | Where |
| --- | --- | --- |
| The test failed, the sample is still usable and enough volume is left | Retest | **Retest** steps below |
| An import marked a sound run as failed | Recover | **Recover a run marked failed by mistake** steps below |
| The sample is not fit to test, and the facility must collect again | Reject with a reason | [Reject](approve-results.md) |
| The sample cannot be found | Mark lost | [Mark lost](approve-results.md) |
| The request was entered twice, or withdrawn | Cancel | [Cancel](approve-results.md) |

Do not cancel a sample to clear a failure. A cancelled sample counts as never
tested and drops out of testing counts and turnaround time. A failed sample
stays in the failure rate, which is the quality signal the lab needs.

## What the page lists

| Status | Meaning |
| --- | --- |
| Failed | The analyzer returned a failure or an invalid reading |
| Hold | The sample is paused pending a decision |
| Lost | The sample cannot be found |

??? info "How a sample reaches Hold"

    No viral load screen in InteLIS sets Hold on a sample. On the **Imported
    Results** screen that follows **Import Results From File**, choosing **Hold**
    for a row sets that row's result aside. The result is not saved to the
    sample, and the sample keeps waiting for a result. A Hold sample on this page
    already carried that status before it arrived here.

For every status, see [Sample statuses](sample-statuses.md).

## Follow the steps

**Choose the situation, then follow its steps from top to bottom.**

=== "Retest"

    1. Go to **HIV VIRAL LOAD → Test Result Management → Failed/Hold Samples**.
    2. Check **Result Status**. **Failed** and **Hold** are selected by default.
       Add **Lost** if needed.
    3. Narrow with the other filters if needed, such as **Facility Name**,
       **Sample Collection Date** or **Manifest Code**.
    4. Select **Search**.
    5. Tick the samples to retest. The **Retest the selected samples** button
       appears.

        ??? info "To retest one sample"

            Select **Retest** on that sample's row instead, then go to step 7.

    6. Select **Retest the selected samples**.
    7. InteLIS shows `Retest has been submitted.` The samples leave this list.

        ??? info "What retest does"

            The result is cleared and the sample leaves its batch. Its status
            returns to **Sample Registered at Testing Lab**. InteLIS keeps the
            failed attempt on record, so lab performance reports count both the
            failure and the retest.

    8. Go to **HIV VIRAL LOAD → Request Management → View Test Requests** and
       search for the sample. The **Status** column shows
       `Sample Registered at Testing Lab`, and the result is empty.
    9. Add the sample to a new batch. See
       [How to batch samples for testing](batch-samples.md).

    ??? info "If the tube's barcode label is damaged"

        Reprint it from **HIV VIRAL LOAD → Request Management → View Test
        Requests**. Search for the sample and select **Barcode** on its row. The
        button appears only when **Sample ID Barcode Label Printing** under
        **ADMIN → System Configuration → General Configuration** is not set to
        **Off**. If no printer is listed, select **Change/Retry** to pick one.

=== "Recover a run marked failed by mistake"

    An import can mark a whole run failed when the results were sound. Recovery
    moves those samples straight to **Accepted**, with no second approval step.
    Check each result against the analyzer printout first.

    1. Go to **HIV VIRAL LOAD → Test Result Management → Failed/Hold Samples**.
    2. Set **Result Status** to **Failed** only.
    3. Narrow with the other filters if needed.
    4. Select **Search**.
    5. Tick the affected samples. The **Move selected to Accepted** button
       appears.

        ??? info "To recover one sample"

            Select **Accept** on that sample's row instead, then go to step 7.
            The button appears only on Failed rows that carry a usable result.

    6. Select **Move selected to Accepted**.
    7. Select **OK** to confirm. InteLIS shows how many samples moved, for
       example `3 sample(s) moved to Accepted.`

        ??? failure "If InteLIS shows `No samples were moved`"

            The samples carry a genuine failure result, such as Failed, Error or
            Invalid, or are already accepted. Genuine failures are always
            skipped. Retest them instead.

    8. Search again with **Result Status** set to **Failed**. The recovered
       samples no longer appear.

    Next: [release the results to the requesting facility](release-results.md).
