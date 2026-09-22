---
description: Steps to register a viral load test request from a paper form, at the testing lab or at a health facility on the STS.
audience: [lab-staff, requesting-facility]
module: [vl]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# How to register a viral load test request

Register a sample that has a paper request form and no Sample ID, so it gets a
Sample ID and joins the testing queue.

For samples that arrive in a package with a manifest, do not register them one
by one. See [How to receive samples sent on a manifest](receive-referred-samples.md).

## Before starting

- A completed paper request form
- Permission to add test requests

## Forms per test type

Each test type has its own **Add New Request** form.

| Test type | Menu |
|---|---|
| HIV viral load | **HIV VIRAL LOAD → Request Management → Add New Request** |
| Early infant diagnosis | **EARLY INFANT DIAGNOSIS (EID) → Request Management → Add New Request** |
| Tuberculosis | **TUBERCULOSIS → Request Management → Add New Request** |
| Custom Tests | **OTHER LAB TESTS → Request Management → Add New Request** |

The steps below follow one country's viral load form. Other country forms use
different labels and fields. The other test types have their own forms. For
example, the EID form identifies the child by **Infant Code**, and the Custom
Tests form starts with **Test Type**. Fields marked with a red asterisk are
mandatory.

**Choose where the request is registered, then follow its steps from top to bottom.**

=== "At the testing lab"

    1. Go to **HIV VIRAL LOAD → Request Management → Add New Request**.
    2. If the lab prints barcode labels, leave **Print Barcode Label** ticked.

    ### Clinic Information

    3. Choose the **State/Province**.
    4. Choose the **District/County**. The list shows only the districts of the
       chosen province.
    5. Choose the **Clinic/Health Center** that collected the sample.

        ??? failure "If the facility is missing from the list"

            The facility is not created yet, or it is not linked to viral load
            testing. Ask the administrator to add it. See
            [How to administer InteLIS](administer-intelis.md).

    6. Choose the **Implementing Partner** and the **Funding Source**, if the
       paper form gives them.
    7. Choose the **Testing Lab**.

    ### Patient Information

    8. Enter the **ART (TRACNET) No.** exactly as it appears on the paper form.
       InteLIS shows the patient's earlier requests: **No. of times Test
       Requested for this Patient**, **Last Test Request Added On LIS/STS** and
       **Sample Collection Date for Last Request**.

        ??? warning "If the last request has the same collection date as the paper form"

            The sample is probably registered already. Do not save a second
            request. Search for the patient in **View Test Requests** first.

    9. Enter the **Date of Birth**.

        ??? info "If the paper form has no date of birth"

            Enter **If DOB unknown, Age in Years**. For a patient under one year
            old, enter **If Age < 1, Age in Months**.

    10. Enter the **Patient Name (First Name, Last Name)**.
    11. Choose the **Sex**.

    ### Sample Information

    12. Enter the **Date of Sample Collection** from the paper form, not the date
        of data entry. InteLIS fills the **Sample ID**. It cannot be typed.
    13. Enter the **Sample Dispatched On** date.
    14. Choose the **Sample Type**.
    15. Enter the **Date Sample Received at Testing Lab**.

    ### Treatment and indication

    16. Fill **Treatment Information** from the paper form: **Date of Treatment
        Initiation**, **Current Regimen**, **Date of Initiation of Current
        Regimen** and **ARV Adherence**.
    17. Choose the **Indication for Viral Load Testing**: **Routine Monitoring**,
        **Repeat VL test after suspected treatment failure adherence
        counselling** or **Suspect Treatment Failure**.
    18. Leave **Laboratory Information** empty. It is filled when the result is
        captured. See [How to capture viral load results](capture-results.md).

    ### Save

    19. Select **Save** to return to the request list, or **Save and Next** to
        open a new form for the next paper form.

        ??? info "If Save and Next carries details over to the new form"

            The lab is configured to copy the request into the next form. Check
            every carried-over field against the next paper form before saving.

        ??? failure "If no barcode printer is listed"

            Select **Change/Retry** to pick the printer.

    20. Go to **HIV VIRAL LOAD → Request Management → View Test Requests**.
    21. Search for the patient identifier or the Sample ID. The request shows the
        status **Sample Registered at Testing Lab**.

        To correct a mistake, select **Edit** on the row.

    Next, add the sample to a batch. See
    [How to batch samples for testing](batch-samples.md).

=== "At a health facility (STS)"

    1. Go to **HIV VIRAL LOAD → Request Management → Add New Request**.
    2. If the facility prints barcode labels, leave **Print Barcode Label**
       ticked.

    ### Clinic Information

    3. Choose the **State/Province**.
    4. Choose the **District/County**. The list shows only the districts of the
       chosen province.
    5. Choose the **Clinic/Health Center** that collected the sample.

        ??? failure "If the facility is missing from the list"

            The facility is not created yet, or it is not linked to viral load
            testing. Ask the administrator to add it. See
            [How to administer InteLIS](administer-intelis.md).

    6. Choose the **Implementing Partner** and the **Funding Source**, if the
       paper form gives them.
    7. Choose the **Testing Lab** the sample goes to.

    ### Patient Information

    8. Enter the **ART (TRACNET) No.** exactly as it appears on the paper form.
       InteLIS shows the patient's earlier requests: **No. of times Test
       Requested for this Patient**, **Last Test Request Added On LIS/STS** and
       **Sample Collection Date for Last Request**.

        ??? warning "If the last request has the same collection date as the paper form"

            The sample is probably registered already. Do not save a second
            request. Search for the patient in **View Test Requests** first.

    9. Enter the **Date of Birth**.

        ??? info "If the paper form has no date of birth"

            Enter **If DOB unknown, Age in Years**. For a patient under one year
            old, enter **If Age < 1, Age in Months**.

    10. Enter the **Patient Name (First Name, Last Name)**.
    11. Choose the **Sex**.

    ### Sample Information

    12. Enter the **Date of Sample Collection** from the paper form, not the date
        of data entry. InteLIS fills the **Sample ID**. It cannot be typed.
    13. Enter the **Sample Dispatched On** date.
    14. Choose the **Sample Type**.
    15. Leave **Date Sample Received at Testing Lab** empty. The testing lab
        fills it.

    ### Treatment and indication

    16. Fill **Treatment Information** from the paper form: **Date of Treatment
        Initiation**, **Current Regimen**, **Date of Initiation of Current
        Regimen** and **ARV Adherence**.
    17. Choose the **Indication for Viral Load Testing**: **Routine Monitoring**,
        **Repeat VL test after suspected treatment failure adherence
        counselling** or **Suspect Treatment Failure**.

    ### Save

    18. Select **Save** to return to the request list, or **Save and Next** to
        open a new form for the next paper form.

        ??? info "If Save and Next carries details over to the new form"

            The installation is configured to copy the request into the next
            form. Check every carried-over field against the next paper form
            before saving.

        ??? failure "If no barcode printer is listed"

            Select **Change/Retry** to pick the printer.

    19. Go to **HIV VIRAL LOAD → Request Management → View Test Requests**.
    20. Search for the patient identifier or the Sample ID. The request shows the
        status **Sample Currently Registered at Health Center**.

        To correct a mistake, select **Edit** on the row.

    Next, send the samples to the testing lab. See
    [How to send samples to a testing lab on a manifest](send-samples-on-a-manifest.md).
