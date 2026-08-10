# InteLIS Documentation

InteLIS is an open-source laboratory information system for HIV viral load, EID,
TB, hepatitis, COVID-19, CD4, and custom tests. Use the sidebar to navigate.

InteLIS was previously called VLSM. Some paths and the database keep the old
name, and the guides say so where it matters.

## Using InteLIS

Guides for the people who use InteLIS day to day. These cover the viral load
workflow.

- [How a sample moves through InteLIS](user-guides/index.md) — the whole journey, start to finish
- [Signing in and navigating InteLIS](user-guides/signing-in.md)
- [Register a test request](user-guides/register-a-request.md) — for samples that arrive with a paper form
- [Receive samples sent on a manifest](user-guides/receive-referred-samples.md) — register a whole package at once
- [Send samples on a manifest](user-guides/send-samples-on-a-manifest.md) — pack samples for a testing lab
- [Batch samples for testing](user-guides/batch-samples.md) — build a batch and load the analyzer
- [Capture results](user-guides/capture-results.md) — the Interface Tool, file import, and manual entry
- [Review and approve results](user-guides/approve-results.md)
- [Handle failed and held samples](user-guides/failed-and-held-samples.md) — retest and recovery
- [Release results](user-guides/release-results.md) — print, email, and export
- [Record where a sample is stored](user-guides/store-samples.md)
- [Administer InteLIS](user-guides/administer-intelis.md) — users, roles, facilities, analyzers, and form settings
- [For requesting facilities](user-guides/for-requesting-facilities.md)
- [Sample statuses](user-guides/sample-statuses.md) — every status and what it means
- [Viral load reports](user-guides/reports.md) — what each report shows

## Printable job aids

- [Job aids](job-aids/index.md) — seven single-page cards to print and pin at the workstation

## Installation

- [Installing InteLIS with Docker](guides/installing-intelis-with-docker.md) — the quickest route, and the recommended one
- [Installing InteLIS on Ubuntu](guides/installing-intelis-on-ubuntu.md) — Ubuntu 22.04 LTS or above
- [Installing InteLIS on Windows](guides/installing-intelis-on-windows.md) — WampServer, PHP, MySQL, and interfacing

## Updates and migration

- [Updating InteLIS on Ubuntu](guides/updating-intelis-on-ubuntu.md)
- [Updating InteLIS on Windows](guides/updating-intelis-on-windows.md)
- [Migrating between Ubuntu machines](guides/migrating-ubuntu-machines.md)

## Backup

- [Backing up to Google Drive with Rclone](guides/backing-up-to-google-drive-with-rclone.md)
- [Backing up to another Linux machine](guides/backing-up-to-remote-server.md) — over SSH
- [Backing up to a Windows machine](guides/backing-up-to-windows-machine.md) — over the local network
- [Restoring from a backup](guides/restoring-from-backup.md) — fetch a backup and put the data back

## Maintenance

- [Maintenance scripts](guides/maintenance.md) — service guard, resource monitor, db-tools, cleanup, scanner, and scheduled tasks

## Remote administration

- [Remote command plane runbook](guides/remote-command-plane.md) — queue commands from the STS and monitor them

## Troubleshooting

- [Fix a collation mismatch](guides/fix-collation-issue.md)
- [Fix a permission denied error](guides/permission-denied-issue.md)
- [Set up the interfacing tool](guides/setting-up-interfacing-tool.md)

## Reference

- [Architecture](ARCHITECTURE.md) — how a request travels through the codebase
- [Engineering standards](engineering-standards.md) — the bar for a change, the review step, and the standing invariants
- [API reference](api/) — the interactive OpenAPI documentation
