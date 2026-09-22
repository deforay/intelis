---
description: Add or update many facilities from one Excel file, reviewing every row and warning before anything is saved.
audience: [system-admin, lab-admin]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# How to add or update many facilities at once

Load a list of facilities from one Excel file, or correct many existing
facilities in one pass. Nothing is saved until the review is accepted.

## Before starting

- An account with administrator rights on the STS, or on a standalone
  installation. A LIS has no **Bulk Upload** button
- The facilities in an `.xlsx` file, at most 20,000 rows

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Add new facilities"

    ### Prepare the file

    1. Go to **ADMIN → Facilities**.
    2. Select **Bulk Upload**.
    3. Select **Download blank template**.
    4. Fill in one facility per row. Keep the columns in their order, and leave
       the heading row in place.

        | Column | What to enter |
        | --- | --- |
        | Facility Name | Required |
        | Facility Code | The national unique code. Left blank on a testing lab, InteLIS generates one. Saved in capitals. The review shows any change |
        | External Facility Code | A second code used by another system |
        | Province/State, District/County | Required. A name InteLIS does not hold is added as a new province or district |
        | Facility Type | Required. `1` Health Facility, `2` Testing Lab, `3` Collection Site. The type name, such as `Testing Lab`, is also accepted |
        | Address, Email, Phone Number | Optional |
        | Latitude, Longitude | Optional. Latitude between -90 and 90, longitude between -180 and 180 |
        | Status | `active` or `inactive`. Blank adds the facility as active |

    ### Upload and review

    5. Under **How should existing facilities be handled?**, keep **Add new
       only**. Rows that match an existing facility are skipped.
    6. Drop the file on the page, or select **browse** and pick it.
    7. Select **Review Upload**. Nothing is saved yet. The review expires after
       24 hours. After that, upload the file again.
    8. Read the **Result** of each row:

        | Result | Meaning |
        | --- | --- |
        | New | The row is added |
        | Skipped | A facility with this name or code already exists |
        | Error | The row cannot be saved. **Details** gives the reason |

    9. Select the **Warnings** tile above the table. Read each warning. Rows
       with a warning start unticked.

        ??? warning "Warnings on new rows"

            | Warning | Risk |
            | --- | --- |
            | Name is almost the same as existing facility | The facility is added twice |
            | Name is close to a facility in the same district | The facility is added twice |
            | Name looks like the facility in another row | The file lists the facility twice |
            | Coordinates are the same as existing facility | The facility is added twice |

    10. Tick each warning row that is a genuinely new facility.
    11. Select **Import ticked rows**. To discard the upload instead, select
        **Cancel**.
    12. Confirm the prompt about rows with warnings, when it appears.

    ### Finish

    13. Read the summary. **Not saved** must be 0.

        ??? failure "If rows were not saved"

            A row is not saved when its name, code or external code is already
            used by another facility. Select **Download rows not saved**.
            Correct the rows, then upload that file from step 5.

    14. Link the new facilities to their test types. The file carries no test
        types, so the new facilities are on no request form yet. See
        [Link many facilities to a test type](admin-facilities.md#link-many-facilities-to-a-test-type).

=== "Update existing facilities"

    ### Prepare the file

    1. Go to **ADMIN → Facilities**.
    2. Select **Bulk Upload**.
    3. Select **Export existing facilities**. The export has the upload layout.
    4. Edit the rows that need a change. Delete the rows that need none. A blank
       optional cell keeps the value already saved.

    ### Upload and review

    5. Under **How should existing facilities be handled?**, choose how a row
       finds its facility:

        | Option | Effect |
        | --- | --- |
        | Match by name and code | Updates only when the name and the code belong to the same facility. The safest update |
        | Match by code | Updates the facility with the same code. Use it to rename facilities |
        | Match by name | Updates the facility with the same name |

        Rows that match nothing are added as new facilities.

    6. Drop the file on the page, or select **browse** and pick it.
    7. Select **Review Upload**. Nothing is saved yet. The review expires after
       24 hours. After that, upload the file again.
    8. Read the **Result** of each row:

        | Result | Meaning |
        | --- | --- |
        | Update | The row changes the facility. **Details** lists each field that changes |
        | No change | The row matches the saved facility |
        | New | The row matched no facility and is added |
        | Skipped | The row cannot be matched with the option chosen |
        | Error | The row cannot be saved. **Details** gives the reason |

    9. Select the **Warnings** tile above the table. Read each warning. Rows
       with a warning start unticked.

        ??? warning "Warnings on updated rows"

            | Warning | Risk |
            | --- | --- |
            | Facility Name changes a lot | The row matched the wrong facility |
            | Facility moves to a different Province/State | The row matched the wrong facility |
            | Coordinates move the facility a long way | The row matched the wrong facility |
            | Facility Type changes | Forms and lists that depend on the type change |
            | Facility Code of a testing lab changes | The code is part of the sample codes the lab generates |
            | External Facility Code changes | Other systems matching on the old code no longer find the facility |
            | Facility will be made inactive | The facility leaves every active list |

    10. Tick each warning row whose change is intended.
    11. Select **Import ticked rows**. To discard the upload instead, select
        **Cancel**.
    12. Confirm the prompt about rows with warnings, when it appears.

    ### Finish

    13. Read the summary. **Not saved** must be 0.

        ??? failure "If rows were not saved"

            A row is not saved for one of two reasons:

            - Its facility was changed by someone else after the review.
            - Its name, code or external code is already used by another
              facility.

            Select **Download rows not saved**, correct the rows, then upload
            that file from step 5.

## Confirm it worked

| Check | Expected |
| --- | --- |
| Summary | **Not saved** is 0, and **Added** plus **Updated** matches the rows ticked |
| Facilities list | A sample of the changed facilities shows the new values |
| Request form | New facilities appear once linked to their test types |
