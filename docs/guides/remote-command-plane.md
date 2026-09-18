# Running Commands on a Lab from the STS

Resend data, refresh, upgrade or roll back a connected lab from the STS, with
nobody at the lab machine.

For how the plane works and why, see the
[remote command plane design](../remote-command-plane.md).

**Before starting, check both of these:**

- The STS account's role has **Queue Lab Command**, **Cancel Lab Command** and
  **Lab Command History** under **Monitoring**. Set them in
  **ADMIN → Access Control → Roles**. Without **Queue Lab Command**, the
  **Queue** button does not appear.
- The lab is online and syncing with the STS.

## Queue a command

1. Go to **Admin → Monitoring → Lab Sync Status**.
2. Find the lab's row. In the **Command plane** column, check that the
   **courier** chip shows a recent time. Upgrade, rollback and the other root
   commands also need a recent **runner** chip.

    ??? info "If the column shows only a dash"

        The lab has never reported the command plane. Either its release is too
        old, or remote commands are off on the lab. See
        [Turn remote commands on or off for a lab](#turn-remote-commands-on-or-off-for-a-lab).

3. Select **Queue** in the **Actions** column.

    ??? failure "If **Queue** is greyed out"

        The lab has not polled for commands in the last 24 hours. Queueing
        stays off until it does. Check the lab machine is on, online and has
        remote commands turned on.

    ??? warning "If the dialog warns about installations"

        More than one installation has reported for this lab recently. A queued
        command goes to whichever one polls first, and the other never sees it.
        Find out which machine is live before queueing an upgrade or a rollback.

4. Choose the **Command**. See [Commands](#commands) for what each one does.
   Greyed-out commands are ones the lab has not reported it can run.

    ??? info "If the upgrade and rollback commands are greyed out"

        The lab offers only the basic commands. One of these applies:

        - **Allow Remote Upgrade** is off on the lab. See
          [Turn remote commands on or off for a lab](#turn-remote-commands-on-or-off-for-a-lab).
        - The lab's database runs on another machine, or the lab machine has no
          systemd. Upgrade that lab the way it was installed.
        - The lab has not sent a full capability report yet. Wait one sync, then
          reopen the dialog.

5. Fill in the fields the command shows:

    | Field | Shown for | What to enter |
    | --- | --- | --- |
    | Module (optional) | Resend results, Resend requests | A module, or leave **All enabled modules**. |
    | Resend data from last N days | Resend results, Resend requests | A number of days from 1 to 3650. Leave blank to send only records not yet synced. **Resend requests** ignores this field. |
    | Prepared staging to apply | Apply a prepared upgrade | The staged release to install. |
    | Not before (optional) | Upgrade, Prepare upgrade only, Apply a prepared upgrade | The earliest time the lab may start, by the STS clock. Leave blank to start at the next sync. |
    | Show maintenance page to users during apply | Upgrade, Apply a prepared upgrade | Select it when the release runs database migrations or changes composer dependencies. |

6. Select **Queue command**. The message **Command queued.** shows the command
   ID. A yellow badge such as `upgrade: pending` appears on the lab's row.

    ??? failure "If it says a matching command is already in flight"

        The same command is already waiting or running for this lab. Wait for it
        to finish, or cancel it while it is still `pending`.

7. Wait for the lab's next sync. The lab syncs about every 5 minutes. The badge
   changes to `picked` or `running`, then disappears when the command finishes.

    ??? tip "To cancel before the lab picks it up"

        Select **×** on the badge. This works only while the status is
        `pending`. Once the lab has picked the command up, it runs to the end.

To see how it ended, follow [Check a command's result](#check-a-commands-result).

### Commands

| Command | What it does | Needs the runner |
| --- | --- | --- |
| Ping (self-test, no side effects) | Confirms the lab picks up and reports commands. Changes nothing. | No |
| Resend results | Sends results to the STS again. | No |
| Resend requests | Pulls test requests from the STS again. | No |
| Metadata resync (force) | Pulls all reference data from the STS, ignoring the last sync time, and sends the lab's metadata. | No |
| Refresh cache | Clears the lab's file cache. | No |
| Rotate STS token | Drops the lab's STS token and fetches a new one. | No |
| Refresh permissions | Resets file ownership and permissions on the installation. | Yes |
| Restart Apache | Restarts the web server gracefully. | Yes |
| Upgrade (prepare + auto-apply) | Downloads the current release and installs it in one go. | Yes |
| Prepare upgrade only | Downloads and checks the current release. Does not install it. | Yes |
| Apply a prepared upgrade | Installs a release staged by **Prepare upgrade only**. | Yes |
| Roll back to the pre-upgrade snapshot (code only) | Restores the code from before the last upgrade. The database stays as it is. | Yes |

## Upgrade a lab

Choose one way, then follow its steps from top to bottom.

=== "One step (upgrade)"

    Use this for a routine release on one lab or a few.

    ### Queue the upgrade

    1. Go to **Admin → Monitoring → Lab Sync Status**.
    2. On the lab's row, check that the **runner** chip in the **Command plane**
       column shows a recent time.
    3. Select **Queue**.
    4. Choose **Upgrade (prepare + auto-apply)**.
    5. To start at a set time, enter it in **Not before**. Pick a time when the
       lab is quiet.
    6. If the release runs database migrations or changes composer
       dependencies, select **Show maintenance page to users during apply**.
       Users then see an "upgrade in progress" page during the install instead
       of errors.
    7. Select **Queue command**.
    8. Wait. Users keep working while the release downloads. The install itself
       is short.

    ### Check the upgrade

    9. When the `upgrade` badge disappears, check that the **Version** column
       shows the new release.
    10. Select **Lab Command History**. Find the `upgrade` row for the lab and
        select **Details**. **Exit code** shows `0` and **succeeded**.

        ??? failure "If the status is `failed`"

            Read the output in **Details**.

            - If the smoke check failed, the updater already restored the
              previous code. The lab runs the old release on the migrated
              database.
            - If the database migrations failed, the new code stays in place and
              is not rolled back. With the maintenance page selected, the lab
              keeps showing it. Someone at the lab machine must fix the cause
              and run `intelis update` again.

=== "Staged (prepare, pilot, apply)"

    Use this for a risky release or many labs. Each lab downloads the release
    first, and installs it only when an apply is queued.

    ### Stage the release

    1. Go to **Admin → Monitoring → Lab Sync Status**.
    2. On each lab's row, check that the **runner** chip in the **Command
       plane** column shows a recent time.
    3. On each lab's row, select **Queue**.
    4. Choose **Prepare upgrade only**.
    5. To start at a set time, enter it in **Not before**.
    6. Select **Queue command**.
    7. Wait for a blue **Staged: vX.Y.Z** badge on each lab's row. Users are not
       affected while a lab prepares.

        ??? info "If a lab shows two **Staged** badges"

            Each prepare replaces the older staging on the lab machine. Apply the
            newest one. The older one fails with
            `staging dir invalid or READY sentinel missing`.

    ### Apply on pilot labs

    8. Pick two or three pilot labs.
    9. On a pilot lab's row, select **Queue**.
    10. Choose **Apply a prepared upgrade**.
    11. In **Prepared staging to apply**, choose the staged release.
    12. To start at a set time, enter it in **Not before**.
    13. If the release runs database migrations or changes composer
        dependencies, select **Show maintenance page to users during apply**.
    14. Select **Queue command**.
    15. When the `upgrade-apply` badge disappears, check that the **Version**
        column shows the new release.
    16. Select **Lab Command History**. Find the `upgrade-apply` row for the lab
        and select **Details**. **Exit code** shows `0` and **succeeded**.

        ??? failure "If the status is `failed`"

            Read the output in **Details**.

            - If the smoke check failed, the updater already restored the
              previous code. The lab runs the old release on the migrated
              database.
            - If the database migrations failed, the new code stays in place and
              is not rolled back. With the maintenance page selected, the lab
              keeps showing it. Someone at the lab machine must fix the cause
              and run `intelis update` again.

        ??? info "If the **Staged** badge stays after a successful apply"

            The badge does not clear by itself. Go by the **Version** column.
            Do not apply the same staging again. It fails with
            `no prepared record`.

    17. Repeat steps 9 to 16 for the other pilot labs.
    18. Watch the pilots for a day or two.

    ### Apply on the other labs

    19. If the pilots are healthy, repeat steps 9 to 16 for each remaining lab.

## Roll back a lab

Roll back when an upgrade went through but the lab misbehaves on the new
release. A rollback restores the code from the snapshot taken right before the
last upgrade. It does not touch the database. Migrations only run forward, so
the previous release then runs against the newer database.

Choose one way, then follow its steps from top to bottom.

=== "From the STS"

    ### Queue the rollback

    1. Go to **Admin → Monitoring → Lab Sync Status**.
    2. On the lab's row, check that the **runner** chip in the **Command plane**
       column shows a recent time.
    3. Select **Queue**.
    4. Choose **Roll back to the pre-upgrade snapshot (code only)**. A warning
       explains that the database is not rolled back.

        ??? info "If the rollback command is greyed out"

            The lab has not reported it can run rollbacks. Its courier is older
            than the rollback command, or **Allow Remote Upgrade** is off on the
            lab. Use the **On the lab machine** tab instead.

    5. Select **Queue command**.

        ??? failure "If it reports `Unknown command`"

            This STS does not accept rollbacks from the queue yet. Use the
            **On the lab machine** tab instead.

    6. Wait for the lab's next sync, about 5 minutes. The `rollback` badge
       disappears when the command finishes.

    ### Check the rollback

    7. Select **Lab Command History**. Find the `rollback` row for the lab and
       select **Details**.
    8. Check that **Exit code** shows `0` and the output ends with
       `Rolled back` and the installation path. The output also names the
       snapshot it used, after `Most recent snapshot:`.

        ??? failure "If the output says `No rollback snapshot found`"

            There is nothing to go back to. The last upgrade ran without a
            snapshot, or none was ever taken on this machine.

        ??? failure "If the output says `composer install failed during rollback`"

            The code is restored but `vendor/` is missing, so the lab does not
            open. The lab machine needs internet access. Someone at the lab
            machine runs `intelis update --rollback` again.

    9. After the next sync, check that the **Version** column shows the previous
       release.
    10. Ask the lab to open InteLIS and confirm the pages they use work.

=== "On the lab machine"

    This way always works, even when the lab cannot reach the STS.

    ### Run the rollback

    1. Open a terminal on the lab machine.
    2. Run:

        ```bash
        intelis update --rollback
        ```

        Enter the password when asked.

        ??? info "If `intelis` is not recognised"

            The install is older. Run the updater directly:

            ```bash
            sudo intelis-update -p /var/www/intelis --rollback
            ```

            On older installs, use `/var/www/vlsm` in place of
            `/var/www/intelis`.

    3. Read the line starting with `Most recent snapshot:`. It names the
       snapshot being restored.
    4. Wait for `Rolled back` and the installation path.

        ??? failure "If it says `No rollback snapshot found`"

            There is nothing to go back to. The last upgrade ran without a
            snapshot, or none was ever taken on this machine.

        ??? failure "If it says `composer install failed during rollback`"

            The code is restored but `vendor/` is missing, so the lab does not
            open. Connect the machine to the internet and run the same command
            again.

    ### Check the rollback

    5. Open InteLIS in the browser.
    6. Confirm the pages the lab uses work.

    !!! warning "Do not copy the snapshot back by hand"

        The snapshot leaves out uploads, runtime data and `vendor/`. Copying it
        over the installation with `rsync --delete` deletes the lab's uploads and
        leaves no `vendor/`. The rollback command skips the same folders and
        reinstalls `vendor/`.

After a rollback, fix the cause, then upgrade again with a corrected release.

## Check a command's result

1. Go to **Admin → Monitoring → Lab Sync Status**.
2. Select **Lab Command History**. It opens in a new tab and lists the 200 most
   recent commands.
3. To find an older command, narrow the list by **Lab**, **Command**,
   **Status** or **Date range**, then select **Search**.
4. On the command's row, select **Details**.
5. Read **Status**. For a finished command, also read **Exit code** and the
   output below it.

| Status | Meaning |
| --- | --- |
| `pending` | Queued. The lab has not picked it up yet. |
| `picked` | The lab has picked it up. |
| `running` | The lab machine is running it. |
| `prepared` | A **Prepare upgrade only** finished. The release waits for an apply. |
| `completed` | Finished. **Exit code** is `0`. |
| `failed` | Finished with an error. Read the output. |
| `expired` | Passed its deadline before the lab ran it. |
| `cancelled` | Cancelled while `pending`. |

To run a finished command again with the same settings, select **Replay** on
its row.

??? failure "If a command stays `pending`"

    The lab is not polling. Check the **courier** chip on
    **Lab Sync Status**. If it is old or missing, the lab is offline or has
    remote commands turned off. A command with **Not before** also stays
    `pending` until that time.

??? failure "If a command stays `picked` or `running`"

    The lab took it but has not reported back. On the lab machine, read the
    runner log for root commands:

    ```bash
    sudo tail -n 100 /var/log/intelis-runner/runner-$(date +%Y%m%d).log
    systemctl status intelis-runner.timer
    ```

    For other commands, read `/var/log/apache2/error.log` and the application
    logs.

??? failure "If it failed with `Stale: no status report received within 2 hours of pick-up`"

    The STS stopped waiting after 2 hours. The command may still have finished
    on the lab. For an upgrade, check the **Version** column before queueing
    it again.

??? failure "If the result says `runner disabled on this instance`"

    **Allow Remote Upgrade** is off on the lab, so the runner refuses root
    commands. See
    [Turn remote commands on or off for a lab](#turn-remote-commands-on-or-off-for-a-lab).

## Turn remote commands on or off for a lab

Two settings on the lab control remote commands. Both are on by default.

| Setting | When off |
| --- | --- |
| `remote_commands_enabled` | The lab ignores all remote commands. Queued commands stay `pending`. |
| `allow_remote_upgrade` | The lab refuses root commands: upgrades, rollback, **Refresh permissions** and **Restart Apache**. The other commands still run. |

No screen changes these settings. Change them in the lab's database, on the lab
machine.

1. Open a terminal on the lab machine.
2. Open the database:

    ```bash
    sudo mysql vlsm
    ```

    If the database has another name, use the name in
    `configs/config.production.php`.

3. Run the statement for the change needed:

    | To | Run |
    | --- | --- |
    | Turn all remote commands off | `UPDATE global_config SET value = 'no' WHERE name = 'remote_commands_enabled';` |
    | Turn only root commands off | `UPDATE global_config SET value = 'no' WHERE name = 'allow_remote_upgrade';` |
    | Turn all remote commands back on | `UPDATE global_config SET value = 'yes' WHERE name = 'remote_commands_enabled';` |
    | Turn root commands back on | `UPDATE global_config SET value = 'yes' WHERE name = 'allow_remote_upgrade';` |

4. Check the result:

    ```sql
    SELECT name, value FROM global_config
     WHERE name IN ('remote_commands_enabled', 'allow_remote_upgrade');
    ```

5. Type `exit` to leave the database.
6. Clear the cache so the lab reads the new value now, not within the hour:

    ```bash
    intelis purge-cache
    ```

7. Wait for the lab's next sync, about 5 minutes.

    ??? warning "A forced metadata sync turns a setting back on"

        Both settings come down from the STS with the lab's reference data. A
        forced metadata sync, including the **Metadata resync (force)**
        command, copies the STS value over the lab's own. Check the setting
        again after one.

### Check the change

8. On **Lab Sync Status**, open the lab's **Queue** dialog.

    - With root commands off, the upgrade and rollback commands are greyed out.
    - With all remote commands off, the **courier** chip stops updating. After
      24 hours, the **Queue** button is greyed out.
    - With them back on, the commands are offered again after the next sync.
