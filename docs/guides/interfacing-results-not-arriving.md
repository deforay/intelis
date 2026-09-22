---
description: Connect InteLIS to the Interfacing Tool from the command line, and import analyzer results now instead of waiting for the scheduled import.
audience: [system-admin, lab-admin]
module: [vl, eid, covid19, hepatitis, tb, cd4, custom-tests]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.78
---
# Analyzer Results Not Arriving

InteLIS imports the results held by the Interfacing Tool every minute. One
command connects the two, and the same command runs the import on demand:

```bash
sudo intelis interface
```

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Not connected yet"

    Use this when the command reports `Interfacing is not enabled.` or
    `Interfacing is enabled, but no analyzer database is configured.`

    1. Open a terminal on the InteLIS machine and run:

        ```bash
        sudo intelis interface
        ```

    2. At `Set interfacing up now?`, press Enter to answer **Yes**.
    3. Answer the setup questions. They ask where the Interfacing Tool runs and
       which database it writes to.
       [Connect an Instrument to InteLIS](setting-up-interfacing-tool.md)
       explains each answer.
    4. Wait for setup to finish. The command then runs the first import by
       itself. Expect the results counted:

        ```text
        Connected to MySQL
        # of records from MySQL : 3
        Processing 3 filtered results from Interface Tool
        ```

        ??? failure "If it stops with `The InteLIS database is not reachable`"

            Run `sudo intelis fix-database`. Then repeat step 1.

        ??? failure "If `intelis` is not recognised"

            The install is older.
            [Update InteLIS](updating-intelis-on-ubuntu.md), then repeat
            step 1.

        ??? info "If the Interfacing Tool runs on another computer"

            Setup prints the database settings to enter in the tool. Follow
            [Enter the database in the tool](setting-up-interfacing-tool.md)
            before expecting any results.

    After this, the scheduled import runs every minute. The steps above never
    need repeating unless the tool moves to another computer. To change the
    connection later, run `sudo intelis interface setup`.

=== "Results are late"

    Use this when interfacing is connected, a result is on the tool, and it is
    not in InteLIS yet.

    1. Run:

        ```bash
        sudo intelis interface
        ```

    2. Check the count on the line `# of records from MySQL`.

        ??? failure "If it prints `Another instance of the script : interface.php is already running.`"

            The scheduled import is running now. Wait one minute, then repeat
            step 1. If the message repeats for more than 15 minutes, a run is
            stuck. Force a new run:

            ```bash
            sudo intelis interface force
            ```

        ??? failure "If it counts `0` records"

            The result has not reached the database InteLIS reads.

            - On the tool, check the **Sync Status** column. If every result
              is `Pending`, the tool is not writing to the database. Repeat
              **Test Connection** in the tool's **Settings**, under **MySQL**.
            - Only final results and failed runs are imported. A result the
              analyzer has not finalised stays in the tool.
            - The result was already read once. Use the **Older results
              missed** situation.

        ??? failure "If it prints `Error while syncing interface results`"

            Open the newest file in `/var/www/intelis/var/logs` and search for
            the error. Send it to support if it does not name a fix.

    3. Open the sample in InteLIS. Check that the result is there.

        ??? failure "If the result is counted but not in InteLIS"

            The sample ID on the analyzer must match the sample code of a
            registered request in InteLIS, character for character. Register
            the request, or correct the ID on the analyzer. Then use the
            **Older results missed** situation.

    4. If results now arrive by hand but still not on their own, check the
       scheduler. The scheduled import is part of `cron.sh`:

        ```bash
        sudo crontab -l | grep cron.sh
        ```

        Expect this line:

        ```text
        * * * * * cd /var/www/intelis && ./cron.sh
        ```

        If the line is missing, see [Maintenance scripts](maintenance.md).

=== "Older results missed"

    Use this when results reached the tool before the request was registered,
    or before the sample ID was corrected. Each import reads only what arrived
    since the last one, so a result that found no matching sample is not read
    again.

    1. Pick how far back to read. Give a number of days, or a date as
       `YYYY-MM-DD`:

        ```bash
        sudo intelis interface 7
        ```

        ```bash
        sudo intelis interface 2026-09-01
        ```

    2. Check the count on the line `# of records from MySQL`. It includes every
       result from that period, not only the missed ones. Results InteLIS
       already holds are left as they are.
    3. Open the sample in InteLIS. Check that the result is there.

        ??? failure "If the sample's results are locked"

            A normal import leaves a locked sample alone. Use the **Locked
            sample** situation.

    !!! warning "Keep the period short"

        If a result was corrected by hand in InteLIS after the analyzer sent
        it, reading that period again puts the analyzer's result back. InteLIS
        keeps the replaced result in the sample's history.

=== "Locked sample"

    Use this when the analyzer re-ran a sample whose results are already locked
    in InteLIS, and the new result must replace the locked one.

    1. Confirm the lab wants the locked result replaced. A normal import never
       changes a locked sample.
    2. Run the import with `force`, and the number of days to read:

        ```bash
        sudo intelis interface 7force
        ```

        `force` on its own reads only what arrived since the last import. Put
        the days in front of it to read further back.

    3. Open the sample in InteLIS. Check that the new result is there.

## Command options

Options go after `sudo intelis interface`, separated by spaces. Use one period
option at most. They combine, as in `sudo intelis interface 7 silent`.

| Option | Example | What it does |
| --- | --- | --- |
| None | `sudo intelis interface` | Imports what arrived since the last import. Offers setup if interfacing is not connected. |
| `setup` | `sudo intelis interface setup` | Runs setup, whether or not interfacing is connected. Shows the current settings first. |
| Days | `sudo intelis interface 7` | Reads everything from the last 7 days, plus anything never imported. |
| Date | `sudo intelis interface 2026-09-01` | Reads everything since that date, plus anything never imported. |
| `force` | `sudo intelis interface force` | Imports into locked samples too. Runs even when another import is still running. |
| Days with `force` | `sudo intelis interface 7force` | Reads the last 7 days and imports into locked samples. |
| `silent` | `sudo intelis interface silent` | Imports without changing each sample's last-modified time, so the sample lists keep their order. |
