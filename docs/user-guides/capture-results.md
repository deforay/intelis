# How to capture viral load results

Get the results of a finished analyzer run into InteLIS.

## Before starting

- A batch that has finished running on the analyzer
- Permission to record results

**Choose how the results reach InteLIS, then follow its steps from top to bottom.**

=== "Interface Tool"

    Use this when the analyzer is connected to the Interface Tool. The Interface
    Tool carries HIV viral load, EID and hepatitis results. It does not carry TB
    or Custom Tests results.

    1. On the analyzer, release the run. Skip this step if the analyzer releases
       results on its own.
    2. On the lab computer, open the Interface Tool.
    3. Check the analyzer shows as **Connected**.

        ??? info "If the analyzer is not showing Connected between runs"

            Some analyzers open the connection only when they have results to
            send. Check again while the analyzer is releasing the run.

    4. In InteLIS, go to **HIV VIRAL LOAD → Request Management → View Test
       Requests**.
    5. Search on the batch code. Every sample that has arrived carries a result.

        ??? failure "If results do not arrive"

            Work through these in order.

            1. Check the analyzer has released the run.
            2. Check the Interface Tool is running and shows the analyzer as
               **Connected**.
            3. Check the Sample IDs on the analyzer match the Sample IDs in
               InteLIS. A result with an unknown Sample ID attaches to no sample.
            4. Ask the administrator to open **ADMIN → Facilities**, edit the
               testing lab, and check the **Interface Tool Connections** panel at
               the foot of the page. Each connected installation shows a
               **Status** and a **Last Seen** time. The panel appears only on
               installations where the lab's support contact has turned on
               Interface Tool connections. Without the panel, ask the support
               contact to check the connection.

            If results still do not arrive, capture the run with **File import**.

    6. Check the status of the results. Interface results arrive approved, with
       the status **Accepted**, ready to print.

        ??? info "If the results show Awaiting Approval"

            The lab's support contact has turned off automatic approval of
            interface results for this installation. Approve them. See
            [How to review and approve results](approve-results.md).

=== "File import"

    Use this when the analyzer cannot reach the Interface Tool but can export a
    result file.

    1. On the analyzer, export the results of the run as an xls, xlsx, csv or txt
       file. The file carries the Sample IDs from the batch PDF.
    2. In InteLIS, go to **HIV VIRAL LOAD → Test Result Management → Import
       Results From File**.
    3. Choose the analyzer that ran the batch in **Instrument/Platform Name**.

        ??? failure "If the import comes out garbled or empty"

            InteLIS reads the file in the layout of the analyzer chosen here. Start
            again and choose the analyzer that produced the file.

    4. Choose the **Specific Machine Name/Code**.
    5. Check **Date Format**. If the analyzer has a pre-configured format, it is
       already filled. If not, paste a date copied from the file. InteLIS works
       out the format from it.
    6. Choose the **Testing Lab Name**.
    7. Select the file under **Upload HIV Viral Load File**.
    8. Select **Submit**. InteLIS lists every row it read, with a **Sample
       source** note on each.
    9. Check the **Sample source** note on each row.

        | Note | Meaning | Action |
        |---|---|---|
        | Result for Sample ID from VLSM | The Sample ID matches a registered sample | Accept it |
        | Sample ID not from VLSM | The Sample ID matches no registered sample | Do not accept. Find why the Sample ID differs |
        | Result already exists for this sample | The sample already has a result | Overwrite only if the new result is the correct one |
        | Test date ~1+ month from collection | The test date is a month or more after collection | Check the date |
        | Test date ~1+ year from collection | The test date is a year or more after collection | Check the date. A gap of a year is usually a typing error |

    10. Set **Status** on each row: **Accepted**, **Hold**, **Rejected** or
        **Failed**. For a row set to **Rejected**, choose the **Rejection
        Reason**.

        ??? info "To accept every row at once"

            Select **Accept All Samples**. It sets only the rows that have no
            status yet. Rows already set to **Rejected** stay rejected.

    11. Choose **Tested By**, **Reviewed By** and **Approved By**.

        ??? failure "If InteLIS says the same person is reviewing and approving"

            The lab's configuration decides the outcome. Either InteLIS asks for
            confirmation, or it refuses. If it refuses, choose a different
            person for **Approved By**.

    12. Select **Save**.

        ??? failure "If InteLIS says one or more samples do not have a test date"

            Enter the missing test date on each row that has none, then select
            **Save** again.

=== "Manual entry"

    Use this only when the analyzer can neither connect nor export a file.

    1. Go to **HIV VIRAL LOAD → Test Result Management → Enter Result
       Manually**.
    2. In the drop-down above the list, choose **Results Not Recorded**. The list
       shows only samples still waiting for a result.
    3. Select **Enter Result** on the sample's row.
    4. Fill the **Laboratory Information** section.

        | Field | Entry |
        |---|---|
        | Date Sample Received at Testing Lab | The date the sample reached the lab |
        | Sample Testing Date | The date the analyzer ran the sample |
        | VL Testing Platform | The analyzer that ran the sample |
        | Viral Load Result (copies/mL) | The result as printed by the analyzer |
        | Reviewed By, Tested By, Approved By | The staff responsible |
        | Lab Tech. Comments | Any comment the report must carry |

        Country forms differ in places. For example, the South Sudan form labels
        the analyzer field **Testing Platform**.

        ??? failure "If the sample was rejected"

            Record the rejection in place of a result.

            1. Set **Is Sample Rejected?** to **Yes**.
            2. Choose the **Rejection Reason**.
            3. Set the **Rejection Date**.

            The reason appears on the result report and in the sample rejection
            report.

        ??? failure "If the analyzer returned a failure"

            1. Enter `Failed` as the **Viral Load Result (copies/mL)**.
            2. Choose the **Reason for Failure**.

            The sample moves to **Failed/Hold Samples** for a retest. See
            [How to handle failed and held samples](failed-and-held-samples.md).

    5. Read the result on the screen against the analyzer printout.
    6. Select **Save**. The result waits at **Awaiting Approval**. See
       [How to review and approve results](approve-results.md).

=== "Custom Tests"

    Custom Tests have no Interface Tool or file import. Their results are always
    entered by hand, one test card per test.

    1. Go to **OTHER LAB TESTS → Test Result Management → Enter Result
       Manually**.
    2. Select **Enter Result** on the sample's row.
    3. Select **Add Test** for a test performed on the sample.
    4. Record that test's result on its card. A card records a test done at this
       lab or a test referred to another lab.
    5. Repeat steps 3 and 4 for every test performed on the sample.
    6. Set **Enter the Final Interpretation?** to **Yes**.

        ??? warning "Record every test card first"

            Entering the final interpretation locks further tests and referrals
            on the sample.

    7. Enter the **Final Interpretation**.

        ??? failure "If the sample stays at Sample Registered at Testing Lab"

            The final interpretation is missing. Without it, the sample stays at
            **Sample Registered at Testing Lab** however many test cards are
            saved, and it never reaches the approval queue. Open the sample
            again and complete steps 6 and 7.

    8. Select **Save**.

## Confirm it worked

1. Go to **View Test Requests** under **Request Management** for the test type.
2. Search on the batch code.

Every sample in the run carries a result, a rejection or a failure. A sample
with none of these did not reach InteLIS. Check its Sample ID against the
analyzer.

## Next

[Review and approve the results](approve-results.md).
