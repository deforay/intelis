---
description: Sign in to the separate System Admin area to change instance configuration, read sync times, review sign-ins and reset passwords.
audience: [system-admin]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---

# How to use the System Admin area

System Admin is a second administration area at `/system-admin` on the
installation's own address. It holds the settings that decide what kind of
installation this is: its database, its instance type, its STS and its modules.

Most labs never open it. These settings are set once, at installation.

## Before starting

- A System Admin login. It is separate from InteLIS user accounts. An InteLIS
  administrator does not hold one automatically
- A fresh database backup before changing **System Configuration**. See
  [Maintenance](../guides/maintenance.md)

## The sidebar

| Sidebar item | Holds |
| --- | --- |
| System Configuration | Database connection, instance type, STS URL, lab, enabled modules, country, time zone, the Gmail account that sends support requests |
| Instance Overview | The instance ID and the last sync time of each data stream |
| API Stats | The API requests this installation has handled |
| User Login History | Every sign-in attempt to InteLIS, with IP address, browser and operating system |
| Reset Password | A new password for any InteLIS user |
| Sign out | Ends the System Admin session |

## Sign in

1. Open `/system-admin` on the installation's address, such as
   `https://lab.example.org/system-admin`.
2. In **User Name**, enter the Login ID chosen at registration, then the
   **Password**.
3. Select **Login**. **System Configuration** opens.

??? info "If no System Admin login exists yet"

    `/system-admin` opens **Register new System Admin** instead. The form asks
    for a **Secret Key**. It is in the file `var/secret-key.txt` of the
    installation folder, such as `/var/www/intelis/var/secret-key.txt`. On older
    installs, the folder is `/var/www/vlsm`. Open the page first, then read the
    key. It changes each time the page loads.

    Fill in **Secret Key**, **User Name**, **Email ID**, **Login ID**,
    **Password** and **Confirm Password**, and select **Submit**. The Login ID
    is the name used to sign in.

## Change the System Admin password

1. Sign in to System Admin.
2. Open the user menu at the top right and select **Change Password**. The
   **Edit Password** page opens.
3. Enter **Password** and **Confirm Password**.
4. Select **Submit**.

## Change the system configuration

1. Take a database backup.
2. Sign in to System Admin.
3. Select **System Configuration** in the sidebar. The page is titled **Edit
   System Configuration**.
4. Change the setting:

    | Section | Settings |
    | --- | --- |
    | System Settings | **Database Host Name**, **Database Username**, **Database Password**, **Database Name**, **Database Port** |
    | Instance Settings | **Instance Type**, **STS URL** and **Lab Name** (LIS only. Lab Name required), **Enabled Modules**, **Country of Installation**, **Timezone** |
    | SMTP Settings | **Email** and **Password** of the Gmail account that sends support requests |

5. Select **Submit**.
6. Open InteLIS and check the change. See [Confirm it worked](#confirm-it-worked).

??? warning "A wrong database setting stops InteLIS for everyone"

    A wrong **Database Password**, or any other wrong database value, makes
    InteLIS unreachable for every user until it is corrected. A blank
    **Database Password** is not kept blank: InteLIS saves a default password
    in its place.

??? warning "Instance Type"

    | Instance Type | Means |
    | --- | --- |
    | LIS - LAB INFORMATION SYSTEM | Runs in a lab and syncs to the STS |
    | STS - SAMPLE TRACKING SYSTEM | The central server labs sync to |
    | Standalone | Syncs nowhere |

    Changing the instance type on a running installation changes where its data
    goes, and which pages appear. Agree it with the national team first.

??? info "Enabled Modules"

    Enabling a module adds its menu and its config section. Disabling one hides
    them. The records already created stay in the database.

## Read the instance overview

1. Sign in to System Admin.
2. Select **Instance Overview** in the sidebar.
3. Read the rows:

    | Field | Means |
    | --- | --- |
    | Instance ID | The identifier this installation is known by |
    | Added On, Updated On | When the instance was registered and last changed |
    | VL Last Sync, EID Last Sync, Covid-19 Last Sync | When each module's data last went to the dashboard |
    | Remote Request Last Sync | When test requests last came from the STS |
    | Remote Results Last Sync | When results last went to the STS |
    | Remote Reference Last Sync | When the lists and facilities last came from the STS |

An old remote sync time means the installation is not reaching the STS. Confirm
it under **ADMIN → Monitoring → API History**. See
[Monitoring and audit](admin-monitoring.md#check-that-data-reached-the-sts).

The pencil button edits these times. Change them only when InteLIS support
asks.

## Check who has been signing in

1. Sign in to System Admin.
2. Select **User Login History** in the sidebar.
3. Set **Date** and **Login ID**, and select **Search**.
4. Read the rows: **Login ID**, **Attempted Datetime**, **IP Address**, **Browser**,
   **Operating System** and **Status**.

One Login ID signing in from several places at once points to a shared or
misused account.

## Reset a user's password

Use this when no InteLIS administrator can sign in to reset it.

1. Sign in to System Admin.
2. Select **Reset Password** in the sidebar.
3. Select the **User**.
4. Enter **Password** and **Confirm Password**, or select **Generate**.
5. Set **Status** to **Active**.
6. Select **Submit**.
7. Give the new password to the user in person. InteLIS does not ask the user
   to change this password.

## Confirm it worked

| Change | Check |
| --- | --- |
| Module enabled | Its section appears in the main menu and under ADMIN |
| Instance Type | The pages of that type appear |
| STS URL | API History records a successful sync to the new address |
| SMTP Settings | Send a support request and confirm it arrives |
| Password reset | The user signs in with the new password |
