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

-   :material-server:{ .lg .middle } __Looking after the machine__

    ---

    Installing, updating, backing up, restoring, and the maintenance scripts
    that keep a lab machine healthy.

    [:octicons-arrow-right-24: Install on Ubuntu](guides/installing-intelis-on-ubuntu.md)

-   :material-printer-outline:{ .lg .middle } __Printable job aids__

    ---

    Thirteen single-page cards to print and pin up: seven for the lab bench,
    six for whoever looks after the machine.

    [:octicons-arrow-right-24: Open the job aids](job-aids/index.md)

-   :material-code-braces:{ .lg .middle } __Reference__

    ---

    How a request travels through the codebase, the bar a change has to meet,
    and the interactive API documentation.

    [:octicons-arrow-right-24: Architecture](ARCHITECTURE.md)

</div>

## Frequently needed

- [Update InteLIS](guides/updating-intelis-on-ubuntu.md) — one command, on a machine that is already running
- [Restore from a backup](guides/restoring-from-backup.md) — put the data back, or rebuild a machine that died
- [Sample statuses](user-guides/sample-statuses.md) — every status and what it means
- [Maintenance scripts](guides/maintenance.md) — service guard, resource monitor, db-tools, cleanup, and scheduled tasks
- [API reference](api/) — the interactive OpenAPI documentation
