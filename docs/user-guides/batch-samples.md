---
description: Group registered viral load samples into a batch for one analyzer run and print the batch PDF.
audience: [lab-staff]
module: [vl]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# How to batch samples for testing

Group registered samples into a batch for one analyzer run, and print the batch
PDF that carries their Sample IDs to the analyzer.

## Before starting

- Samples registered in InteLIS, either
  [registered directly](register-a-request.md) or
  [activated from a manifest](receive-referred-samples.md)
- The analyzer the run goes on
- Permission to manage batches

## Create the batch

1. Go to **HIV VIRAL LOAD → Request Management → Manage Batch**.
2. Select **Create New Batch**.
3. Choose the analyzer in **Testing Platform**. The list of samples waiting for
   a batch appears, with the maximum number of samples the analyzer takes.
4. Select **Show Advanced Search Options**.
5. Choose the **Positions** numbering, **Numeric** or **Alpha Numeric**, to
   match how positions are labelled on the analyzer.
6. Choose **Sort By** and **Sort Type** to set the order of the samples. The
   position screen after saving starts in this order.
7. Set any filters needed to narrow the list.

    | Filter | Narrows the list to |
    |---|---|
    | Facility | Samples from the chosen health facilities |
    | Samples Entered or Modified By | Samples handled by one user |
    | Sample Collection Date | A collection date range |
    | Date Sample Receieved at Lab | A lab reception date range |
    | Last Modified | A last-change date range |
    | Sample Type | One specimen type |
    | Funding Source | Samples under one funder |

8. Select **Filter Samples**.

    ??? failure "If InteLIS says to choose a testing platform to proceed"

        No analyzer is selected. Choose one in **Testing Platform**, then select
        **Filter Samples** again.

    ??? question "If a sample is missing from the list"

        The list shows only samples that have a Sample ID, are at **Sample
        Registered at Testing Lab** or **Sample Reordered**, have no result,
        are not rejected, and are not in another batch.

9. Check the **Batch Code**. InteLIS fills it and it cannot be changed.
10. Select the samples for the run. Either:

    - Select **Automatically select samples for Batch**. It moves samples from
      the top of the list into the batch, up to the analyzer's maximum.
    - Select samples in the left list, then select the single right arrow to
      move them into the batch on the right.

11. Select **Save and Next**. The **Add Batch Controls Position** screen opens.

    ??? failure "If InteLIS says more than the allowed number of samples are selected"

        The batch holds more samples than the analyzer takes. Move samples back
        to the left list with the single left arrow, then select **Save and
        Next** again.

    ??? failure "If InteLIS asks to select at least one sample"

        The batch on the right is empty. Move samples into it, then select
        **Save and Next** again.

12. Drag the samples and controls into the order they go on the analyzer.
13. Select **Save**. The batch appears in the **Manage Batch** list.

## Print the batch PDF

14. On the batch's row in **Manage Batch**, select **Batch PDF** or **Compact
    Batch PDF**.

    | Option | Layout |
    |---|---|
    | Batch PDF | One area per sample, with a barcode for each Sample ID |
    | Compact Batch PDF | The same list on fewer pages |

    ??? info "If Batch PDF is missing from the row"

        The lab is configured for the compact layout only. Use **Compact Batch
        PDF**.

15. Print the PDF.

Load the samples on the analyzer with the Sample IDs from the printed PDF. Then
[capture the results](capture-results.md).

## Change or remove a batch

Each row in **Manage Batch** offers these actions.

| Action | Effect |
|---|---|
| **Edit** | Change the batch and its samples |
| **Edit Position** | Change the position of each sample |
| **Batch PDF**, **Compact Batch PDF** | Reprint the batch PDF |
| **Delete** | Remove the batch and return its samples to the list waiting for a batch. Shown only while no sample in the batch has a result or a rejection |

To retest samples in a batch that already has results, see
[How to handle failed and held samples](failed-and-held-samples.md).

## Confirm it worked

The batch appears in **Manage Batch** with the right count in **No. of
Samples**. Once results are captured, **No. of Samples Tested** rises to match.
