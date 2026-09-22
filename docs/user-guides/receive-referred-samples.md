---
description: Steps to activate every sample in a package that arrives with a manifest, including packages with missing or damaged tubes.
audience: [lab-staff]
module: [vl]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# How to receive samples sent on a manifest

Register every sample in a package that arrives with a manifest, by entering
the manifest code once.

The **Add Samples from Manifest** menu appears on a lab's own InteLIS (LIS). On
the central system (STS), it appears only for users whose role has the
**Testing Lab** access type. Health facility users never see it. It does not
appear on a standalone installation.

Before starting:

- The package and the printed manifest inside it
- The date and time the package arrived at the lab

The steps use viral load. For another test, open **Add Samples from Manifest**
under that test's **Request Management** menu.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Every tube arrived"

    1. Count the tubes against the printed manifest.
    2. Go to **HIV VIRAL LOAD → Request Management → Add Samples from
       Manifest**.
    3. Type or scan the manifest code into **Enter Sample Manifest Code**.
    4. Select **Submit**. The samples on the manifest appear in the table.

        ??? failure "If a message appears instead of the samples"

            | Message | Cause | What to do |
            | --- | --- | --- |
            | Please enter the Sample Manifest Code | The code field is empty | Enter the code, then select **Submit** |
            | No manifest found with code … | No manifest with that code was sent to this lab | Check each character of the code. Ask the sender which lab the manifest names |
            | Manifest … is registered to a different testing lab and cannot be activated here | The manifest names another lab | Ask the sender to move the manifest to this lab. See [Move manifests to another lab](send-samples-on-a-manifest.md) |
            | Could not retrieve samples for manifest … Please try again or contact support | The STS holds no samples for this code for this lab | Check the code. Ask the sender which lab the manifest names |
            | Unable to sync manifest … | The STS did not answer | Select **Submit** again. If it repeats, contact support |
            | Unable to verify manifest | The manifest could not be checked | Select **Submit** again. If it repeats, contact support |
            | Some error occurred while processing the manifest | The manifest could not be processed | Select **Submit** again. If it repeats, contact support |
            | Invalid server response while processing manifest … | The STS sent an answer InteLIS could not read | Select **Submit** again. If it repeats, contact support |

            The table shows **Please enter a valid Manifest Code to activate**
            until a manifest loads. It is not an error.

    5. Check that the number of rows matches the tubes on the bench.
    6. Set **Sample Received at Testing Lab** to the date and time the package
       arrived. The date is required. Without it, InteLIS shows **Please select
       when the samples were received at the Testing Lab**.
    7. Select **Activate Samples**. The message **Samples from this Manifest
       have been activated** appears. If every sample already had a Sample ID,
       no message appears. The received date is still saved.

        ??? warning "Activate a manifest once"

            Activating the same manifest again overwrites the received date of
            every sample on it. Test dates and statuses are kept.

    8. Add the samples to a batch. See
       [How to batch samples for testing](batch-samples.md).

=== "Tubes missing or damaged"

    1. Count the tubes against the printed manifest. Note the Sample IDs of the
       missing or damaged tubes.
    2. Go to **HIV VIRAL LOAD → Request Management → Add Samples from
       Manifest**.
    3. Type or scan the manifest code into **Enter Sample Manifest Code**.
    4. Select **Submit**. The samples on the manifest appear in the table.

        ??? failure "If a message appears instead of the samples"

            | Message | Cause | What to do |
            | --- | --- | --- |
            | Please enter the Sample Manifest Code | The code field is empty | Enter the code, then select **Submit** |
            | No manifest found with code … | No manifest with that code was sent to this lab | Check each character of the code. Ask the sender which lab the manifest names |
            | Manifest … is registered to a different testing lab and cannot be activated here | The manifest names another lab | Ask the sender to move the manifest to this lab. See [Move manifests to another lab](send-samples-on-a-manifest.md) |
            | Could not retrieve samples for manifest … Please try again or contact support | The STS holds no samples for this code for this lab | Check the code. Ask the sender which lab the manifest names |
            | Unable to sync manifest … | The STS did not answer | Select **Submit** again. If it repeats, contact support |
            | Unable to verify manifest | The manifest could not be checked | Select **Submit** again. If it repeats, contact support |
            | Some error occurred while processing the manifest | The manifest could not be processed | Select **Submit** again. If it repeats, contact support |
            | Invalid server response while processing manifest … | The STS sent an answer InteLIS could not read | Select **Submit** again. If it repeats, contact support |

            The table shows **Please enter a valid Manifest Code to activate**
            until a manifest loads. It is not an error.

    5. Set **Sample Received at Testing Lab** to the date and time the package
       arrived. The date is required. Without it, InteLIS shows **Please select
       when the samples were received at the Testing Lab**.
    6. Select **Activate Samples**. Activation covers every sample on the
       manifest, including the missing ones. The message **Samples from this
       Manifest have been activated** appears. If every sample already had a
       Sample ID, no message appears. The received date is still saved.

        ??? warning "Activate a manifest once"

            Activating the same manifest again overwrites the received date of
            every sample on it. Test dates and statuses are kept.

    7. Mark each missing tube **Lost** and reject each damaged sample with a
       reason. See [How to review and approve results](approve-results.md).
    8. Tell the sender which samples were rejected or lost, so they can collect
       again.
    9. Add the remaining samples to a batch. See
       [How to batch samples for testing](batch-samples.md).

## Confirm it worked

1. Go to **HIV VIRAL LOAD → Request Management → View Test Requests**.
2. Filter on the manifest code.

Every sample from the package has a Sample ID and the status **Sample Registered
at Testing Lab**.

??? info "What activation does"

    Activation issues a lab Sample ID to every sample on the manifest that does
    not have one yet. It records the received date and moves each sample to
    **Sample Registered at Testing Lab**. Until then, the samples carry only
    the identifier the sending facility used, and are not ready for testing.

    | Column | Meaning |
    | --- | --- |
    | **Sample ID** | The identifier this lab issues, used on the analyzer and on the report |
    | **Remote Sample ID** | The identifier the sending facility used, kept so the facility can trace the sample |

    On a LIS, **Submit** checks the manifest against the STS every time, and
    fetches it again when the lab's copy is missing or out of date. This needs
    an internet connection.
