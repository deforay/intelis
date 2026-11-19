---
description: Register an analyzer, its machines, date format, limits and control counts so InteLIS can batch and import its results.
audience: [lab-admin, system-admin]
module: [all]
type: how-to
reviewed: 2026-11-19
reviewed_against: 5.8.8
---
# How to set up an instrument

Register an analyzer under **ADMIN → Settings → Instruments**, so
InteLIS can read its results. An instrument that is not registered cannot be
chosen for a batch, and its result files cannot be imported.

Two related tasks have their own guides:

- To send results straight from the analyzer, see
  [Connect an Instrument to InteLIS](../guides/setting-up-interfacing-tool.md).
- To link an Interface Tool installation to the lab, see
  [Interface Tool connections](admin-interface-tool-connections.md).

## Before starting

- An administrator account, or on a cloud instance, a lab account allowed to
  manage instruments (own lab only)
- The testing lab, created under **ADMIN → Facilities**
- One date copied exactly as the analyzer writes it in its result files

## Add an instrument

1. Go to **ADMIN → Settings → Instruments**.
2. Select **Add Instrument**.
3. Enter **Instrument Name**, the name the lab uses for this analyzer, such as
   Cobas 6800 NRL. Each instrument needs its own name.
4. Set **Make** to the analyzer's manufacturer, such as Roche or Abbott. If the
   manufacturer is not listed, select **Other**.

    ??? info "What Make is used for"

        **ADMIN → Reports → Instrument Activity** groups the tests on **Tests by
        Instrument** under **By Instrument Type** by make. Left empty, the
        report guesses the make from the **Instrument File** and the
        **Instrument Name**. On Edit Instrument, **Make** shows that guess until
        someone saves a make.

5. Set **Testing Lab** to the lab the analyzer sits in.

    ??? info "Testing Lab on a LIS or a cloud instance"

        A LIS shows no **Testing Lab** field. InteLIS uses the installation's
        own lab. On a cloud instance, a lab user without the built-in Admin role
        sees only their own lab.

6. Under **Supported Tests**, select every test type the analyzer runs.
7. Set **Instrument File**. It tells InteLIS how to read this analyzer's
   result files. Without it, file import fails. If the analyzer has no file in
   the list, a developer must write one before file import works.

    ??? failure "If the instrument is missing from Import Results From File"

        **Instrument/Platform Name** on **Import Results From File** lists an
        instrument only when all three are true:

        - Its **Status** is **Active**.
        - The test type is among its **Supported Tests**.
        - Its **Instrument File** exists for that test type and is not empty.

        Fix the one that fails on Edit Instrument. If the file is missing or
        empty for that test type, a developer must write it.

8. Enter the result limits:

    | Field | What to enter |
    | --- | --- |
    | Lower Limit | The lowest value the analyzer reports, such as 20 |
    | Higher Limit | The highest value the analyzer reports, such as 10000000 |
    | Maximum No. of Samples In a Batch | How many samples fit in one run |
    | Low VL Result Text | Every text the analyzer writes for an undetectable result, separated by commas, such as `Target Not Detected, TND, < 20, < 40`. Shown only when VL or Hepatitis is among the **Supported Tests** |

    ??? warning "A wording missing from Low VL Result Text"

        A result written in a wording that is not in the list is imported as an
        unrecognised result, not as undetectable.

9. Under **Machine Names**, enter the **Machine Name** of the first analyzer
   of this model.
10. In the **Date Format** cell of that row, paste the date copied from a result
   file, such as `06.19.2025 11:19 AM`. Select the format InteLIS suggests.

    ??? failure "If no format is suggested"

        Enter the format by hand, such as `d/m/Y H:i`. A wrong date format makes
        every imported date wrong or empty.

11. In **Instrument File Name** on that row, select the file for this
    analyzer. Left empty, the row uses the **Instrument File** set above.
12. If the analyzer is a point-of-care device, tick **Is this a POC Device?**
    and enter its **Latitude** and **Longitude**. Both coordinates are needed.
    Without them the analyzer is not saved as POC.
13. To add another analyzer of the same model, select **+** on the row, then
    repeat steps 9 to 12 on the new row.
14. For each test type, enter the control counts. A row appears for each test
    type selected under **Supported Tests**. The counts tell InteLIS how many
    positions in a run are not patient samples:

    | Field | What to enter |
    | --- | --- |
    | Number of In-House Controls | In-house control positions per run |
    | Number of Manufacturer Controls | Manufacturer control positions per run |
    | No. Of Calibrators | Calibrator positions per run |

15. If the same people always sign off this analyzer's results, set
    **Default Reviewer** and **Default Approver** for each test type. Left
    empty, each result records whoever actually reviewed and approved it.
16. To print a fixed method statement on every result from this analyzer,
    enter it under **Description/Comment to add in Test Result**.
17. Select **Submit**.

## Retire an instrument

1. Go to **ADMIN → Settings → Instruments**.
2. Select **Edit** on the instrument.
3. Set **Status** to **Inactive**.
4. Select **Submit**.

**Status** exists on Edit Instrument only. A new instrument is saved as active.

## Confirm it worked

| Change | Check |
| --- | --- |
| New instrument | It is offered as the testing platform when creating a batch |
| Make | **By Instrument Type** on **Instrument Activity** counts the instrument's tests under that make |
| Instrument File | Import one result file from the analyzer and read the imported rows |
| Date Format | The imported test dates match the analyzer's own |
| Low VL Result Text | An undetectable result imports as undetectable |
| Control counts | The batch position count matches the run |
