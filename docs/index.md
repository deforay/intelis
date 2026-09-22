---
description: Entry point to InteLIS documentation for lab work, machine care, troubleshooting, job aids and the API.
audience: [lab-staff, lab-supervisor, lab-admin, system-admin, requesting-facility, developer]
module: [all]
type: reference
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# InteLIS Documentation

InteLIS is an open-source laboratory information system for HIV viral load, EID,
TB, hepatitis, COVID-19, CD4, and custom tests.

InteLIS was previously called VLSM. Some paths and the database keep the old
name, and the guides say so where it matters.

<div class="grid cards" markdown>

-   :material-flask-outline:{ .lg .middle } __Using InteLIS__

    ---

    Day-to-day work in the lab: registering requests, receiving and sending
    manifests, batching, capturing results, approving and releasing them.

    [:octicons-arrow-right-24: How a sample moves through InteLIS](user-guides/index.md)

    [:octicons-arrow-right-24: Register a test request](user-guides/register-a-request.md)

-   :material-server:{ .lg .middle } __Looking after the machine__

    ---

    Installing, updating, backing up, restoring, and the maintenance scripts
    that keep a lab machine healthy.

    [:octicons-arrow-right-24: Update InteLIS](guides/updating-intelis-on-ubuntu.md)

    [:octicons-arrow-right-24: Install on Ubuntu](guides/installing-intelis-on-ubuntu.md)

    [:octicons-arrow-right-24: Move a lab or rebuild a dead machine](guides/migrating-ubuntu-machines.md)

    [:octicons-arrow-right-24: Connect an instrument](guides/setting-up-interfacing-tool.md)

    [:octicons-arrow-right-24: Set up off-machine backups](guides/setting-up-off-machine-backups.md)

-   :material-lifebuoy:{ .lg .middle } __Something is wrong__

    ---

    Start on the machine with `intelis check`, or `intelis doctor` when the
    site will not open. These pages cover the problems they point to.

    [:octicons-arrow-right-24: MySQL will not start](guides/mysql-will-not-start.md)

    [:octicons-arrow-right-24: Browser shows PHP code](guides/browser-shows-php-code.md)

    [:octicons-arrow-right-24: Permission denied errors](guides/permission-denied-issue.md)

    [:octicons-arrow-right-24: Collation errors](guides/fix-collation-issue.md)

-   :material-printer-outline:{ .lg .middle } __Printable job aids__

    ---

    Eighteen single-page cards to print and pin up: seven for the lab bench,
    five for the administrator, and six for whoever looks after the machine.

    [:octicons-arrow-right-24: Cards for the lab bench](job-aids/index.md#for-the-lab)

    [:octicons-arrow-right-24: Cards for the administrator](job-aids/index.md#for-the-administrator)

    [:octicons-arrow-right-24: Cards for the machine](job-aids/index.md#for-the-machine)

</div>

## Frequently needed

- [Restore from a backup](guides/restoring-from-backup.md): put the data back on a machine that still runs InteLIS
- [Sample statuses](user-guides/sample-statuses.md): every status and what it means
- [Maintenance scripts](guides/maintenance.md): service guard, resource monitor, db-tools, cleanup, and scheduled tasks

For developers: [Architecture](ARCHITECTURE.md), [Engineering standards](engineering-standards.md) and the [API reference](api/).
