# General configuration settings

Reference for **ADMIN → System Configuration → General Configuration**. Every
setting applies to every user of the installation at once.

The settings listed under
[Changes that need agreement](administer-intelis.md#changes-that-need-agreement)
need the national team's agreement first.

## Change a setting

1. Go to **ADMIN → System Configuration → General Configuration**. The page,
   titled **System Configuration**, opens read-only.
2. Select **Edit System Configuration**.
3. Find the setting with **Search settings by keyword**, or pick its panel
   under **Jump to a section**.
4. Change the setting.
5. Select **Save**.
6. Check the effect with the matching row under
   [Confirm a change](#confirm-a-change).

The panels below follow the order of the page. A module's panel appears only
when the installation runs that module.

## Instance Settings

| Setting | Controls |
| --- | --- |
| Date Format | How dates display across InteLIS: `d-M-Y` or `d-m-Y` |
| Display Encrypt PII Option | Whether the option to encrypt personally identifying information is offered |

## Global Settings

| Setting | Controls |
| --- | --- |
| Country of Installation | The request form layout. Each country has its own form |
| Default Time Zone | The time zone stamped on every record |
| System Locale | The interface language |
| Header | The heading printed on reports |
| Logo Image | The logo printed on reports |
| Allow users to Edit Profile | Whether users may change their own details |
| Track Page Usage | Whether InteLIS records the pages each user opens. **Yes** by default. Feeds the Page Usage page |
| Training Mode | Marks the installation as practice, and shows the text set beside it |
| Barcode Format | `C39`, `C39+`, `C128` or `QRCODE` |
| Same user can Review and Approve | Whether one person may both review and approve a result |
| Sample ID Barcode Label Printing | `off`, `zebra-printer` or `dymo-labelwriter-450` |
| Allow Samples not matching the System Sample IDs while importing results manually | Whether a manual import accepts rows whose Sample ID InteLIS does not hold |
| Support Email | The address shown to users asking for help |
| Minimum Mobile App Version | The oldest mobile app version allowed to sign in, such as `1.5.0`. Blank allows every version |
| CSV Delimiter, CSV Enclosure | How exported CSV files are separated and quoted |
| Default Phone Prefix | The country dialling prefix |
| Minimum Length of Phone Number, Maximum Length of Phone Number | The accepted phone number lengths |
| Batch PDF Layout | **Standard** or **Compact** |
| Sample Expiry Days | Days before a sample expires |
| Sample Lock Days | Days before a sample stops accepting edits |
| Test Type Templates | A PDF template per test type, with its **Header Margin**, for result reports |

??? warning "Country of Installation and Training Mode"

    Changing **Country of Installation** changes the form every user sees. The
    new form may not carry the fields the old one did.

    **Training Mode** belongs on a practice installation only. Never turn it on
    for an installation holding real patient records.

## Module panels

Each module has its own panel: **Viral Load Settings**, **EID Settings**,
**Covid-19 Settings**, **Hepatitis Settings**, **TB Settings**, **CD4
Settings** and **Other Lab Tests Settings**. A change in one panel does not
reach the others.

| Setting | Controls | Panels |
| --- | --- | --- |
| Sample ID | How this module's Sample IDs are built. See [Sample ID formats](#sample-id-formats) | All |
| Minimum Patient ID Length | The shortest patient identifier the request form accepts | All |
| Copy Request On Save and Next Form | Whether **Save and Next** carries the previous request's values forward | All, on the Cameroon form only |
| VL, EID, COVID-19 or TB Auto Approve API Results | Whether results arriving through the API are approved with no human check | Viral Load, EID, Covid-19, TB |
| Show Participant Name in VL, EID, COVID-19, Hepatitis or TB Manifest, Show Participant Name in Custom Lab Tests Manifest | Whether the participant name prints on this module's manifest | All except CD4 |
| Covid-19 Positive Confirmatory Tests Required | Whether a positive COVID-19 result needs a confirmatory test | Covid-19 |
| Sample Expiry Days | An expiry for Custom Tests, where it differs from the global one | Other Lab Tests |

**Viral Load Settings** carries these as well:

| Setting | Controls |
| --- | --- |
| Viral Load Threshold Limit | The value from which a result counts as high |
| VL Suppression Target | The suppression target used by the reports |
| VL Monthly Target | **Enable** or **Disable**. Enabled, the dashboard shows each lab's work against its targets. The targets themselves are set per testing lab, under [Set up a testing lab](admin-facilities.md#set-up-a-testing-lab) |
| Interpret and Convert VL Results | Whether InteLIS converts and interprets imported viral load values |
| Viral Load Export Format | **Default Format** or **CRESAR Format**. On the Cameroon form only |

??? warning "Auto Approve API Results"

    Results arriving through the API are released with no human check. This is
    safe where the analyzer is trusted and the batch workflow is followed. It is
    not safe where Sample IDs are typed on the analyzer by hand.

## Sample ID formats

Each module has its own format and prefix. The running number has at least four
digits and restarts each year.

| Format | Builds | Example with prefix `VL` |
| --- | --- | --- |
| YY | Prefix, 2-digit year, number | `VL260001` |
| MMYY | Prefix, month, 2-digit year, number | `VL08260001` |
| Auto | Province code, date as YYMMDD, number | `122608190001` |
| Auto 2 | 2-digit year, province code, prefix, number. On the PNG form only | `2612VL0001` |
| Numeric, Alpha Numeric | Prefix, number. No date | `VL0001` |

Samples registered on the STS carry a leading `R`. Where a lab code is
appended, a hyphen separates it from the running number, as in
`VL0826-NMC-0019`.

A new format or prefix applies to samples registered from that moment. Existing
samples keep the old one, so the lab holds two schemes at once.

## Mobile App Settings

| Setting | Controls |
| --- | --- |
| Mobile APP Menu Name | The name the mobile app shows for this installation |

## Connect

| Setting | Controls |
| --- | --- |
| National Dashboard URL | The dashboard this installation links to |

## Viral Load Result PDF Settings

| Setting | Controls |
| --- | --- |
| Show Emoticon/Smiley | Whether the result PDF carries a smiley for a suppressed result |
| Display VL Log Result | Whether the log value prints beside the copies per millilitre |
| High Viral Load Message | The message printed on a result at or above the threshold |
| Low Viral Load Message | The message printed on a result below the threshold |
| Patient Name Format | **First Name + Last Name**, **Full Name**, or **Hide Patient Name** |

Set **Patient Name Format** to **Hide Patient Name** where result PDFs travel by
a route that must not carry patient names.

## Settings not on this page

| Setting | Default | Controls |
| --- | --- | --- |
| Auto Approve Interface Results | `yes` | Whether results arriving through the Interface Tool are approved with no human check. It is separate from the per-module API settings |
| Interface API Enabled | `no` | Whether the **Interface Tool Connections** panel appears on testing labs |

InteLIS support changes these two settings on request.

!!! warning "Interface results are approved automatically by default"

    With the default `yes`, Interface Tool results are accepted without review,
    even where every Auto Approve API Results setting is off. A lab that needs
    human review of interface results asks support to set Auto Approve
    Interface Results to `no`. Then confirm that the next analyzer result lands
    in the approval queue, not as Accepted.

## Confirm a change

| Change | Check |
| --- | --- |
| Date Format, Header, Logo Image | Open a report and read it |
| Sample ID | Register one request and read the Sample ID issued |
| Barcode Format | Print a batch PDF and scan a barcode |
| Same user can Review and Approve | Review one result, then try to approve that same result |
| Auto Approve API Results | Send one result through the API and read its status |
| VL Monthly Target | Open the dashboard and find the target charts |
| Result PDF settings | Print one result PDF |
| Sample Lock Days | Open a sample older than the limit and try to edit it |
