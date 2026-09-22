---
description: Free an InteLIS update that stops at "Running database migrations...", and finish an update that failed part way.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.78
---
# Update Stuck at Database Migrations

An update prints `Running database migrations...` and then nothing more. In
almost every case the database step is waiting for another query to finish. The
data is safe. Nothing is being changed while it waits.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Update is still waiting"

    Use this when the update has shown `Running database migrations...` for more
    than 10 minutes, and the terminal is still open.

    Leave the update running. Do not press Ctrl+C.

    1. Open a second terminal on the InteLIS machine.
    2. List what MySQL is doing:

        ```bash
        sudo mysql -e "SHOW FULL PROCESSLIST"
        ```

    3. Find the rows with `Waiting for table metadata lock` in the `State`
       column. These are the update, and everything queued behind it. Do not
       stop these.
    4. Find the query they are waiting for. It works on the same table, is not
       waiting itself, and has the largest number in the `Time` column (in
       seconds). Note its `Id`.

        ??? info "If no running query stands out"

            An open transaction can hold the table while its connection shows
            `Sleep`. List the open transactions, oldest first:

            ```bash
            sudo mysql -e "SELECT trx_mysql_thread_id AS Id, trx_started, trx_query FROM information_schema.innodb_trx ORDER BY trx_started"
            ```

            The first row is almost always the one holding the table. Note its
            `Id`.

    5. Check what the query in step 4 does, in the `Info` column:
        - If it starts with `SELECT`, it only reads. Stopping it loses nothing.
          Go to step 6.
        - If it changes data (`INSERT`, `UPDATE`, `DELETE`, `ALTER`), wait for
          it to finish. If it is still running after an hour, contact support
          with the output of step 2.
    6. Stop the query, using the `Id` from step 4 in place of `12345`:

        ```bash
        sudo mysql -e "KILL 12345"
        ```

    7. Go back to the first terminal. The update carries on by itself within a
       minute.

        ??? failure "If it prints `still waiting on a lock after 30s`"

            The update is retrying. It tries three times, 30 seconds apart.
            Repeat steps 2 to 6 while it retries.

    8. Wait for the update to finish. Then check it, as in
       [Check the update](updating-intelis-on-ubuntu.md).

=== "Update failed or was stopped"

    Use this when the update ended with `Failed to update`, or was stopped with
    Ctrl+C or a closed terminal.

    1. Read the lines above `Failed to update`.

        ??? failure "If they end with `another connection has held a lock on ... for over 90s`"

            A long query held a table the update needed. Stop it as in the
            **Update is still waiting** situation, steps 2 to 6. Then carry on
            with step 2 below.

    2. Run the update again. Running it again is safe. Every step that already
       finished is skipped:

        ```bash
        sudo intelis update
        ```

    3. Check the result:

        ```bash
        sudo intelis check
        ```

        Expect `OK` on the rows `Schema version` and `Audit triggers`.

    If the update fails again at the same point, use the **Stuck on an old
    version** situation.

=== "Stuck on an old version"

    Use this when every update fails at the database step, or `intelis check`
    reports that the database is behind. The page footer also shows a red
    `DB ver.` warning with `(database not fully migrated)`.

    1. Confirm the database is behind:

        ```bash
        sudo intelis check
        ```

        The `Schema version` row reads
        `database is at 5.6.2, migrations go to 5.7.78`, with the versions of
        this machine.

    2. Run the migrations on their own:

        ```bash
        sudo intelis migrate
        ```

    3. Read the output.
        - If it shows `Halting further migrations after`, the migration named
          there has an error that a re-run does not fix. Contact support. Send
          the whole output of step 2.
        - Otherwise, check the `DB version` row in the `Migration summary`
          table. It ends at the newest version. Go to step 4.
    4. Put the audit triggers back:

        ```bash
        sudo intelis audit-triggers-install
        ```

    5. Run `sudo intelis check` again. Expect `OK` on `Schema version` and
       `Audit triggers`.

    !!! warning "Do not change the version number in the database by hand"

        The version records which migrations have run. Setting it by hand skips
        or repeats migrations, and leaves the database in a state no update can
        repair.

## After a failed update

??? failure "Imports, syncs and backups stopped after the failed update"

    The update pauses scheduled tasks during the database step. The pause
    lifts by itself after 30 minutes. To lift it now, follow step 3 of
    [Check the scheduled tasks are running](maintenance.md#check-the-scheduled-tasks-are-running).

??? failure "`intelis check` reports `no audit trigger on:`"

    The update stopped between removing and restoring the audit triggers.
    Changes to those tables are not recorded until the triggers are back:

    ```bash
    sudo intelis audit-triggers-install
    ```

??? info "Where the update log is"

    Every update writes a log to `/tmp/intelis-upgrade-<date>-<time>.log`.
    Send the newest one when contacting support.
