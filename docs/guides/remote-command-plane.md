# Remote Command Plane — Operator Runbook

> Admin-facing guide. For the architectural plan and the trust model, see
> [remote-command-plane.md](../remote-command-plane.md) in the repo root of docs.

This guide is for operators who manage STS and the labs connected to it.
It covers how to enable remote commands on a lab, queue common commands,
monitor their progress, and roll back if something misbehaves.

## What is it?

STS can queue **commands** — "resend results from the last 45 days",
"refresh cache", "upgrade" — for any connected LIS. The LIS pulls the
queue on its normal 5-minute sync tick and executes commands locally.
Root-privileged commands (like upgrades) run through a systemd-timed
runner. Nothing pushes into the LAN from the cloud — commands are pulled
by LIS, which preserves the usual one-way security model.

Commands in the whitelist:

| Command            | Runs as       | What it does                                  |
|--------------------|---------------|-----------------------------------------------|
| `resend-results`   | www-data PHP  | Re-runs `results-sender.php` with optional module + days filter |
| `resend-requests`  | www-data PHP  | Re-runs `requests-receiver.php` with optional module + manifest |
| `metadata-resync`  | www-data PHP  | Force metadata sync from STS + lab metadata send |
| `refresh-cache`    | www-data PHP  | Clears the file cache (optional tag filter) |
| `rotate-token`     | www-data PHP  | Drops + re-fetches the STS bearer token |
| `refresh-perms`    | root runner   | `intelis-refresh -p <lis> -m full` |
| `restart-apache`   | root runner   | `apache2ctl -k graceful` |
| `upgrade`          | root runner   | Prepare + auto-apply in the next quiet window |
| `upgrade-prepare`  | root runner   | Download + extract + validate; does not apply |
| `upgrade-apply`    | root runner   | Apply a previously prepared upgrade |

## Enabling remote commands on a lab

By default the whole channel is off. To opt a lab in, set three
`global_config` values on that lab's LIS DB:

```sql
INSERT INTO global_config (name, value) VALUES
  ('remote_commands_enabled', 'yes'),     -- master switch for the courier
  ('allow_remote_upgrade',    'yes'),     -- per-lab kill switch for root commands
  ('remote_upgrade_window',   '02:00-05:00')  -- optional; when apply may run (local time)
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

The next scheduled LIS upgrade (or a fresh `sudo intelis-update`)
installs the privileged runner + its systemd timer automatically — no
separate bootstrap step.

Verify the install:

```bash
systemctl status intelis-runner.timer        # active (waiting)
systemctl list-timers | grep intelis         # shows next fire time
sudo tail -f /var/log/intelis-runner/runner-*.log
```

Disable again at any time:

- Flip `global_config.remote_commands_enabled` to anything non-truthy →
  courier stops polling the pending-commands endpoint. Queued commands
  on STS just sit at `pending` until you turn it back on or cancel them.
- Flip `global_config.allow_remote_upgrade` to `no` → courier drops a
  `var/remote-commands/disabled` flag file; the runner refuses all root
  commands. Non-root commands (resends, cache refresh) still work.

## Queueing a command from STS

1. Go to **Admin → Monitoring → Lab Sync Status**.
2. Find the lab's row. Click the **Queue** button.
3. Pick a command from the dropdown; the modal shows only the fields
   that command needs:
   - **Resend results / Resend requests:** optional module (VL, EID,
     etc.) + optional "last N days". Leave both blank to only send
     unsynced records.
   - **Upgrade / Upgrade-prepare:** optional `Not before` time. The
     apply phase also waits for the lab's `remote_upgrade_window`.
   - **Upgrade-apply:** pick from the dropdown of staged upgrades for
     this lab (populated only if a prior `upgrade-prepare` completed).
4. Click **Queue command**. The row's badge updates within a few seconds
   showing `pending`. Within ~5 minutes, the LIS picks it up.

Bulk rollout: prepare on many labs first, then apply on pilots, then
apply on the rest. See "Gated apply" below.

## Monitoring

### Lab Sync Status page

Each row shows badges for that lab:

- Blue **Staged: vX.Y.Z** — an `upgrade-prepare` is ready to apply. Click
  Queue → `Apply a prepared upgrade` to fire it.
- Yellow **command: status** — an in-flight command. Pending commands
  show an **×** you can click to cancel (only works while status is
  `pending` — once the courier picks it up the runner owns it).

### Lab Command History page

**Admin → Monitoring → Lab Command History** lists the 200 most recent
commands across all labs with filters for lab, command, status, and
date range. Click **Details** on any row to see the full result JSON
(exit codes, output tails, staged versions, etc.).

## Gated apply (risky releases)

For a release that needs human approval before applying:

1. Queue `upgrade-prepare` on the affected labs.
2. Each lab downloads + extracts + validates in the background over the
   next few hours. Zero downtime for this phase.
3. When a lab is ready, the row on Lab Sync Status shows **Staged: vX.Y.Z**.
4. Queue `upgrade-apply` on 2–3 pilot labs, selecting the staged
   `commandId` from the dropdown.
5. Watch the pilots via Lab Command History for a day or two.
6. If OK, queue `upgrade-apply` on the rest.

`upgrade-apply` refuses to fire if the referenced `dependsOn` isn't a
`prepared` row for that same lab, so you can't accidentally apply a
stale staging.

## Troubleshooting

### "Queue" button doesn't appear
You don't have the `Queue Lab Command` privilege. Ask an admin to add
the `/admin/monitoring/queue-lis-command.php` privilege to your role.

### Command sits at `pending` forever
Likely the lab has `remote_commands_enabled = no` (or unset). The LIS
courier never polls, so STS never learns the command was "seen".
Options: turn the flag on at the lab, or cancel the command on STS.

### Command gets to `picked` then stalls
Means the courier pulled it but hasn't reported back yet. For non-root
commands, check `/var/log/apache2/error.log` or the LIS cron log for
exceptions. For root commands, check `/var/log/intelis-runner/runner-*.log`
and `systemctl status intelis-runner.service`.

### Upgrade gets to `prepared` and waits
Either `remote_upgrade_window` is configured and we're outside it (the
runner will fire when the window opens, assuming the machine is on), or
the command's `not_before` hasn't arrived yet. Check the details pane
of the row on Lab Command History.

### Rolling back a bad upgrade
The apply phase always takes a hardlink snapshot at
`/var/intelis-rollback/<timestamp>/<basename>/` before rsyncing the
new tree. If the smoke check fails, the runner restores the snapshot
automatically and reports `failed` back to STS. Manual rollback:

```bash
sudo systemctl stop apache2
sudo rsync -a --delete /var/intelis-rollback/<ts>/<basename>/ /var/www/<basename>/
sudo systemctl start apache2
```

Then fix the underlying issue, prepare the corrected version, and try
again.

## Safety invariants

- `sudo intelis-update` with no flags always works exactly as it did
  before the remote plane. Default operator flow is unchanged.
- Every new flag on `intelis-update` is opt-in.
- Commands are whitelisted in both the LIS courier and the root runner.
  Unknown command names fail closed.
- Nonces prevent a command from running twice.
- `expires_at` rows auto-sweep to `expired` on every pending-commands
  request.
- `flock` on the runner prevents overlapping ticks.
