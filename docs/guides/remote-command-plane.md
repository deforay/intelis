# Remote command plane: operator runbook

> Admin-facing guide. For the architecture and the trust model, see the
> [Remote Command Plane design](../remote-command-plane.md), which is
> authoritative for the command set and the data model.

This guide is for operators who manage STS and the labs connected to it.
It covers how to check whether remote commands are enabled on a lab, queue
common commands, monitor their progress, and roll back a bad upgrade.

## What is it?

STS can queue commands, such as "resend results from the last 45 days",
"refresh cache" or "upgrade", for any connected LIS. The LIS pulls the queue
on its normal 5-minute sync tick and executes commands locally.
Root-privileged commands such as upgrades run through a systemd-timed
runner. Nothing pushes into the LAN from the cloud: commands are pulled by
the LIS, which preserves the usual one-way security model.

Commands offered by the queue form:

| Command           | Runs as      | What it does                                                      |
|-------------------|--------------|-------------------------------------------------------------------|
| `ping`            | www-data PHP | Self-test with no side effects. Confirms the courier is running.  |
| `resend-results`  | www-data PHP | Re-runs `results-sender.php` with optional module and days filter |
| `resend-requests` | www-data PHP | Re-runs `requests-receiver.php` with optional module filter       |
| `metadata-resync` | www-data PHP | Forces metadata sync from STS and lab metadata send               |
| `refresh-cache`   | www-data PHP | Clears the file cache (optional tag filter)                       |
| `rotate-token`    | www-data PHP | Drops and re-fetches the STS bearer token                         |
| `refresh-perms`   | root runner  | `intelis-refresh -p <lis> -m full`                                |
| `restart-apache`  | root runner  | `apache2ctl -k graceful`                                          |
| `upgrade`         | root runner  | Prepare and auto-apply back to back in one shot                   |
| `upgrade-prepare` | root runner  | Download, extract and validate. Does not apply.                   |
| `upgrade-apply`   | root runner  | Applies a previously prepared upgrade                             |
| `rollback`        | root runner  | Restores the pre-upgrade snapshot, code only                      |

`resend-requests` also accepts a specific manifest code, but the queue form
has no field for it. Only the module filter can be sent from STS.

## Checking whether remote commands are enabled on a lab

Both switches default to enabled. Migrations `5.5.2.sql` and `5.5.3.sql` seed
`remote_commands_enabled` and `allow_remote_upgrade` as `yes` on fresh and
upgraded installations alike, so a lab accepts remote commands, including root
commands, unless someone has turned them off.

Check the current state on that lab's LIS database:

```sql
SELECT name, value FROM global_config
 WHERE name IN ('remote_commands_enabled', 'allow_remote_upgrade');
```

To turn either off:

```sql
UPDATE global_config SET value = 'no'
 WHERE name IN ('remote_commands_enabled', 'allow_remote_upgrade');
```

The effect of each:

- `remote_commands_enabled` set to anything non-truthy stops the courier
  polling the pending-commands endpoint. Queued commands on STS sit at
  `pending` until it is turned back on or they are cancelled.
- `allow_remote_upgrade` set to `no` makes the courier drop a
  `var/remote-commands/disabled` flag file, and the runner then refuses all
  root commands. Non-root commands such as resends and cache refresh continue
  to work.

To schedule an upgrade for a specific time, use the **Not before** field when
queueing the command from STS. There is no global quiet window; every command's
timing is per command.

The next scheduled LIS upgrade, or a fresh `sudo intelis-update`, installs the
privileged runner and its systemd timer automatically. There is no separate
bootstrap step.

Verify the install:

```bash
systemctl status intelis-runner.timer        # active (waiting)
systemctl list-timers | grep intelis         # shows next fire time
sudo tail -f /var/log/intelis-runner/runner-*.log
```

## Queueing a command from STS

1. Go to **Admin → Monitoring → Lab Sync Status**.
2. Find the lab's row and select **Queue**.
3. Pick a command from the dropdown. The modal shows only the fields that
   command needs:
   - **Resend results:** optional module (VL, EID and so on) and an optional
     **Resend data from last N days**. Leave both blank to send only unsynced
     records.
   - **Resend requests:** optional module. The days field is ignored by this
     command even when the form allows it to be filled in.
   - **Upgrade, Prepare upgrade only, Apply a prepared upgrade:** optional
     **Not before** time. Leave it blank to run on the next sync tick, about
     five minutes, or pick a datetime to schedule it.
   - **Apply a prepared upgrade:** select from the dropdown of staged upgrades
     for this lab, which is populated only if a prior prepare completed.
