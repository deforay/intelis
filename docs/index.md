# InteLIS Documentation

InteLIS is an open-source laboratory information system for HIV viral load, EID,
TB, hepatitis, COVID-19, CD4, and custom tests. Use the sidebar to navigate.

InteLIS was previously called VLSM. Some paths and the database keep the old
name, and the guides say so where it matters.

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
- [Backing up to a remote server](guides/backing-up-to-remote-server.md)
- [Backing up to a Windows machine](guides/backing-up-to-windows-machine.md) — over the local network

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
