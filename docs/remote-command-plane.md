---
description: How the STS queues commands that labs pull over their outbound sync, and the layers, switches and checks that keep them safe.
audience: [system-admin, developer]
module: [all]
type: explanation
platform: any
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# About the Remote Command Plane

Why the STS can run commands on a lab without ever connecting into the lab's
network, and how the pieces keep that safe. For the operator steps, see
[Running commands on a lab from the STS](guides/remote-command-plane.md).

## Why the lab pulls

Labs sit on LANs behind NAT and firewalls that nobody at the STS controls. The
STS cannot open a connection to a lab, and a design that needed it would also
need an inbound port on every lab. The existing sync already runs the other
way: each LIS calls the STS on its `sync-sts` tick, every 5 minutes. The command
plane rides that same outbound call. The STS only ever answers a lab's request.
Nothing is pushed into the LAN.

The cost is latency. A command waits up to one sync tick before the lab sees it,
and its result waits up to another tick before the STS sees it. For resends,
cache refreshes and upgrades, minutes do not matter. A push channel would buy
seconds at the price of an inbound attack surface on every lab.

## Three layers, three levels of trust

```text
  STS (cloud)                          LIS (lab LAN)
  -----------                          -------------
  Lab Sync Status ──queues──> s_lis_remote_commands
                                   ▲
                                   │ bearer-authenticated POST
                                   │ (sync-sts chain, every 5 minutes)
                                   │
                        ┌──────────┴──────────┐
                        │ courier (www-data)  │
                        │ pending-commands.php│
                        └──────────┬──────────┘
                                   │
                 ┌─────────────────┴─────────────────┐
                 │                                   │
        in-process handler                  marker file on disk
        (www-data PHP)                  var/remote-commands/pending/
                                                     │
                                          ┌──────────┴──────────┐
                                          │ runner (root)       │
                                          │ systemd timer, 60 s │
                                          └──────────┬──────────┘
                                                     │
                                     intelis-update, intelis-refresh,
                                     apache2ctl
```

Each layer trusts the layer above it to ask for less than the layer below can
do.

- **The STS** stores commands and hands them out. It never reaches the lab.
- **The courier** is `app/tasks/remote/pending-commands.php`, running as
  www-data in the `sync-sts` composer chain. It reports results, collects new
  commands, runs the safe ones in-process, and drops a marker file for anything
  that needs root.
- **The runner** is `scripts/intelis-runner.sh`, installed as
  `/usr/local/bin/intelis-runner` and fired every 60 seconds by
  `intelis-runner.timer`. It reads marker files and runs a fixed set of root
  operations. It does no network I/O and never runs a string from a marker as a
  shell command.

The filesystem is the privilege boundary between the courier and the runner.
www-data can ask for a root operation only by naming it in a marker file, and
the runner decides whether that name means anything.

## Commands and where they run

| Command | Runs as | What runs |
| --- | --- | --- |
| `ping` | www-data | `command-handlers/ping.php`. No side effects. |
| `resend-results` | www-data | `command-handlers/resend-results.php`, which runs `results-sender.php` with the module and days filters. |
| `resend-requests` | www-data | `command-handlers/resend-requests.php`, which runs `requests-receiver.php` with the module filter. |
| `metadata-resync` | www-data | `command-handlers/metadata-resync.php`, which runs `sts-metadata-receiver.php -f` and `lab-metadata-sender.php -f`. |
| `refresh-cache` | www-data | `command-handlers/refresh-cache.php`. Clears the file cache, or only the given tags. |
| `rotate-token` | www-data | `command-handlers/rotate-token.php`. Drops the STS token and fetches a new one. |
| `refresh-perms` | root | `intelis-refresh -p <path> -m full` |
| `restart-apache` | root | `apache2ctl -k graceful`, or `systemctl reload apache2` without `apache2ctl` |
| `upgrade` | root | `intelis-update -p <path> -s`, with `-M` when the maintenance page is requested |
| `upgrade-prepare` | root | `intelis-update -p <path> --prepare-only -s` |
| `upgrade-apply` | root | `intelis-update -p <path> --apply-prepared <staging-dir> -s`, with `-M` when requested |
| `rollback` | root | `intelis-update -p <path> --rollback` |

The handlers live under `app/tasks/remote/command-handlers/`. www-data is the
default. A command goes to the runner only when it needs root.

`resend-requests` also accepts a manifest code, but the queue form has no field
for it. `refresh-cache` accepts tags, and the form sends none, so a queued
refresh clears the whole cache.

The command set is listed in four places, and they must agree:

- the queue endpoint's whitelist, `app/admin/monitoring/queue-lis-command.php`
- the courier's `$inProcessHandlers` and `$rootRunnerCommands`
- the runner's `case` dispatch
- the `<select>` in `app/admin/monitoring/sync-status.php`

