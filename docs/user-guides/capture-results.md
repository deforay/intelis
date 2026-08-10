# How to capture viral load results

Once a batch has run on the analyzer, the results have to reach InteLIS. There
are three ways to do that. Use the highest one on this list that the lab and the
analyzer support.

| Method | Use it when |
|---|---|
| [Interface Tool](#method-1-interface-tool) | The analyzer is connected to the Interface Tool |
| [File import](#method-2-file-import) | The analyzer cannot connect but can export a result file |
| [Manual entry](#method-3-manual-entry) | Neither of the above is possible |

Manual entry is a fallback. Every result typed by hand can be mistyped, so it
always needs a second person to approve it.

## Before starting

- A batch that has finished running on the analyzer
- Permission to record results

---

## Method 1: Interface Tool

The Interface Tool runs on a computer in the lab, listens to the analyzer, and
passes results to InteLIS. Nobody types a result and nobody uploads a file.

### Check the Interface Tool is ready

1. Confirm the Interface Tool is installed and running on the lab computer.
2. Confirm the tool is on the current version.
3. Open the Interface Tool and check the analyzer shows as **Connected**.

Some analyzers only open the connection when they have something to send. A tool
that is not showing **Connected** between runs is not necessarily faulty. Check
again while the analyzer is releasing results.

### Release the results

Some analyzers hold results until an operator releases them. Where the analyzer
offers manual release, release the run once the operator has reviewed it.

Where the analyzer does not, it releases results on its own schedule. Nothing
needs to be done at the analyzer.

### Wait for the results to appear

Results reach InteLIS on their own once the analyzer has released them. There is
no import step and no button to press.

Go to **HIV VIRAL LOAD → Request Management → View Test Requests** and search on
the batch code. Samples that have arrived carry a result.

If the lab has turned on automatic approval for interface results, those results
are ready to print. If not, they wait for approval. See
[How to review and approve results](approve-results.md).

### If results do not arrive

Work through these in order.

1. Check the analyzer has actually released the run.
2. Check the Interface Tool is running and shows the analyzer as **Connected**.
3. Check the Sample IDs on the analyzer match the Sample IDs in InteLIS. A
   result carrying an unrecognised ID does not attach to a sample.
4. Ask the administrator to check the lab's Interface Tool connection under
   **ADMIN → Facilities**, then the testing lab, then **Interface Tool
   Connections** at the foot of the page. The installation shows a status and a
   **Last Seen** time.

If results still do not arrive, use file import for the run and raise the
connection problem with the administrator.

<!-- SCREENSHOT NEEDED
     Page: ADMIN → Facilities → (a testing lab) → Interface Tool Connections
     Capture: the Connected Installations table
     Highlight: the Status and Last Seen columns
-->

---

## Method 2: File import

Use this when the analyzer cannot reach the Interface Tool but can write a
result file.

### Export the file from the analyzer

Export the results for the run from the analyzer. The file must carry the
InteLIS Sample IDs, which is what the batch PDF put on the analyzer in the first
place. Supported file types are xls, xlsx, csv, and txt.

### Upload the file

1. Go to **HIV VIRAL LOAD → Test Result Management → Import Results From File**.
2. Choose the **Instrument/Platform Name**.
3. Choose the **Specific Machine Name/Code**.
4. Choose the **Testing Lab Name**.
5. Select the exported file under **Upload File**.
6. Select **Submit**.

Choose the analyzer carefully. Every analyzer writes its file differently, and
InteLIS reads the file according to the analyzer selected here. The wrong choice
produces a garbled import or none at all.

Where the file's date format is not recognised, paste a date copied from the
file into the date format field. InteLIS works out the format from it.

<!-- SCREENSHOT NEEDED
     Page: HIV VIRAL LOAD → Test Result Management → Import Results From File
     Capture: the form with instrument, machine, lab, and file chosen
     Highlight: the Instrument/Platform Name and Specific Machine Name/Code fields
-->

### Review what was imported

InteLIS lists every row it read from the file, with a **Sample source** note on
each.

| Note | Meaning | What to do |
|---|---|---|
| Result for Sample ID from VLSM | The Sample ID matches a registered sample | Accept it |
| Sample ID not from VLSM | The ID does not match any registered sample | Do not accept. Find why the ID differs |
| Result already exists for this sample | The sample already has a result | Only overwrite if the new result is the correct one |
| Test date ~1+ month from collection | The test date is a month or more after collection | Check the date is right |
| Test date ~1+ year from collection | The test date is a year or more after collection | Check the date is right. A year's gap is usually a typing error |

Set a **Status** on every row. Set **Tested By**, **Reviewed By**, and
**Approved By**.

To mark every row at once, select **Accept All Samples**. It only sets rows that
have no status yet, so rows already marked as rejected stay rejected.

InteLIS refuses to submit while any row is missing a test date.

Depending on the lab's configuration, InteLIS either warns or refuses when the
same person is set as both reviewer and approver.

7. Select **Save**.

<!-- SCREENSHOT NEEDED
     Page: the imported results review screen
     Capture: several rows with different Sample source notes
     Highlight: the Sample source column and the Accept All Samples button
-->

---

## Method 3: Manual entry

Use this only when the analyzer can neither connect nor export a file.

1. Go to **HIV VIRAL LOAD → Test Result Management → Enter Result Manually**.
2. Filter to find the sample. Set **Status** to **Results Not Recorded** to see
   only samples still waiting.
3. Select **Enter Result** on the sample's row.
4. Fill the laboratory section of the form.

| Field | What to enter |
|---|---|
| Date Sample Received at Testing Lab | The date the sample reached the lab |
| Sample Testing Date | The date the analyzer ran the sample |
| VL Testing Platform | The analyzer that ran it |
| Viral Load Result | The result as reported by the analyzer |
| Reviewed By, Tested By, Approved By | The staff responsible |
| Lab Tech. Comments | Anything the report should carry |

5. Select **Save**.

Read the result back off the screen against the analyzer printout before saving.

A manually entered result is not released until it is approved. See
[How to review and approve results](approve-results.md).

<!-- SCREENSHOT NEEDED
     Page: HIV VIRAL LOAD → Test Result Management → Enter Result Manually
     Capture: the list filtered to Results Not Recorded
     Highlight: the Enter Result button on a row
-->

---

## If the sample was rejected

Where the sample cannot be tested, record the rejection instead of a result.
Set **Is Sample Rejected?** on the form, choose a **Rejection Reason**, and set
the **Rejection Date**.

A rejected sample carries the reason through to the report and into the sample
rejection report.

## If the test failed

Where the analyzer returned a failure or an invalid reading, record it as failed
and give the **Reason for Failure**. Failed samples collect on their own page for
retesting. See [How to handle failed and held samples](failed-and-held-samples.md).

## Confirm it worked

Go to **HIV VIRAL LOAD → Request Management → View Test Requests** and search on
the batch code.

Every sample in the run carries either a result, a rejection, or a failure.
Samples still showing no result did not reach InteLIS. Check their Sample IDs
against the analyzer.

## Next

[Review and approve the results](approve-results.md).
