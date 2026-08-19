# How to manage facilities and testing labs

This guide maintains the health facilities and testing labs under **ADMIN →
Facilities**. Every sample is attached to one of each.

The page also holds the report templates, the signatories that appear on result
PDFs, and the Interface Tool connections for each lab.

## Before starting

- An account with administrator rights
- The province and district the facility sits in, created already under
  **ADMIN → System Configuration → Geographical Divisions**

## Add a facility

1. Go to **ADMIN → Facilities**.
2. Select **Add Facility**.
3. Fill in the details.

| Field | What to enter |
|---|---|
| Facility Name | The name staff will search for. It must not already be in use |
| Facility Code | The national unique code |
| Other/External Code | A second code, where another system uses its own |
| Facility Type | Health facility, or testing lab |
| Test Type | Every test type this facility takes part in |
| Testing Point(s) | The service points, such as VCT or PMTCT |
| Province/State, District/County | The location |
| Address, Latitude, Longitude | Where the facility is. Latitude and longitude place it on the referral network map |
| Email(s) | Addresses for emailed results, separated by commas |
| Lab Manager, Phone Number | The contact person |
| Linked Hub Name | The hub this facility routes samples through, if any |
| Status | Active or inactive |

4. Select **Submit**.

Set the **Test Type**, and tick every test type the facility takes part in. A
facility not linked to a test type does not appear in the facility list on that
test type's request form. A facility ticked for one test type stays missing from
every other test type's form. This is the usual reason a facility is "missing".

## Set up a testing lab

A testing lab is a facility with **Facility Type** set to testing lab. It carries
extra settings that a health facility does not.

| Setting | Controls |
|---|---|
| Available Platforms | The analyzers this lab runs, such as Xpert, Microscopy, or Lam |
| Monthly Target | The lab's monthly testing target, used by the reports |
| Suppressed Monthly Target | The viral load suppression target |
| Allow Results File Upload | Whether this lab may import result files |
| Logo Image | The logo on result PDFs from this lab. 80 by 80 pixels |
| Report Format For VL, EID, TB, Covid-19, Hepatitis | The result PDF layout per test type |
| Upload Report Template | A PDF template, where the default layout does not fit |

## Add signatories to result PDFs

Signatories are the names, designations, and signatures printed on the result
PDFs a lab issues.

1. Open the testing lab under **ADMIN → Facilities**.
2. Find the signatory section.
3. For each signatory, enter the **Name of Signatory** and **Designation**, set
   the **Display Order**, and upload the signature image as jpg or png.
4. Select **Submit**.

| Setting | Controls |
|---|---|
| Display Signature Table | Whether the signature block prints at all |
| Header Text, Header Margin, Report Top Margin | The report heading and its spacing |
| Bottom Text Location | Above the footer, or below the platform name |
| Display Page Number in Footer | Whether pages are numbered |

## Load many facilities at once

1. Go to **ADMIN → Facilities**.
2. Select **Bulk Upload**.
3. Download the Excel format from the link on the page.
4. Fill it in and upload it.
5. Choose an upload option.

| Upload option | Effect |
|---|---|
| Don't update duplicates | Adds new facilities. Leaves existing ones untouched. This is the default |
| Update if Facility Code matches | Overwrites the facility holding that code |
| Update if Facility Name matches | Overwrites the facility holding that name |
| Update if Facility Name and Facility Code match | Overwrites only where both match |

The page reports the total records in the file, the number added, and the number
not added. Read all three. A file that adds fewer facilities than it holds has
rows that failed.

Always use the downloaded format. A file with different columns fails to import.

## Find facilities that behave oddly

Set **Show Orphaned Facilities** on the Facilities page. It lists facilities
whose province or district is missing, inactive, or not linked to its province.

Those facilities behave unpredictably in the geographic filters on every report
until the province and district are fixed.

**Show Only Active** hides retired facilities. **Export** writes the current
filtered list to Excel.

## Connect the Interface Tool

The Interface Tool passes results from an analyzer into InteLIS without anyone
typing them. Each installation of the tool connects to InteLIS once.

1. Go to **ADMIN → Facilities**.
2. Open the testing lab.
3. Scroll to **Interface Tool Connections**.
4. Select **Generate Connection Code**.
5. Enter the three groups of the code, and the InteLIS URL shown above them, into
   the Interface Tool on the lab computer.

The code expires. The page shows the time remaining. If it expires, generate
another.

Only one code can be outstanding at a time. To start again, cancel the current
code first.

Once connected, the installation appears under **Connected Installations** with a
status and a **Last Seen** time. Use **Last Seen** when results stop arriving.

| Action | When to use it |
|---|---|
| Reconnect / Reinstall | The lab computer is rebuilt or the tool is reinstalled |
| Revoke | The computer is retired or lost. Other installations are unaffected |

## Confirm it worked

| Change | Check |
|---|---|
| New facility | The facility appears on the request form of each test type it was ticked for |
| Testing lab | The lab appears in the Testing Lab list on the request form |
| Signatories | Print a result PDF from that lab and read the signature block |
| Bulk upload | The number added matches the total records in the file |
| Orphaned facility fixed | It no longer appears under Show Orphaned Facilities |
| Interface Tool connection | The installation shows under Connected Installations with a recent Last Seen |
