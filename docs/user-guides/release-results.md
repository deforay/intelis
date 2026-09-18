# How to release results to the requesting facility

Approved results reach the requesting facility as a printed PDF report, as an
email attachment, or as a spreadsheet export.

## Before starting

- Results approved. See [How to review and approve results](approve-results.md)
- Permission to print, email or export results

Two kinds of sample can be released:

- **Accepted** samples with a result.
- **Rejected** samples, which carry no result. Releasing them tells the facility
  to collect a new sample. Print them: email sends only samples with a result.

A sample still awaiting approval is never listed for printing or email.

## Release the results

**Choose how the results go out, then follow its steps from top to bottom.**

=== "Print"

    1. Go to **HIV VIRAL LOAD → Management → Print Result**.
    2. Stay on the **Results not yet Printed** tab.

        ??? info "To reprint a result"

            Switch to the **Results already Printed** tab and follow the same
            steps there.

    3. Filter to the results to print, for example by **Facility Name** to
       print one facility's reports together.

        ??? info "Other filters"

            | Filter | Use |
            | --- | --- |
            | Sample Test Date | Everything tested on one date |
            | Batch Code | One analyzer run |
            | Patient ID or Patient Name | One patient |
            | Province/State and District/County | A region |

    4. Select **Search**.
    5. Tick the results to print. The **Print Selected Results PDF** button
       appears.

        ??? info "To print one result"

            Select **Print** on that result's row instead, then go to step 7.

    6. Select **Print Selected Results PDF**.

        ??? failure "If InteLIS refuses more than 1000 results"

            One PDF holds at most 1000 results. Tick fewer rows, print them, then
            print the rest.

    7. The PDF opens in a new browser tab. Print it from there.
    8. Select the **Results already Printed** tab and search again. The printed
       results are listed there.

=== "Email"

    1. Go to **HIV VIRAL LOAD → Test Result Management → E-mail Test Result**.
    2. Choose the facility in **Facility Name (To)**. InteLIS sets the
       **Facility Name** filter to the same facility, lists its samples, and
       shows the address the email goes to.

        ??? failure "If InteLIS shows `No valid Email id available`"

            The facility has no email address recorded, and **Next** stays
            disabled. Ask the administrator to add one under **ADMIN →
            Facilities**.

    3. Check the **Subject** and the **Message**. Both are filled in already.
    4. Leave **Mail Sent Status** at **Samples Not yet Mailed**, so results
       already sent are left out.
    5. Keep **Facility Name** set to that one facility. Narrow with the other
       filters if needed, then select **Search**.
    6. Under **Choose Sample(s)**, select each sample to send, or select
       **Select All**. Selected samples move to the right-hand list.

        ??? failure "If InteLIS refuses the selection"

            One email holds at most 100 samples. Send the rest in a second
            email.

    7. Select **Next**. The next page lists the samples that go out.

        ??? info "If a rejected sample is missing from the list"

            Email sends only samples with a result. Print the rejected sample's
            report instead.

    8. Select **Send**.
    9. Go back to **E-mail Test Result**, set **Mail Sent Status** to
       **Already Mailed Samples**, and search. The sent results are listed.

=== "Export"

    Use this when a facility or programme wants the data rather than patient
    reports. Send patient reports as printed PDFs.

    1. Go to **HIV VIRAL LOAD → Management → Export Results**.
    2. Set **Status** to **Accepted**, and add **Rejected** if the rejections
       are wanted too.
    3. Set the other filters, for example **Facility Name** or **Sample Test
       Date**.
    4. Select **Search** and check the rows listed.
    5. Select **Download**. The spreadsheet opens in a new browser tab as a
       download.
