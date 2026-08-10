# How to batch samples for testing

A batch is the set of samples that run together on one analyzer. Building the
batch in InteLIS first, then printing the batch PDF, is what keeps the Sample IDs
on the analyzer identical to the Sample IDs in InteLIS.

Skipping this step is the most common cause of results that come back and match
no sample.

## Before starting

- Samples registered in InteLIS, either
  [registered directly](register-a-request.md) or
  [activated from a manifest](receive-referred-samples.md)
- The analyzer the run goes on
- Permission to manage batches

## Create the batch

1. Go to **HIV VIRAL LOAD → Request Management → Manage Batch**.
2. Select **Create New Batch**.
3. Choose the analyzer in **Testing Platform**.

Choose the analyzer first. InteLIS caps the number of samples a batch can hold
based on the analyzer chosen, and it refuses to go further without one.

4. Enter a **Batch Code**.

Batch codes are unique. If the code is already used, InteLIS says so and the
batch cannot be saved until the code is changed. Use the lab's own naming
convention so the batch can be traced back later.

5. Enter the **Lab Assigned Batch Code** if the lab keeps a separate run number
   on the analyzer.
6. Choose the **Positions** numbering, either **Numeric** or **Alpha Numeric**,
   to match how positions are labelled on the analyzer.

!!! info "Screenshot"
    **Page:** HIV VIRAL LOAD → Request Management → Manage Batch → Create New Batch

    **Capture:** the top of the form with Testing Platform and Batch Code filled

    **Highlight:** the Testing Platform field

## Find the samples

The sample list below the form shows samples waiting to be tested. To narrow it,
select **Show Advanced Search Options** and filter on any of these.

| Filter | Use |
|---|---|
| Facility | Samples from one health facility |
| Samples Entered or Modified By | Samples handled by one user |
| Sample Collection Date | A collection date range |
| Date Sample Received at Lab | A received date range |
| Sample Type | One specimen type |
| Funding Source | Samples under one funder |

Set **Sort By** and **Sort Type** to control the order samples appear in. That
order becomes the order on the batch PDF, so set it to match how the run is
loaded.

Select **Filter Samples** to apply. Select **Reset Filters** to clear.

## Select the samples

Tick the samples for the run.

To fill the batch to the analyzer's capacity in one action, use **Automatically
select samples for Batch**. It selects from the filtered list in the sort order
chosen.

InteLIS blocks a save in three cases.

| Message | Meaning |
|---|---|
| Choose a testing platform to proceed | No analyzer selected |
| Select at least one sample | No samples ticked |
| More than the allowed number of samples for this platform | Too many samples ticked for that analyzer |

## Save

Select **Save and Next**. The batch is created and appears in the batch list.

## Print the batch PDF

1. Go to **HIV VIRAL LOAD → Request Management → Manage Batch**.
2. Find the batch.
3. Select **Batch PDF** or **Compact Batch PDF** on the row.

| Option | What it gives |
|---|---|
| Batch PDF | One page per sample area, with a barcode for each Sample ID |
| Compact Batch PDF | The same list packed into fewer pages |

Some labs are configured to offer the compact layout only. Where that is the
case, **Batch PDF** does not appear on the row.

Print the PDF and take it to the analyzer.

!!! info "Screenshot"
    **Page:** HIV VIRAL LOAD → Request Management → Manage Batch

    **Capture:** the batch list with one row's action buttons visible

    **Highlight:** the Batch PDF and Compact Batch PDF buttons

## Register the samples on the analyzer

Use the printed batch PDF at the analyzer. Scan or enter the Sample ID from the
PDF for each position.

The Sample IDs on the analyzer must match the Sample IDs in InteLIS exactly. A
result carrying an ID that InteLIS does not recognise does not attach to any
sample, and the sample stays in the untested queue.

Do not enter IDs from the paper request form, from a worklist kept outside
InteLIS, or from memory.

## Run the test

Run the batch on the analyzer as normal. Then capture the results. See
[How to capture viral load results](capture-results.md).

## Change or remove a batch

The batch list offers these actions per row.

| Action | What it does | When it is available |
|---|---|---|
| **Edit** | Change the batch details and its samples | Always |
| **Edit Position** | Change which position each sample sits in | Always |
| **Batch PDF** | Reprint the full barcode sheet | Unless the lab uses the compact layout only |
| **Compact Batch PDF** | Reprint the packed sheet | Always |
| **Delete** | Remove the batch and release its samples | Only while no sample in the batch has been tested |

Deleting a batch does not delete its samples. The samples return to the untested
queue and can be added to another batch.

Once any sample in a batch has a result, **Delete** disappears from the row. To
retest those samples, use the retest action instead. See
[How to handle failed and held samples](failed-and-held-samples.md).

## Confirm it worked

The batch appears in **Manage Batch** with the correct sample count in **No. of
Samples**. After the run and after results are captured, **No. of Samples
Tested** rises to match.
