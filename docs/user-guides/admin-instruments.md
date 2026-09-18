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

- An account with administrator rights
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
        own lab. On a cloud instance, the list offers only the user's own lab.

5. Under **Supported Tests**, select every test type the analyzer runs.
6. Set **Instrument File**. It tells InteLIS how to read this analyzer's
   result files. Without it, file import fails.
7. Enter the result limits:

    | Field | What to enter |
    | --- | --- |
    | Lower Limit | The lowest value the analyzer reports, such as 20 |
    | Higher Limit | The highest value the analyzer reports, such as 10000000 |
    | Maximum No. of Samples In a Batch | How many samples fit in one run |
    | Low VL Result Text | Every text the analyzer writes for an undetectable result, separated by commas, such as `Target Not Detected, TND, < 20, < 40` |

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

10. If the analyzer is a point-of-care device, tick **Is this a POC Device?**
    and enter its **Latitude** and **Longitude**.
11. To add another analyzer of the same model, select **+** on the row, then
    repeat steps 8 to 10 on the new row.
12. For each test type, enter the control counts. They tell InteLIS how many
    positions in a run are not patient samples:

    | Field | What to enter |
    | --- | --- |
    | Number of In-House Controls | In-house control positions per run |
    | Number of Manufacturer Controls | Manufacturer control positions per run |
    | No. Of Calibrators | Calibrator positions per run |

13. If the same people always sign off this analyzer's results, set
    **Default Reviewer** and **Default Approver** for each test type. Left
    empty, each result records whoever actually reviewed and approved it.
14. To print a fixed method statement on every result from this analyzer,
    enter it under **Description/Comment to add in Test Result**.
15. Select **Submit**.

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
