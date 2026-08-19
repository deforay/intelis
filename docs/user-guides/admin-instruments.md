# How to set up instruments and interfacing

This guide registers an analyzer under **ADMIN → System Configuration →
Instruments** so InteLIS can read its results.

An instrument that is not registered cannot be chosen as a testing platform, and
its result files cannot be imported.

## Before starting

- An account with administrator rights
- The testing lab created already under **ADMIN → Facilities**
- A sample result file exported from the analyzer

## Add an instrument

1. Go to **ADMIN → System Configuration → Instruments**.
2. Select **Add Instrument**.
3. Fill in the details.

| Field | What to enter |
|---|---|
| Instrument Name | The manufacturer or platform, such as Roche or Abbott |
| Machine Name | The name of this individual machine |
| Testing Lab | The lab the machine sits in |
| Supported Tests | Every test type this machine runs |
| Instrument File | The configuration file that tells InteLIS how to read this machine's result files |
| Maximum No. of Samples In a Batch | How many samples fit in one run |
| Is this a POC Device? | Whether this is a point-of-care device |
| Latitude, Longitude | Where the machine is, for the referral network map |
| Status | Active or inactive |

4. Select **Submit**.

The **Instrument File** is what makes file import work. Without it, results
exported from the analyzer cannot be read. See
[How to capture viral load results](capture-results.md).

## Set the result limits

The limits decide how a numeric result is displayed and interpreted.

| Field | What to enter |
|---|---|
| Lower Limit | The lowest value the machine reports, such as 20 |
| Higher Limit | The highest value the machine reports, such as 10000000 |
| Low VL Result Text | The exact text the machine writes for an undetectable result, such as `Target Not Detected, TND, < 20, < 40`. Separate alternatives with commas |

Enter every wording the machine uses in **Low VL Result Text**. A wording that
is missing from the list is imported as an unrecognised result rather than as
undetectable.

## Let InteLIS detect the machine's date format

Result files carry dates in the machine's own format. InteLIS reads that format
from a sample.

1. Find **Date Format**.
2. Paste one date exactly as the machine writes it, such as `06.19.2025 11:19 AM`.
3. InteLIS detects the format from it.

An undetected date format makes every imported date wrong or empty.

## Set the quality control counts

Each test type the machine runs carries its own control counts. They tell InteLIS
how many positions in a run are not patient samples.

| Field | What to enter |
|---|---|
| No. Of Calibrators | How many calibrator positions this test type uses |
| Number of Manufacturer Controls | How many manufacturer control positions |
| Number of In-House Controls | How many in-house control positions |

Set these per test type. A machine running viral load and TB carries one set for
each.

## Set the default reviewer and approver

**Default Reviewer** and **Default Approver** pre-fill the reviewer and approver
names on results that arrive from this machine.

Set them only where the same people always sign off that machine's results.
Leaving them empty makes each result record whoever actually signed it.

## Add a description to this machine's results

**Description/Comment to add in Test Result** adds a fixed comment to every
result from this machine. Use it for a method statement that belongs on every
report from that platform.

## Confirm it worked

| Change | Check |
|---|---|
| New instrument | It appears in Testing Platform when creating a batch |
| Instrument file | Import a result file from the machine and read the imported rows |
| Date format | The imported Sample Tested On dates match the machine's own |
| Low VL Result Text | An undetectable result imports as undetectable, not as unrecognised |
| Control counts | The batch position count matches the run |
