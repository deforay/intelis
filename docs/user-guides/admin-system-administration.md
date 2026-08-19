# How to use the System Admin area

This guide covers `/system-admin`, a second administration area that sits outside
the ADMIN menu and has its own login.

It holds the settings that decide what kind of installation this is: the database
it connects to, whether it is a lab system or the national server, and which
modules it runs.

## Before starting

- A System Admin login. This is not an InteLIS user account, and an InteLIS
  administrator does not automatically hold one
- A backup of the database, before changing anything on the Edit System
  Configuration page

Reach the area at `/system-admin` on the installation's own address.

Most labs never open this area. The settings here are set once at installation.
Changing them on a running installation can stop it working.

## What the area holds

| Page | Holds |
|---|---|
| Manage System Config | Database connection, instance type, STS URL, enabled modules, country, time zone, SMTP settings |
| System Instance Overview | The instance id, its type, and when each module last synced |
| API Stats | The API traffic this installation has handled |
| Manage User Login History | Every sign-in attempt, with IP address, browser, and operating system |

## Read the instance overview

1. Sign in at `/system-admin`.
2. Open **System Instance Overview**.

| Field | Means |
|---|---|
| Instance Id | The identifier the national server knows this installation by |
| Instance Type | LIS, STS, or Standalone |
| Lab Name | The lab this installation belongs to |
| Last Sync, per module | When each module last exchanged data |

A module whose last sync is old is not reaching the national server. Confirm it
against **ADMIN → Monitoring → API History**. See
[Monitoring and audit](admin-monitoring.md).

## Understand the instance type

| Type | Means |
|---|---|
| LIS | A lab information system. It runs in a lab and syncs to the national server |
| STS | The sample tracking system. The national server that labs sync to |
| Standalone | Neither. It syncs nowhere |

The instance type decides which pages appear. Lab Sync Status and API Dashboard
appear on an STS instance only.

Changing the instance type on a running installation changes where its data goes.
Agree it with the national team first.

## Check who has been signing in

1. Sign in at `/system-admin`.
2. Open **Manage User Login History**.

Each row carries the Login Id, the attempted date and time, the IP address, the
browser, and the operating system.

Use it when an account is suspected of being shared or used by someone else. The
rows show whether one Login Id signs in from several places.

## Change the system configuration

**Manage System Config** holds the database credentials, the instance type, the
STS URL, the enabled modules, the country of installation, the time zone, and the
SMTP settings.

Take a database backup first. A wrong database credential makes InteLIS
unreachable for every user until it is corrected.

Enabling a module adds its menu section and its config section. Disabling one
hides them. The records already created stay in the database.

## Confirm it worked

| Change | Check |
|---|---|
| Module enabled | Its section appears in the main menu and under ADMIN |
| Instance type | The pages that belong to that type appear |
| STS URL | API History records a successful sync to the new address |
| SMTP settings | Send one result by email and confirm it arrives |
