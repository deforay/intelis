# How to review and approve results

A result reaches the requesting facility only after it is approved. Approval
confirms that the result in InteLIS is the one the analyzer produced, for the
right sample.

Results imported from a file or entered by hand always pass through this page.
Results sent in through the Interface Tool may be approved automatically, if the
lab is configured that way.

## Before starting

- Results captured in InteLIS. See [How to capture viral load results](capture-results.md)
- Permission to manage result status
- The analyzer printout or worklist for the run

## Change a sample's status

**Choose the action, then follow its steps from top to bottom.**

=== "Accept"

    Use this to approve results that match the analyzer printout.

    1. Go to **HIV VIRAL LOAD → Test Result Management → Manage Results Status**.
    2. Set **Show Samples that are** to **Not Approved/Rejected**. This lists
       samples that have a result and are waiting for approval.
    3. Set **Batch Code** to the analyzer run. One run on screen can be checked
       against one printout.

        ??? info "Other filters"

            | Filter | Use |
            | --- | --- |
            | Sample Test Date | Everything tested on one date |
            | Facility Name | Samples from one health facility |
            | Sample Collection Date | A collection date range |
            | Sample Type | One specimen type |
            | Manifest Code | Samples from one incoming package |

    4. Select **Search**.
    5. For each row, check against the printout that the **Sample ID** matches,
       the **Result** matches, and the patient is the one expected for that
       Sample ID.

        ??? failure "If the Sample ID and the patient do not match"

            The sample was registered against the wrong patient, or loaded into
            the wrong analyzer position. Do not tick the row. It stays under
            **Not Approved/Rejected** and is not released. Correct the
            registration or the result, then come back to step 1.

        ??? warning "Custom Tests: check the test cards first"

            For Custom Tests, go to **OTHER LAB TESTS → Test Result Management →
            Manage Results Status**. The steps are the same. The list shows only
            the sample's final interpretation, not the test cards behind it.
            Open the sample's result screen and check every test card before
            ticking the row.

    6. Tick the rows that match.
    7. In **Bulk Actions**, set **Status** to **Accepted**.
    8. Set **Approver**. Set **Tester** and **Reviewer** if the lab records them.

        ??? info "Names already recorded on the sample"

            A name already on the sample is kept. To overwrite it, tick
            **Replace existing** under that field.

        ??? info "If the same person is chosen for two roles"

            InteLIS asks for confirmation. Select **OK** only if the lab allows
            one person to hold both roles.

    9. Select **Apply**.
    10. Select **OK** to confirm. InteLIS shows `Updated successfully.`

        ??? failure "If the message lists samples not accepted"

            `Not accepted because no result is recorded` names samples that have
            no result. They keep their status. Enter the result first. See
            [How to capture viral load results](capture-results.md).

        ??? info "If an accepted sample shows Failed/Invalid"

            A result that reads as a failure or an invalid run cannot be
            accepted. InteLIS sets the status to **Failed/Invalid** instead. See
            [How to handle failed and held samples](failed-and-held-samples.md).

    11. Set **Show Samples that are** to **Already Approved/Rejected**, then
        select **Search**. The **Status** column of the accepted samples shows
        `Accepted`.

    Next: [release the results to the requesting facility](release-results.md).

=== "Reject"

    Use this when the sample was not fit to test, such as a haemolysed or
    insufficient specimen.

    1. Go to **HIV VIRAL LOAD → Test Result Management → Manage Results Status**.
    2. Set **Show Samples that are** to **Not Approved/Rejected**.

        ??? info "If the sample has no result yet"

            **Not Approved/Rejected** lists only samples with a result. Set
            **Show Samples that are** to **Available for Cancellation** instead.
            It lists every sample that is not already Expired or Cancelled.

    3. Filter to the sample, for example by **Batch Code** or **Facility Name**.
    4. Select **Search**.
    5. Tick the samples to reject.
    6. In **Bulk Actions**, set **Status** to **Rejected**.
    7. Choose a **Rejection Reason**. Choose the reason that tells the facility
       what to change next time. It appears on the report sent to the facility
       and in the sample rejection report.

        !!! warning "Rejecting removes the result"

            A result already recorded is cleared from the sample. InteLIS keeps
            the cleared result in the sample's test history.

    8. Select **Apply**.
    9. Select **OK** to confirm. InteLIS shows `Updated successfully.`
    10. Go to **HIV VIRAL LOAD → Request Management → View Test Requests** and
        search for the sample. The **Status** column shows `Rejected`.

    Next: [release the rejection to the requesting facility](release-results.md),
    so the facility can collect a new sample.

=== "Mark lost"

    Use this when the sample cannot be found and will not be tested.

    1. Go to **HIV VIRAL LOAD → Test Result Management → Manage Results Status**.
    2. Set **Show Samples that are** to **Available for Cancellation**. It lists
       every sample that is not already Expired or Cancelled, with or without a
       result.
    3. Filter to the sample, for example by **Facility Name** or **Sample
       Collection Date**.
    4. Select **Search**.
    5. Tick the samples.
    6. In **Bulk Actions**, set **Status** to **Lost**.
    7. Select **Apply**.
    8. Select **OK** to confirm. InteLIS shows `Updated successfully.`
    9. Go to **HIV VIRAL LOAD → Request Management → View Test Requests** and
       search for the sample. The **Status** column shows `Lost`.

=== "Cancel"

    Use this only when testing will not happen at all, such as a request
    entered twice or withdrawn by the clinician.

    !!! warning "Do not cancel a sample that failed"

        A cancelled sample counts as never tested. It drops out of testing
        counts and turnaround time. A failed sample stays in the failure rate.
        For a failed sample, see
        [How to handle failed and held samples](failed-and-held-samples.md).

    1. Go to **HIV VIRAL LOAD → Test Result Management → Manage Results Status**.
    2. Set **Show Samples that are** to **Available for Cancellation**.
    3. Filter to the sample, for example by **Facility Name** or **Sample
       Collection Date**.
    4. Select **Search**.
    5. Tick the samples.
    6. In **Bulk Actions**, set **Status** to **Cancelled**.
    7. Select **Apply**. The **Confirm Cancellation** box opens.
    8. Type `CANCEL` in the box.
    9. Select **Confirm Cancellation**. InteLIS shows `Updated successfully.`
    10. Go to **HIV VIRAL LOAD → Request Management → View Test Requests** and
        search for the sample. The **Status** column shows `Cancelled`.

## Correct an approved result

The Manage Results Status page changes the status and the staff names only. It
never changes the result value. To correct a wrong value:

1. Go to **HIV VIRAL LOAD → Test Result Management → Enter Result Manually**.
2. Set the list filter to **Results Recorded**.
3. Find the sample and select **Enter Result**.

    ??? failure "If the row shows Locked"

        Samples lock after the number of days set in **Sample Lock Days** under
        **ADMIN → System Configuration → General Configuration**. Ask the
        administrator to correct a locked sample.

4. Enter the correct result.
5. Fill in the reason for changing the result.
6. Save the form. The sample returns to **Awaiting Approval**.
7. Approve it again with the **Accept** steps above.

If the wrong result had already been printed or emailed, release the corrected
result again. See [How to release results](release-results.md).
