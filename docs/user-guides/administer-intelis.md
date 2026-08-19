# How to administer InteLIS

This guide is the entry point for everything under **ADMIN**. It covers what an
administrator owns, which changes need agreement before they are made, and where
each task is documented.

It does not cover installing, updating, or backing up InteLIS. Those are server
tasks and live under the installation and maintenance guides.

## Before starting

- An account with administrator rights

## The ADMIN menu

| Section | Holds | Guide |
|---|---|---|
| Access Control | Roles and Users | [Users and roles](admin-users-and-roles.md) |
| Facilities | Health facilities, testing labs, report templates, signatories, Interface Tool connections | [Facilities and testing labs](admin-facilities.md) |
| System Configuration → Instruments | Analyzers and their result file formats | [Instruments and interfacing](admin-instruments.md) |
| VL Config, EID Config, TB Config, and the other module sections | The dropdown lists on each module's request form | [Module configuration](admin-module-configuration.md) |
| System Configuration → General Configuration | The settings that apply across the installation | [General configuration](admin-general-configuration.md) |
| System Configuration → Geographical Divisions, Implementation Partners, Funding Sources, Lab Storage | The lists shared by every module | [Module configuration](admin-module-configuration.md) |
| Monitoring | Activity log, audit trail, sync history, analyzer activity, lab performance | [Monitoring and audit](admin-monitoring.md) |

The ADMIN menu shows a config section only for the modules the installation
runs. An installation running one module carries one section.

A second administration area sits outside this menu, at `/system-admin`, with its
own login. See [System administration](admin-system-administration.md).

## Two levels of administrator

Not every administrator needs every page. InteLIS does not enforce the split.
Build it into the roles.

| Level | Owns |
|---|---|
| Lab administrator | Users, facilities, instruments, module config lists, Interface Tool connections, audit trail lookups |
| National administrator | All of the above, plus roles and privileges, General Configuration, and Geographical Divisions |

Most trouble in the field comes from national-level settings being changed by
lab-level staff.

## Changes that need agreement before making them

The changes below take effect across the whole installation the moment they are
saved. Setting them back does not undo their effect on the records already
created.

| Change | Where | Why it needs agreement |
|---|---|---|
| Sample ID format or prefix | General Configuration, per module | Every sample registered from that moment carries the new format. The samples already registered keep the old one, leaving the lab with two schemes |
| Sample Lock Days and Sample Expiry Days | General Configuration → Global Settings | Decides when a record stops accepting edits. Too short and the lab cannot correct a result. Too long and results stay editable after release |
| Same user can Review and Approve | General Configuration → Global Settings | Lets one person enter and sign off their own result. Approval is the only check on result quality |
| Auto Approve API Results | General Configuration, per module | Releases analyzer results with no human check. Not safe where Sample IDs are typed on the analyzer by hand |
| Country of Installation | General Configuration → Global Settings | Selects the request form layout. Changing it changes the form every user sees |
| Training Mode | General Configuration → Global Settings | Marks the installation as practice. Never turn it on for a live installation |
| Role privileges | Access Control → Roles | Applies at once to every user holding the role |
| Deleting a list entry | Any module config page | Set the entry inactive instead. Deleting it leaves the records that used it unreadable |
| Renaming or removing a province or district | System Configuration → Geographical Divisions | The facilities under it lose their link, and the geographic filters on every report stop matching |

## Rules that hold everywhere

**Never share a login.** The activity log, and the tester, reviewer, and approver
names on every report, record whoever was signed in. A shared login makes those
records worthless.

**Retire, never delete.** Set list entries and departing users inactive. An
inactive entry disappears from the form and stays readable on the records that
already use it.

**Never reuse a login for a different person.** The old records stay attached to
the old name.

**Change one setting at a time**, then check the effect before changing another.

## Confirm a change worked

| Change | Check |
|---|---|
| New user | The user signs in and sees the expected menu |
| Role change | Sign in as a user with that role, or use the Permission filter on the Roles page |
| New facility | The facility appears on the request form of each test type it was ticked for |
| New instrument | The instrument appears in Testing Platform when creating a batch |
| Interface Tool connection | The installation shows under Connected Installations with a recent Last Seen |
| List entry | The entry appears in its dropdown on the request form |
| General configuration | Open the page the setting affects and read the result |