4. For a release that changes `composer.lock` or runs migrations, select
   **Show maintenance page to users during apply**. It is unchecked by default,
   and without it requests can reach a partially replaced application while the
   apply is in progress.
5. Select **Queue command**. The row's badge updates within a few seconds
   showing `pending`. The LIS picks it up within about five minutes.

Bulk rollout: prepare on many labs first, then apply on pilots, then apply on
the rest. See "Gated apply" below.

## Monitoring

### Lab Sync Status page

Each row shows badges for that lab:

- Blue **Staged: vX.Y.Z** means an `upgrade-prepare` is ready to apply. Select
  Queue, then **Apply a prepared upgrade**, to fire it.
- Yellow **command: status** is an in-flight command. Pending commands show an
  **×** to cancel them. Cancelling works only while the status is `pending`:
  once the courier picks a command up, the runner owns it.

### Lab command history

The command history is reached through the **Lab Command History** button on
**Admin → Monitoring → Lab Sync Status**. It has no sidebar entry of its own;
migration `5.5.25.sql` removed it while keeping the page and its privileges.

The page lists the 200 most recent commands across all labs with filters for
lab, command, status and date range. Select **Details** on any row to see the
full result JSON, including exit codes, output tails and staged versions.

## Gated apply (risky releases)

For a release that needs human approval before applying:

1. Queue `upgrade-prepare` on the affected labs.
2. Each lab downloads, extracts and validates in the background over the next
   few hours. This phase causes no downtime.
3. When a lab is ready, its row on Lab Sync Status shows **Staged: vX.Y.Z**.
4. Queue **Apply a prepared upgrade** on two or three pilot labs, selecting the
   staged `commandId` from the dropdown.
5. Watch the pilots through the command history for a day or two.
6. If the pilots are healthy, queue the apply on the rest.

An apply refuses to fire unless the referenced `dependsOn` is a `prepared` row
for that same lab, so a stale staging cannot be applied by accident.

## Troubleshooting

### The Queue button does not appear

The account lacks the `Queue Lab Command` privilege. An administrator must add
the `/admin/monitoring/queue-lis-command.php` privilege to that role.

### A command sits at `pending` forever

The lab most likely has `remote_commands_enabled` set to something other than
`yes`. The courier never polls, so STS never learns the command was seen. Either
turn the flag back on at the lab, or cancel the command on STS.

### A command reaches `picked` and stalls

The courier pulled it but has not reported back. For non-root commands, check
`/var/log/apache2/error.log` or the LIS cron log for exceptions. For root
commands, check `/var/log/intelis-runner/runner-*.log` and
`systemctl status intelis-runner.service`.

### An upgrade reaches `prepared` and waits

This is the expected resting state. A prepared upgrade is staged and waits for
an explicit apply; it does not continue on its own. Queue **Apply a prepared
upgrade** for that lab, selecting the staged entry.

A command that has not yet reached the lab at all, rather than one sitting at
`prepared`, may still be inside its **Not before** window, which STS enforces by
withholding the command until that timestamp passes.

### Rolling back a bad upgrade

The apply phase always takes a hardlink snapshot at
`/var/intelis-rollback/<timestamp>/<basename>/` before rsyncing the new tree. If
the smoke check fails, the runner restores the snapshot automatically and
reports `failed` back to STS.

To roll back deliberately, queue the **Roll back to the pre-upgrade snapshot**
command from STS, or run the updater's own rollback on the machine:

```bash
sudo intelis-update -p /var/www/intelis --rollback
```

Use the updater rather than restoring the snapshot by hand. The snapshot is
taken with `public/uploads/`, `public/files/`, `public/temporary/`, `var/` and
`vendor/` excluded, so a manual `rsync -a --delete` from the snapshot over the
installation deletes the lab's uploads and runtime data and leaves no usable
`vendor/` directory. The updater applies the same exclusions on the way back and
reinstalls dependencies.

Rollback restores code only. Database migrations already applied are not
reversed, so confirm the restored version can still run against the current
schema before returning the lab to service.

Then fix the underlying issue, prepare the corrected version, and try again.

## Safety invariants

- `sudo intelis-update` with no flags always works exactly as it did before the
  remote plane. The default operator flow is unchanged.
- Every new flag on `intelis-update` is opt-in.
- Commands are whitelisted in both the LIS courier and the root runner. Unknown
  command names fail closed.
- Nonces prevent a command from running twice.
- Rows past `expires_at` sweep to `expired` on every pending-commands request.
- `flock` on the runner prevents overlapping ticks.
