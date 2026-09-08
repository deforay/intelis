# How to record where a sample is stored

Recording the freezer position of a sample lets anyone find the physical tube
later, without opening every box. This matters most for samples held for
retesting, for confirmatory work, or for a quality review.

## Before starting

- Samples registered in InteLIS
- The freezer, rack, box, and position where the tube goes
- Permission to view test requests

The freezer names come from a list the administrator maintains. If a freezer is
missing from the list, ask the administrator to add it under **ADMIN → System
Configuration → Lab Storage**.

## Open the storage page

1. Go to **HIV VIRAL LOAD → Request Management → View Test Requests**.
2. Select **Samples Storage** at the top right of the list.

## Record a position

1. Choose the **Lab** and the **Freezer**.
2. Filter to the samples being stored, using **Sample Collection Date**, **Sample
   Received at Lab Date**, or **Facility Name**.
3. Select **Get Samples**.
4. For each sample, fill in the storage details.

| Field | What to enter |
|---|---|
| Rack | The rack inside the freezer. Required |
| Box | The box inside the rack. Required |
| Position | The position inside the box. Required |
| Volume(ml) | The volume stored. Required, and greater than zero |
| Date out | The date the sample left storage, filled in on removal |
| Comments | Anything needed to find or interpret the sample |

5. Select **Save**.

!!! warning "Every required field must be filled in, on every row"
    A row is saved only when the freezer, rack, box, position and a volume
    greater than zero are all present. A row missing any of them is skipped
    silently: the page still reports success, and the tube ends up with no
    recorded position. After saving, confirm each sample now shows its position.

Record the position at the moment the tube goes into the freezer. A position
written down later, from memory, is the position the tube was meant to go in,
not necessarily where it is.

## Record many samples at once

Where a whole box goes in at one time, use **Storage Bulk Upload**. The button
appears only on the DRC request form, so labs on other country forms record
positions one row at a time using the steps above.

1. Select **Storage Bulk Upload**.
2. Select **Download Excel Format** and fill in the downloaded sheet. Use its
   columns as they are, including the volume column, which is required in the
   same way as on the form.
3. Select **Upload File**, choose the completed sheet, and select **Submit**.
4. Confirm the samples now show their positions before returning the box to the
   freezer.

## Record a sample leaving storage

1. Find the sample's row in **Freezer/Storage**.
2. Select **Remove** on that row.
3. Choose the reason for removal.
4. Confirm.

The sample's status becomes Removed and it leaves current storage, while its
history is kept, so a later search still shows where it was and when it left.

Setting **Date out** and saving does not remove a sample. That writes another
storage history row and leaves the sample in its position, so the freezer map
still shows the tube as present.

## Find a stored sample

1. Go to **HIV VIRAL LOAD → Management → Freezer/Storage Reports**.
2. Filter by freezer, by date, or by facility.

The report gives the current position of each sample and its storage history.
Export it to a spreadsheet for a stock check against the physical freezer.

## Confirm it worked

Search for the sample on the Samples Storage page. **Current Storage** shows the
freezer, rack, box, and position just recorded.