The runbook carries an operator-facing list of the same commands.

## What each lab says it can do

The STS never guesses what a lab can run. On every poll the courier reports a
capability object: `commandPlane: true` and a `supports` list. The STS stores it
in `facility_details.facility_attributes`, and `LabCapabilityService` grades the
lab into one of three tiers.

- **full**: a capability report arrived in the last 24 hours. The STS offers
  exactly the commands in `supports`.
- **basic**: no fresh report, but the lab polled in the last 24 hours. This is a
  courier from before capability reporting. The STS offers only the in-process
  commands.
- **none**: neither signal is fresh. Queueing is off.

The courier lists the root commands in `supports` only when the lab allows them.
That takes three things: `allow_remote_upgrade` is on, the database is on the
same machine, and the machine has systemd. `upgrade.sh` manages MySQL and
restarts services. On a machine where the database lives elsewhere, as in a
compose stack, it fails after minutes of real work. It is better not to offer
the command at all.

The queue form greys out what the lab did not report, and the queue endpoint
checks the same tiers again. A stale page or a hand-built request cannot queue
a command the lab never offered.

## The two switches

Two `global_config` rows on the lab turn the plane on and off.

- `remote_commands_enabled` gates the courier. When off, the courier exits
  before it polls. The lab sends no heartbeat and no capability report, so after
  24 hours the STS stops offering the lab at all.
- `allow_remote_upgrade` gates root commands. The courier mirrors it into a
  `var/remote-commands/disabled` flag file, because the runner has no database
  access. The courier also writes the flag when the database is on another
  host or the machine has no systemd. While the flag exists, the runner fails
  every marker with `runner disabled on this instance`.

The flag file is a second lock, not the only one. The courier also stops
advertising root commands, so the STS stops offering them.

Both rows are seeded as `yes` with `remote_sync_needed = 'yes'`. That flag means
the STS's own values travel to every lab with the metadata sync. A forced
metadata sync copies the STS value over whatever the lab set locally. There is
no durable per-lab setting yet.

## Upgrades in two phases

An upgrade has a slow part and a dangerous part, and they are different parts.
Downloading and unpacking a release takes minutes on a slow link but touches
nothing live. Replacing the code is quick but is the moment users can see
errors. `upgrade.sh` splits them.

**Prepare** (`--prepare-only`) creates `/var/intelis-staging/<timestamp>-<pid>/`.
It downloads the release and the vendor bundle in parallel, checks the vendor
bundle's SHA-256, extracts both and runs `composer validate`. It writes a
`READY` sentinel with the staged version last, so a half-finished staging never
looks complete. The live application keeps serving throughout. Only the newest
staging directory survives a run.

**Apply** (`--apply-prepared <dir>`) does the short part:

1. It takes a hardlink snapshot of the installation under
   `/var/intelis-rollback/<timestamp>/<basename>/`.
2. It turns on the maintenance page when asked.
3. It syncs the staged code and vendor over the installation.
4. It runs migrations and the run-once scripts.
5. It runs a smoke check before users are let back in.

What happens on failure depends on the step. A failed code sync or smoke check
restores the snapshot. A failed database check or migration leaves the new code
in place, because the old code cannot safely serve a half-migrated schema
either. The maintenance page, if on, stays on.

The one-step `upgrade` command runs both phases back to back. The staged route
exists so that an operator can prepare every lab days ahead, apply on a few
pilots, and only then apply everywhere.

### Why an apply points at a prepare

`upgrade-apply` carries `depends_on`, the ID of the `upgrade-prepare` it
installs. The queue endpoint accepts it only if that row is `prepared` and
belongs to the same lab. The STS withholds it until the prepare row is
`prepared` or `completed`. On the lab, the runner looks up the staging
directory from its own `prepared/` record and refuses without a `READY`
sentinel. Every layer checks, so an apply can never install a stale staging or
another lab's release.

After a successful apply the runner deletes its `prepared/` record. Nothing
moves the STS row out of `prepared`, so the **Staged** badge outlives the
apply.

## Rollback restores code only

The snapshot leaves out `public/uploads/`, `public/files/`,
`public/temporary/`, `var/`, `vendor/` and the legacy data folders. The apply
never rewrites them, and snapshotting them costs time on large labs. The
restore uses the same exclusions, so its `--delete` cannot remove uploads made
after the snapshot. It then rebuilds `vendor/` from the restored
`composer.lock`.

A rollback does not reverse migrations. Migrations only run forward, so the
restored release runs against a schema already migrated past it. That is
usually survivable and sometimes not. The queue form and `upgrade.sh` both say
so before the rollback runs, rather than presenting it as an undo.

