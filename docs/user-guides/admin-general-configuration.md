# How to change general configuration

This guide covers **ADMIN → System Configuration → General Configuration**, the
settings that change how InteLIS behaves across the whole installation.

Every setting on this page applies to every user at once. Change one setting at a
time and check its effect before changing another.

## Before starting

- An account with administrator rights
- Agreement from the national team for the settings listed under
  [Changes that need agreement](administer-intelis.md#changes-that-need-agreement-before-making-them)

The page is grouped into panels. Use the search box at the top to find a setting
rather than scrolling.

## Instance settings

| Setting | Controls |
|---|---|
| Date Format | How dates display across InteLIS. `DD-MMM-YYYY` or `DD-MM-YYYY` |
| Display Encrypt PII Option | Whether the option to encrypt personally identifying information is offered |

## Global settings

| Setting | Controls |
|---|---|
| Country of Installation | The request form layout. Each country has its own form |
| Default Time Zone | The time zone stamped on every record |
| System Locale | The interface language |
| Header | The heading printed on reports |
| Logo Image | The logo printed on reports |
| Allow users to Edit Profile | Whether users may change their own details |
| Training Mode | Marks the installation as practice, and shows the text set beside it |
| Barcode Format | `C39`, `C39+`, `C128`, or `QRCODE` |
| Sample ID Barcode Label Printing | `off`, `zebra-printer`, or `dymo-labelwriter-450` |
| Same user can Review and Approve | Whether one person may both review and approve a result |
| Allow Samples not matching the System Sample IDs while importing results manually | Whether a manual import may bring in rows whose Sample ID InteLIS does not hold |
| Support Email | The address shown to users asking for help |
| Minimum Mobile App Version | The oldest InteLIS Mobile version allowed to sign in, for example `1.5.0`. Blank allows every version |
| CSV Delimiter, CSV Enclosure | How exported CSV files are separated and quoted |
| Default Phone Prefix | The country dialling prefix |
| Minimum Length of Phone Number, Maximum Length of Phone Number | The accepted phone number lengths |
| Batch PDF Layout | `standard` or `compact` |
| Sample Lock Days | How many days before a sample stops accepting edits |
| Sample Expiry Days | How many days before a sample expires |

**Country of Installation** selects the request form. Changing it changes the form
every user sees, and the new form may not carry the fields the old one did.

**Training Mode** belongs on a practice installation only. Never turn it on for an
installation holding real patient records.

## Per-module settings

Each module the installation runs carries its own panel. The settings repeat per
module, so a change under Viral Load Settings does not reach TB Settings.

| Setting | Controls |
|---|---|
| Sample ID format and prefix | How this module's Sample IDs are built. See below |
| Minimum Patient ID Length | The shortest patient identifier the request form accepts |
| Copy Request On Save and Next Form | Whether Save and Next carries the previous request's values forward |
| Auto Approve API Results | Whether results arriving through the API are approved with no human check |
| Show Participant Name in Manifest | Whether the participant name prints on this module's manifest |
| Sample Expiry Days | An expiry setting for this module, where it differs from the global one |

Viral Load Settings carries five more.

| Setting | Controls |
|---|---|
| Viral Load Threshold Limit | The value above which a result counts as high |
| VL Suppression Target | The suppression target used by the reports |
| VL Monthly Target | The monthly testing target used by the reports |
| Interpret and Convert VL Results | Whether InteLIS converts and interprets imported viral load values |
| Viral Load Export Format | The column layout of the viral load export |

**Auto Approve API Results** releases analyzer results with no human check. It is
safe where the analyzer is trusted and the batch workflow is followed. It is not
safe where Sample IDs are entered on the analyzer by hand.

## Sample ID formats

Each module carries its own format and its own prefix. The running number is four
digits and restarts each year.

| Format | Produces | Example with prefix `VL` |
|---|---|---|
| YY | prefix, 2-digit year, number | `VL260001` |
| MMYY | prefix, month, 2-digit year, number | `VL08260001` |
| alphanumeric | prefix, number. No date | `VL0001` |
| auto | province code, date as YYMMDD, number | `122608190001` |
| auto2 | 2-digit year, province code, prefix, number | `2612VL0001` |

Samples minted on the national server carry a leading `R`. Where a lab code is
appended, a hyphen separates it from the running number, as in `VL0826-NMC-0019`.

Changing the format or the prefix changes every sample registered from that
moment. The samples already registered keep the old format. The lab then holds
two schemes at once, and neither is wrong.

## Mobile app settings

| Setting | Controls |
|---|---|
| Mobile APP Menu Name | The name the mobile app shows for this installation |

## Connect

| Setting | Controls |
|---|---|
| National Dashboard URL | The dashboard this installation links out to |

## Viral load result PDF settings

| Setting | Controls |
|---|---|
| Show Emoticon/Smiley | Whether the result PDF carries a smiley for a suppressed result |
| Display VL Log Result | Whether the log value prints beside the copies per millilitre |
| High Viral Load Message | The message printed on a high result |
| Low Viral Load Message | The message printed on a low result |
| Patient Name Format | `flname` for first and last name, `fullname` for the whole name, `hidename` to print no name |

Set **Patient Name Format** to `hidename` where result PDFs travel by a route
that must not carry patient names.

## Confirm it worked

| Change | Check |
|---|---|
| Date Format, Header, Logo | Open a report and read it |
| Sample ID format | Register one test request and read the Sample ID it issues |
| Barcode Format | Print a batch PDF and scan a barcode |
| Same user can Review and Approve | Review one result, then try to approve that same result |
| Auto Approve API Results | Send one result through the API and read its status |
| Result PDF settings | Print one result PDF |
| Sample Lock Days | Open a sample older than the limit and try to edit it |
