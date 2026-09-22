---
description: Register an analyzer, its machines, date format, limits and control counts so InteLIS can batch and import its results.
audience: [lab-admin, system-admin]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# How to set up an instrument

Register an analyzer under **ADMIN → System Configuration → Instruments**, so
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

1. Go to **ADMIN → System Configuration → Instruments**.
2. Select **Add Instrument**.
3. Enter **Instrument Name**, the manufacturer or platform, such as Roche or
   Abbott.
4. Set **Testing Lab** to the lab the analyzer sits in.

    ??? info "Testing Lab on a LIS or a cloud instance"

        A LIS shows no **Testing Lab** field. InteLIS uses the installation's
        own lab. On a cloud instance, a lab user without the built-in Admin role
        sees only their own lab.

5. Under **Supported Tests**, select every test type the analyzer runs.
6. Set **Instrument File**. It tells InteLIS how to read this analyzer's
   result files. Without it, file import fails. Select an existing file. A
   file named after the instrument is empty and needs a developer to write it
   before import works.
7. Enter the result limits:

    | Field | What to enter |
    | --- | --- |
    | Lower Limit | The lowest value the analyzer reports, such as 20 |
    | Higher Limit | The highest value the analyzer reports, such as 10000000 |
    | Maximum No. of Samples In a Batch | How many samples fit in one run |
    | Low VL Result Text | Every text the analyzer writes for an undetectable result, separated by commas, such as `Target Not Detected, TND, < 20, < 40`. Shown only when VL or Hepatitis is among the **Supported Tests** |

    ??? warning "A wording missing from Low VL Result Text"

        A result written in a wording that is not in the list is imported as an
        unrecognised result, not as undetectable.

8. Under **Machine Names**, enter the **Machine Name** of the first analyzer
   of this model.
9. In the **Date Format** cell of that row, paste the date copied from a result
   file, such as `06.19.2025 11:19 AM`. Select the format InteLIS suggests.

    ??? failure "If no format is suggested"

        Enter the format by hand, such as `d/m/Y H:i`. A wrong date format makes
        every imported date wrong or empty.

10. In **Instrument File Name** on that row, select the file for this
    analyzer. Left empty, the row uses the **Instrument File** set above.
11. If the analyzer is a point-of-care device, tick **Is this a POC Device?**
    and enter its **Latitude** and **Longitude**. Both coordinates are needed.
    Without them the analyzer is not saved as POC.
12. To add another analyzer of the same model, select **+** on the row, then
    repeat steps 8 to 11 on the new row.
13. For each test type, enter the control counts. A row appears for each test
    type selected under **Supported Tests**. The counts tell InteLIS how many
    positions in a run are not patient samples:

    | Field | What to enter |
    | --- | --- |
    | Number of In-House Controls | In-house control positions per run |
    | Number of Manufacturer Controls | Manufacturer control positions per run |
    | No. Of Calibrators | Calibrator positions per run |

14. If the same people always sign off this analyzer's results, set
    **Default Reviewer** and **Default Approver** for each test type. Left
    empty, each result records whoever actually reviewed and approved it.
15. To print a fixed method statement on every result from this analyzer,
    enter it under **Description/Comment to add in Test Result**.
16. Select **Submit**.

## Retire an instrument

1. Go to **ADMIN → System Configuration → Instruments**.
2. Select **Edit** on the instrument.
3. Set **Status** to **Inactive**.
4. Select **Submit**.

**Status** exists on Edit Instrument only. A new instrument is saved as active.

## Confirm it worked

| Change | Check |
| --- | --- |
| New instrument | It is offered as the testing platform when creating a batch |
| Instrument File | Import one result file from the analyzer and read the imported rows |
| Date Format | The imported test dates match the analyzer's own |
| Low VL Result Text | An undetectable result imports as undetectable |
| Control counts | The batch position count matches the run |