`--rollback` takes the most recent snapshot for the installation, and
`upgrade.sh` keeps three by default. If the last upgrade ran with
`--no-snapshot`, the most recent snapshot comes from an earlier upgrade, and a
rollback goes back more than one release.

## Why commands run once

A command crosses the network twice and the filesystem twice, and any of those
steps can repeat. Four mechanisms stop a repeat from running twice.

- **Nonces.** Every command carries a nonce. After running a command, the runner
  records its nonce under `var/remote-commands/processed-nonces/` and discards
  any later marker with the same nonce. It prunes records after 30 days.
- **Result files.** The courier writes each status to
  `var/remote-commands/results/<commandId>.json` and deletes it only after the
  STS acknowledges it. A command that already has a result file is not run
  again.
- **flock.** The runner holds `/var/lock/intelis-runner.lock`, so two timer
  ticks never overlap.
- **One in flight.** The queue endpoint refuses a command while the same command
  is in flight for the same lab.

Status updates only move rows that are not already terminal, and only when the
nonce matches.

## Timing and expiry

`not_before` holds a command on the STS until that time. The lab never sees it
early, so a scheduled upgrade needs no clock or window on the lab. There is no
global quiet window.

`expires_at` is enforced. On each poll the STS moves rows past their deadline
to `expired`, and never hands one out. The queue form does not set it yet.

A command picked up more than 2 hours ago that has not reported a terminal
state is marked `failed` as stale. This stops a crashed handler from showing an
in-flight badge forever. `prepared` is exempt, because a staged release can
wait for days. A long upgrade that finishes after the cutoff cannot change the
row back, because terminal rows are not overwritten.

## Data model

The STS keeps one table, `s_lis_remote_commands`. The same migration creates it
on every LIS, where it stays empty.

| Column | Purpose |
| --- | --- |
| `command_id` | Primary key. |
| `lab_id` | The lab the command is for. |
| `command` | A whitelisted command name. |
| `params` | JSON, such as `{"module":"vl","days":45}`. |
| `status` | `pending`, `picked`, `running`, `preparing`, `prepared`, `applying`, `completed`, `failed`, `expired` or `cancelled`. The runner writes `running`, not `preparing` or `applying`. |
| `requested_by`, `requested_at` | Who queued it, and when. |
| `picked_at`, `picked_by_instance` | When a lab took it, and which installation. |
| `completed_at` | When it reached a terminal state. |
| `not_before`, `expires_at` | Delivery window. |
| `depends_on` | The `upgrade-prepare` an `upgrade-apply` installs. |
| `result`, `last_error` | Exit code, output tail and staged version. |
| `nonce` | Anti-replay token. |

On the lab, state lives in files under `var/remote-commands/`:

| Path | Owner | Purpose |
| --- | --- | --- |
| `pending/` | www-data | Markers for the runner. |
| `results/` | runner and courier | Statuses waiting to go to the STS. |
| `prepared/` | root | The runner's record of each staged release. |
| `processed-nonces/` | root | Nonces already run. |
| `disabled` | courier | Present when root commands are not allowed: `allow_remote_upgrade` is off, the database is on another host, or the machine has no systemd. |
| `courier.heartbeat`, `runner.heartbeat` | courier, runner | Shown as the chips in the **Command plane** column. |

## How it was built

The plane was built in steps, each useful without the next.

1. `upgrade.sh` was split into prepare and apply, with a snapshot and a
   maintenance page. Every manual upgrade gained shorter downtime before any
   remote plumbing existed. `sudo intelis-update` with no flags still runs both
   phases with the same prompts. Every flag added since is opt-in.
2. The STS table and queue form came next, with `resend-results` only.
3. The courier followed, then the other in-process commands.
4. The root runner and its systemd timer started with `refresh-perms` and
   `restart-apache`, then took on upgrades.
5. Capability reporting, `not_before`, `depends_on` and the staged apply
   completed it. `rollback` was added last, as a way back that had until then
   only run automatically when an apply failed.

Migrations record the path:

- **5.4.3** added the **Queue Lab Command** privilege.
- **5.4.4** added **Cancel Lab Command**, **Lab Command History** and a sidebar
  entry for the history page.
- **5.5.2** seeded `remote_commands_enabled` as `yes`. The courier had been gated
  on a row that did not exist, so every lab read it as off and the **Queue**
  button stayed disabled everywhere.
- **5.5.3** seeded `allow_remote_upgrade` as `yes`.
- **5.5.25** removed the history page's sidebar entry. The page and its
  privileges stay, reached through **Lab Command History** on **Lab Sync
  Status**.

Both seeds use `INSERT IGNORE`, so a lab that had already set `no` keeps it.
