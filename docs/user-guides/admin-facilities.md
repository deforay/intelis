---
description: Add facilities and testing labs, link them to test types, configure lab reports and signatories, and repair broken locations.
audience: [system-admin, lab-admin]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# How to manage facilities and testing labs

Add and maintain the health facilities and testing labs under **ADMIN →
Facilities**. Every sample is attached to one of each.

Other tasks on the same page have their own guides:

- [Add or update many facilities at once](admin-facilities-bulk-upload.md)
- [Connect the Interface Tool to a testing lab](admin-interface-tool-connections.md)

## Before starting

- An account with administrator rights on the STS, or on a standalone
  installation
- The facility's province and district. A missing one can be added with
  **Other** on the form, or first under **ADMIN → System Configuration →
  Geographical Divisions**

??? info "On a LIS, the Facilities page is read-only"

    A LIS lists facilities with no **Add Facility**, **Edit**, **Bulk
    Upload**, **Health Facilities** or **Testing Lab** button. Make the change on the STS. To bring it to the LIS at
    once, select **Force Remote Sync** at the bottom right of any LIS page.

## Add a facility

1. Go to **ADMIN → Facilities**.
2. Select **Add Facility**.
3. Enter **Facility Name**. It must not already be in use.
4. Optionally enter **Facility Code**, the national unique code. It accepts
   letters, numbers and hyphens and is saved in capitals. Left blank on a
   testing lab, InteLIS generates one. A code already used by another facility
   is refused.
5. Set **Facility Type**: **Health Facility**, **Testing Lab** or **Collection
   Site**.
6. Set **Province/State** and **District/County**.
7. Under **Test Type**, select at least one test type the facility takes part
   in. Select every test type that applies.

    ??? warning "A facility missing from a request form"

        The request form of a test type offers only the facilities ticked for
        that test type. A facility ticked for viral load only is missing from
        the EID form.

8. Fill in the optional fields the facility needs:

    | Field | What to enter |
    | --- | --- |
    | Other/External Code | A second code, where another system uses its own |
    | Testing Point(s) | The service points, such as VCT or PMTCT |
    | Address, Latitude, Longitude | Where the facility is. Latitude and longitude place it on the Sample Referral Network map |
    | Country | The facility's country |
    | Email(s) | Addresses for emailed results, separated by commas |
    | Lab Manager | The contact person, chosen from the InteLIS users |
    | Phone Number | The contact number. It must not already be used by another facility |
    | Linked Hub Name (If applicable) | The hub the facility routes samples through |

9. If the facility is a testing lab, set **Allow Results File Upload?**, and
   **Available Platforms** for TB.
10. Select **Submit**.
11. For a testing lab, set the logo, header and report settings under
    [Set up a testing lab](#set-up-a-testing-lab).

## Link many facilities to a test type

Use this when several facilities are missing from one test type's request
form, such as after a bulk upload.

1. Go to **ADMIN → Facilities**.
2. Select **Health Facilities**, or **Testing Lab** for testing labs.
3. Set **Test Type**.
4. Move the facilities that take part into the selected list.

    ??? warning "Facilities left in the unselected list are removed from that test type"

        **Submit** replaces every link for the test type with the selected
        list.

5. Select **Submit**.
6. Open that test type's request form. The facilities are offered.

## Set up a testing lab

A testing lab is a facility whose **Facility Type** is **Testing Lab**. It
carries settings that a health facility does not.

1. Go to **ADMIN → Facilities**.
2. Select **Edit** on the lab.
3. Check that **Facility Type** is **Testing Lab**, and that **Test Type**
   holds every test the lab runs.
4. Check that **Allow Results File Upload?** is **yes** if the lab imports
   result files. It is preset to **yes**.
5. For TB, set **Available Platforms** to the methods the lab runs:
   **Microscopy**, **Xpert** or **LAM**.

    ??? info "Available Platforms does not appear"

        The field appears only while TB is the only ticked test type.

6. Upload the **Logo Image** printed on this lab's result PDFs. It must be 80 by
   80 pixels.
7. Set the result PDF layout for each test type under **Report Format For VL**,
   **Report Format For EID** and the matching fields for other modules. Each
   field appears only when more than one format is available.
8. Set the report header and footer. The four middle rows appear only on some
   country forms.

    | Setting | Controls |
    | --- | --- |
    | Header Text | The heading printed on the report |
    | Display Page Number in Footer | Whether pages are numbered |
    | Display Signature Table | Whether the signature block prints at all |
    | Report Top Margin | The space above the report |
    | Bottom Text Location | **Above Footer** or **Below Platform Name** |
    | Upload Report Template | One PDF template for the lab's reports |
    | Test Type Templates | A PDF per test type with its **Header Margin**. On **Edit**, shown only once a template exists |

9. Select **Submit**.

## Add signatories to result PDFs

Signatories are the names, designations and signatures printed on a lab's
result PDFs.

1. Go to **ADMIN → Facilities**.
2. Select **Edit** on the testing lab.
3. In the signatory table, enter the **Name of Signatory** and the
   **Designation**.
4. Upload the signature under **Upload Signature (jpg, png)**.
5. Under **Test Types**, select every module whose result PDF carries this
   signatory.

    ??? warning "A signatory with no test type never prints"

        InteLIS saves the row, but prints it on no result PDF. The signature
        block then looks switched off.

6. Set the **Display Order** and the **Current Status**.
7. Repeat steps 3 to 6 on a new row for each further signatory.
8. Select **Submit**.
9. Print one result PDF per module and read the signature block.

## Find facilities with a broken location

1. Go to **ADMIN → Facilities**.
2. Open **Advanced Search**.
3. Tick **Show Orphaned Facilities**.
4. Select **Search**. The list shows facilities whose province or district is
   missing, inactive or not linked to its province.
5. Select **Edit** on each one, set a valid **Province/State** and
   **District/County**, and select **Submit**.

These facilities drop out of the location filters on reports until they are
fixed.

## Deactivate a facility

1. Go to **ADMIN → Facilities**.
2. Select **Edit** on the facility.
3. Set **Status** to **Inactive**.
4. Select **Submit**.

## Confirm it worked

| Change | Check |
| --- | --- |
| New facility | It appears on the request form of each ticked test type |
| Testing lab | It appears in the **Testing Lab** list on the request form |
| Signatories | A printed result PDF carries the expected names |
| Orphaned facility fixed | It no longer appears under **Show Orphaned Facilities** |
