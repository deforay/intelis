# How to send samples to a testing lab on a manifest

List the samples in a package on a manifest, so the testing lab registers the
whole package by entering one code.

The manifest menu exists only on the central system (STS). A lab running its
own InteLIS (LIS) never sees it. Use this guide on the STS, at a health facility
sending samples or at a lab referring samples on.

Before starting:

- Register a test request for every sample in the package. See
  [How to register a viral load test request](register-a-request.md). The
  manifest is built from requests that already exist.
- Set the testing lab on each request to the lab the package goes to.

The steps use viral load. For another test, open the manifest menu under that
test's group.

| Menu group | Manifest menu |
| --- | --- |
| **HIV VIRAL LOAD** | **VL Manifest** |
| **EARLY INFANT DIAGNOSIS (EID)** | **EID Manifest** |
| **TUBERCULOSIS** | **TB Manifest** |
| **COVID-19** | **Covid-19 Manifest** |
| **HEPATITIS** | **Hepatitis Manifest** |
| **CD4** | **CD4 Manifest** |
| **OTHER LAB TESTS** | **Lab Test Manifest** |

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "New package"

    ### Build the manifest

    1. Go to **HIV VIRAL LOAD → Request Management → VL Manifest**.
    2. Select **Add Specimen Referral Manifest**. The **Manifest Code** is filled
       in and cannot be changed.
    3. Choose the **Testing Lab** the package goes to.
    4. Choose the **Operator/Technician** packing the samples.
    5. To narrow the list, set any of **Sample Collection Point**, **Sample Type**
       and **Sample Collection Date Range**.
    6. Select **Search**. The samples waiting to be sent appear in the left-hand
       list.

        ??? question "If a sample is missing from the list"

            The list shows only requests that:

            - were registered on the STS
            - name the chosen testing lab
            - are not on another manifest
            - are not cancelled

            Open the request and check its testing lab. Then select
            **Search** again.

    7. Move each sample that is in the package to the right-hand list. Use the
       arrow buttons between the two lists. The search box above each list
       finds a Sample ID.
    8. Check that **Number of selected samples** matches the tubes in the
       package.

        Select only samples that are physically in the package. The receiving
        lab records every listed sample as received.

    9. Select **Save**. The manifest list opens.

    ### Print the manifest

    10. Find the manifest in the list. Check its **Testing Lab** and **Number
        of Samples**.
    11. Select **Print Manifest PDF**.
    12. Put the printed manifest in the package. Keep a copy at the sending
        site.

    The receiving lab enters the manifest code from this sheet. See
    [How to receive samples sent on a manifest](receive-referred-samples.md).

=== "Change a manifest"

    1. Go to **HIV VIRAL LOAD → Request Management → VL Manifest**.
    2. Select **Edit** on the manifest row. The samples already on the manifest
       appear in the right-hand list.

        ??? failure "If Edit is greyed out"

            The testing lab has already received the package, so the manifest
            can no longer change. If it is wrong, tell the testing lab. The lab
            can reject the affected samples.

    3. To add samples, move them from the left-hand list to the right-hand list.
       To narrow the left-hand list, change the filters and select **Search**.
    4. To remove samples, move them back to the left-hand list.
    5. Check that **Number of selected samples** matches the tubes in the
       package.
    6. Select **Save**.
    7. Select **Print Manifest PDF** on the manifest row.
    8. Replace the old printed manifest in the package with the new one.

    ??? info "About the Manifest Status field"

        **Manifest Status** changes by itself and is locked on this screen:

        | Status | Set when |
        | --- | --- |
        | **Pending** | The manifest is created. |
        | **Dispatch** | The manifest is printed for the first time. |
        | **Received** | The testing lab takes in the package. From then on, the manifest cannot be edited. |

=== "Move manifests to another lab"

    Use this when a testing lab cannot take the packages already sent to it.
    **Move Manifest** has its own permission. If the button is missing, ask the
    administrator.

    1. Go to **HIV VIRAL LOAD → Request Management → VL Manifest**.
    2. Select **Move Manifest**.
    3. Choose the lab the manifests were sent to in **Manifest From Testing
       Lab**.
    4. To narrow the list, set a **Date Range**. It filters on the date each
       manifest was created.
    5. Select **Search Manifests**.
    6. Move each manifest to be reassigned to the right-hand list.
    7. Choose the new lab in **Assign to Testing Lab**.
    8. Enter the **Reason for Moving Manifest(s)**. It is the only record of why
       the samples went to a different lab.
    9. Select **Save Changes**. The manifest list opens.
    10. Send the physical packages to the new lab. The move changes the records
        only.

    ??? info "If the first lab already activated the manifest"

        The move clears the Sample IDs the first lab issued. The new lab
        activates the manifest and issues its own Sample IDs. The identifier
        the sending facility used stays the same.

## Confirm it worked

The manifest appears in the list with the right **Testing Lab** and **Number of
Samples**.

After the receiving lab activates it, each sample carries a lab Sample ID next
to the identifier the facility used.
