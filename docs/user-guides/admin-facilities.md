# How to manage facilities and testing labs

Add and maintain the health facilities and testing labs under **ADMIN →
Facilities**. Every sample is attached to one of each.

Other tasks on the same page have their own guides:

- [Add or update many facilities at once](admin-facilities-bulk-upload.md)
- [Connect the Interface Tool to a testing lab](admin-interface-tool-connections.md)

## Before starting

- An account with administrator rights on the STS, or on a standalone
  installation
- The facility's province and district, created under **ADMIN → System
  Configuration → Geographical Divisions**

??? info "On a LIS, the Facilities page is read-only"

    A LIS lists facilities with no **Add Facility**, **Edit** or **Bulk
    Upload** button. Make the change on the STS. To bring it to the LIS at
    once, select **Force Remote Sync** at the bottom right of any LIS page.

## Add a facility

1. Go to **ADMIN → Facilities**.
2. Select **Add Facility**.
3. Enter **Facility Name**. It must not already be in use.
4. Enter **Facility Code**, the national unique code. It accepts letters,
   numbers and hyphens.
5. Set **Facility Type**: **Health Facility**, **Testing Lab** or **Collection
   Site**.
6. Set **Province/State** and **District/County**.
7. Under **Test Type**, tick every test type the facility takes part in.

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
    | Email(s) | Addresses for emailed results, separated by commas |
    | Lab Manager, Phone Number | The contact person |
    | Linked Hub Name (If applicable) | The hub the facility routes samples through |

9. If the facility is a testing lab, fill in the lab settings. See
   [Set up a testing lab](#set-up-a-testing-lab), steps 5 to 9.
10. Select **Submit**.

## Link many facilities to a test type

Use this when several facilities are missing from one test type's request
form, such as after a bulk upload.

1. Go to **ADMIN → Facilities**.
2. Select **Health Facilities**, or **Testing Lab** for testing labs.
3. Set **Test Type**.
4. Move the facilities that take part into the selected list.
5. Select **Submit**.
6. Open that test type's request form. The facilities are offered.

## Set up a testing lab

A testing lab is a facility whose **Facility Type** is **Testing Lab**. It
carries settings that a health facility does not.

1. Go to **ADMIN → Facilities**.
2. Select **Edit** on the lab.
3. Check that **Facility Type** is **Testing Lab**, and that **Test Type**
   holds every test the lab runs.
4. In the targets table, enter the **Monthly Target** for each test type. For
   viral load, also enter the **Suppressed Monthly Target**. The dashboard
   compares the lab's work with these targets when **VL Monthly Target** is
   enabled in [General configuration](admin-general-configuration.md).

    ??? info "No targets table"

        The table exists on **Edit Facility** only. Save the new lab first,
        then edit it.

5. Set **Allow Results File Upload** to **Yes** if the lab imports result
   files.
6. For TB, set **Available Platforms** to the methods the lab runs:
   **Microscopy**, **Xpert** or **Lam**.

    ??? info "Available Platforms does not appear"

        The field appears only while TB is the only ticked test type.

7. Upload the **Logo Image** printed on this lab's result PDFs. It must be 80 by
   80 pixels.
8. Set the result PDF layout for each test type under **Report Format For VL**,
   **Report Format For EID** and the matching fields for other modules.
9. Set the report header and footer:

    | Setting | Controls |
    | --- | --- |
    | Header Text | The heading printed on the report |
    | Display Page Number in Footer | Whether pages are numbered |
    | Display Signature Table | Whether the signature block prints at all |
    | Report Top Margin | The space above the report |
    | Bottom Text Location | **Above Footer** or **Below Platform Name** |
    | Upload Report Template | A PDF template per test type, with its **Header Margin**, where the default layout does not fit |

10. Select **Submit**.

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

## Confirm it worked

| Change | Check |
| --- | --- |
| New facility | It appears on the request form of each ticked test type |
| Testing lab | It appears in the **Testing Lab** list on the request form |
| Targets | The dashboard target charts show the lab's figures |
| Signatories | A printed result PDF carries the expected names |
| Orphaned facility fixed | It no longer appears under **Show Orphaned Facilities** |
