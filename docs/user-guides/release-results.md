# How to release results to the requesting facility

Approved results reach the facility as a printed report or as an email
attachment. This guide covers both.

## Before starting

- Results approved. See [How to review and approve results](approve-results.md)
- Permission to print or email results

Only approved results can be released. A result still awaiting approval does not
appear on either page.

## Print results

1. Go to **HIV VIRAL LOAD → Management → Print Result**.
2. Stay on the **Results not yet Printed** tab.
3. Filter to the results to print.

| Filter | Use |
|---|---|
| Facility Name | One facility's results, ready to send together |
| Sample Test Date | Everything tested on one date |
| Batch Code | One analyzer run |
| Patient ID or Patient Name | One patient |
| Province/State and District/County | A region |

4. Select **Search**.
5. Tick the results to print.
6. Select **Print Selected Results PDF**.

InteLIS builds one PDF holding every selected report.

The limit is 1000 results at a time. Selecting more than that stops the print.
Split the work across several prints.

<!-- SCREENSHOT NEEDED
     Page: HIV VIRAL LOAD → Management → Print Result
     Capture: the Results not yet Printed tab with results selected
     Highlight: the two tabs and the Print Selected Results PDF button
-->

### Reprint a result

Switch to the **Results already Printed** tab, find the result, and print it
again. The two tabs exist so a batch of reports can be printed once without
reprinting what has already gone out.

## Email results

1. Go to **HIV VIRAL LOAD → Test Result Management → E-mail Test Result**.
2. Choose the facility in **Facility Name (To)**.
3. Enter a **Subject** and a **Message**.
4. Filter to the results to send.

Set **Mail Sent Status** to **Samples Not yet Mailed** to leave out anything
already sent.

5. Select **Search**.
6. Tick the results, or use **Select All**.
7. Select **Next** and confirm.

The limit is 100 samples per email. Selecting more than that stops the send.

The results go to the email addresses recorded against the facility. If a
facility has no address recorded, ask the administrator to add one under
**ADMIN → Facilities**.

<!-- SCREENSHOT NEEDED
     Page: HIV VIRAL LOAD → Test Result Management → E-mail Test Result
     Capture: the subject, recipient facility, and message filled, with results selected below
     Highlight: the Mail Sent Status filter
-->

## Export results to a spreadsheet

Where a facility or programme wants the data rather than the reports, export it.

1. Go to **HIV VIRAL LOAD → Management → Export Results**.
2. Set the filters.
3. Select the export option.

The export gives a spreadsheet, not patient reports. Send patient reports as
PDFs.

## Confirm it worked

For printing, the results move from **Results not yet Printed** to **Results
already Printed**.

For email, set **Mail Sent Status** to **Already Mailed Samples** and search.
The results appear there.
