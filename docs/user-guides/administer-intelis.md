# Administer InteLIS

This page maps every task under **ADMIN** to its guide. It also lists the
changes that need agreement before anyone makes them.

Installing, updating and backing up InteLIS are server tasks. They are covered
by the installation and maintenance guides.

## Where each task is

| Menu | Holds | Guide |
| --- | --- | --- |
| Access Control → Users, Roles | Logins and what each login can reach | [Users and roles](admin-users-and-roles.md) |
| Facilities | Health facilities, testing labs, lab targets, signatories | [Facilities and testing labs](admin-facilities.md) |
| Facilities → Bulk Upload | Many facilities added or updated from one Excel file | [Add or update many facilities](admin-facilities-bulk-upload.md) |
| Facilities → a testing lab → Interface Tool Connections | Connection codes for the Interface Tool | [Interface Tool connections](admin-interface-tool-connections.md) |
| System Configuration → Instruments | Analyzers and how their result files are read | [Instruments](admin-instruments.md) |
| VL Config, EID Config, TB Config and the other config sections | The dropdown lists on each module's request form | [Request form lists](admin-module-configuration.md) |
| System Configuration → Geographical Divisions, Implementation Partners, Funding Sources, Lab Storage | The lists shared by every module | [Request form lists](admin-module-configuration.md) |
| System Configuration → General Configuration | Settings that apply to the whole installation | [General configuration](admin-general-configuration.md) |
| Monitoring | Audit trail, activity, page usage, sync, analyzer activity, lab performance | [Monitoring and audit](admin-monitoring.md) |

The menu shows a config section only for the modules the installation runs.

A second administration area, **System Admin**, sits outside this menu at
`/system-admin` and has its own login. See
[System Admin area](admin-system-administration.md).

??? info "On a LIS, facilities and lists are read-only"

    A LIS shows Facilities and the request form lists without add or edit
    buttons. They are maintained on the STS and reach the LIS when it syncs.
    Instruments, users and Lab Storage are still maintained on the LIS itself.

## Two levels of administrator

| Level | Owns |
| --- | --- |
| Lab administrator | Users, instruments, Interface Tool connections, audit trail lookups |
| National administrator | All of the above, plus roles, facilities, request form lists, General Configuration and Geographical Divisions |

**Choose the installation type.**

=== "Standalone or LIS"

    InteLIS enforces the split only through the privileges on each role. The
    same holds for national staff signed in to the STS.

    1. Give lab administrators a role without the national-level pages.
    2. Keep **Roles**, **General Configuration** and **Geographical Divisions**
       on the national administrator's role only.

    Most trouble in the field comes from national-level settings changed by
    lab-level staff.

=== "Cloud"

    Lab staff sign in to the STS with a testing-lab role. InteLIS enforces the
    split for every such user except the super administrator.

    1. Expect these five ADMIN pages, and no others: **Users**,
       **Instruments**, **Audit Trail**, **User Activity Log** and **Log File
       Viewer**. Role privileges decide which of the five appear.
    2. Send every other change to the national administrator on the STS.

    Users and instruments stay limited to the user's own lab. The Audit Trail
    opens only samples of that lab.

## Changes that need agreement

Each change below applies to the whole installation the moment it is saved.
Setting it back does not undo its effect on records already created.

| Change | Where | Effect |
| --- | --- | --- |
| Sample ID format or prefix | General Configuration, per module | New samples get the new format. Existing samples keep the old one |
| Sample Lock Days, Sample Expiry Days | General Configuration → Global Settings | Decide when a record stops accepting edits |
| Same user can Review and Approve | General Configuration → Global Settings | Lets one person review and approve the same result |
| VL, EID, COVID-19 or TB Auto Approve API Results | General Configuration, per module | Results arriving through the API are approved with no human check |
| Country of Installation | General Configuration → Global Settings | Changes the request form every user sees |
| Training Mode | General Configuration → Global Settings | Marks the installation as practice |
| Role privileges | Access Control → Roles | Apply at once to every user holding the role |
| Deleting a list entry | Any config section | Records that used the entry become unreadable. Set it inactive instead |
| Renaming or removing a province or district | System Configuration → Geographical Divisions | Facilities under it lose their link, and report filters stop matching |

## Rules that hold everywhere

- **One login per person.** The activity log and the tester, reviewer and
  approver names record whoever was signed in.
- **Retire, never delete.** An inactive entry leaves the form and stays readable
  on the records that use it.
- **Never give an old login to a new person.** The old records stay attached to
  the old name.
- **Change one setting at a time.** Check its effect before changing the next.
