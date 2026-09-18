# How to record where a sample is stored

Sample storage is recorded on the lab's own InteLIS (LIS), for labs that use the
DRC request form. Other request forms do not show the **Samples Storage** button.

A recorded freezer position lets anyone find the physical tube later, without
opening every box. This matters most for samples kept for retesting, for
confirmatory work, or for a quality review.

## Before starting

- Samples registered in InteLIS
- The freezer, rack, box and position where each tube goes
- Permission to view test requests
- The freezer set up under **ADMIN → System Configuration → Lab Storage**. If it
  is missing, ask the administrator to add it.

## Record a position

**Choose how the tubes go in, then follow its steps from top to bottom.**

Record the position when the tube goes into the freezer. A position written
down later, from memory, is where the tube was meant to go, not always where it
is.

=== "One sample at a time"

    1. Go to **HIV VIRAL LOAD → Request Management → View Test Requests**.
    2. Select **Samples Storage** at the top right of the list.
    3. Choose the lab in **Nom du laboratoire**. This label shows in French in
       every language.
    4. Filter to the samples being stored, using **Sample Collection Date**,
       **Sample Received at Lab Date** or **Facility Name**.
    5. Select **Get Samples**.

        ??? failure "If a sample is missing from the list"

            Without a **Sample Collection Date**, the list shows only samples
            collected in the last 30 days. Set the date range and select **Get
            Samples** again. If a sample never stored before is still missing,
            contact support: some lab installs list only samples that already
            have a position.

    6. On each sample's row, fill in the storage details:

        | Field | What to enter |
        | --- | --- |
        | Volume(ml) | The volume stored. Required, and greater than zero |
        | Lab | The lab that holds the freezer. Required |
        | Freezer | The freezer. Required |
        | Rack | The rack inside the freezer. Required |
        | Box | The box inside the rack. Required |
        | Position | The position inside the box. Required |
        | Comments | Anything needed to find or interpret the sample. Optional |

        !!! warning "A row with a required field missing is not saved"

            InteLIS skips that row without saying so. The page still shows
            `Sample added to the freezer successfully`, and the tube has no
            recorded position. Step 9 catches this.

    7. Leave **Date out** empty. It does not record a sample leaving storage.
    8. Select **Save**. InteLIS shows `Sample added to the freezer successfully`.
    9. Check each sample:

        1. Go to **HIV VIRAL LOAD → Management → Freezer/Storage Reports**.
        2. Select the **Sample Storage History** tab.
        3. Choose the **Testing Lab**, enter the **Sample ID**, and select
           **Search**.
        4. The row shows the freezer, **Rack**, **Box** and **Position**
           entered in step 6, with the status `Added`.

        ??? info "The Current Storage column stays empty"

            On the Samples Storage page, **Current Storage** does not show the
            recorded position in the current version. Check positions in
            Freezer/Storage Reports instead.

=== "A whole box"

    !!! warning "These positions go onto the request form, not into the storage records"

        The upload fills the **Freezer**, **Rack**, **Box**, **Position** and
        **Volume (ml)** fields on each sample's request form. It does not add
        the samples to Freezer/Storage Reports.

    1. Go to **HIV VIRAL LOAD → Request Management → View Test Requests**.
    2. Select **Samples Storage** at the top right of the list.
    3. Select **Storage Bulk Upload**.
    4. Enter the batch code or manifest code of the box in **Batch Code (or)
       Manifest Code**.
    5. Select **Download Excel Format**. The sheet downloads with the Sample IDs
       and patient IDs of that batch or manifest already filled in.
    6. Fill in the sheet. Keep its columns as they are:

        | Column | What to enter |
        | --- | --- |
        | Sample Code | Filled in already. Required |
        | Patient ID | Filled in already |
        | Location/Freezer Code | The freezer code, exactly as set up under Lab Storage. Required |
        | Rack | Required |
        | Box | Required |
        | Position | Required |
        | Volume (ml) | Required |

        ??? failure "If the freezer code is mistyped"

            A code that does not match an existing freezer creates a new
            freezer with that code. Check the codes before uploading.

    7. Select **Upload File** and choose the completed sheet.
    8. Select **Submit**. InteLIS shows the **Total number of records in
       file**, the **Number of Lab Storage added** and the **Number of
       Storages not added**.

        ??? failure "If some rows were not added"

            A sample that already has a position on its request form is not
            changed. InteLIS lists the rows it could not add and offers them as
            a spreadsheet to download.

    9. Check a few samples before returning the box to the freezer. In **View
       Test Requests**, search for the sample and select **Edit**. The
       **Freezer**, **Rack**, **Box**, **Position** and **Volume (ml)** fields
       show the uploaded values.

## Record a sample leaving storage

!!! failure "Removal cannot be recorded in the current version"

    The **Remove** button on the Samples Storage page does not appear, so a
    sample cannot be marked as removed. Setting **Date out** and saving does
    not remove it either: it adds another storage record and leaves the tube
    shown as present. Report removals to the administrator until this is fixed.

??? info "Steps once the Remove button shows again"

    1. On the Samples Storage page, select **Get Samples** to list the sample.
    2. Select **Remove** on its row.
    3. Choose the reason from the list that appears. InteLIS removes the sample
       at once, with no confirmation, and shows
       `Sample is removed from this freezer`.

    Choosing **Other** records the reason as `other`. Choose a listed reason
    where one fits. The storage history keeps where the tube was and when it
    left.

## Find a stored sample

1. Go to **HIV VIRAL LOAD → Management → Freezer/Storage Reports**.
2. Choose the tab:

    | Tab | Filters | Shows |
    | --- | --- | --- |
    | Freezer/Storage Report | **Testing Lab**, **Freezer/Storage** | Every sample in one freezer, with rack, box, position and volume |
    | Sample Storage History | **Testing Lab**, **Sample ID** | Every storage record of one sample |

3. Set the filters and select **Search**.
4. To check the freezer by hand, select **Export to excel** and compare the
   sheet with the tubes.

Samples recorded through **Storage Bulk Upload** do not appear in this report.
