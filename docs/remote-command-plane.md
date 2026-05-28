# Remote Command Plane — Plan

STS queues commands for LIS instances; LIS pulls them on its existing `sync-sts` tick; non-privileged commands run in PHP as www-data, root-privileged commands are dispatched to a local systemd-timed runner.

## 1. Architecture

```text
  STS (cloud)                       LIS (LAN)
  ────────────                      ─────────
  [Admin UI] ──queues──> s_lis_remote_commands
                         ▲
                         │ bearer-authed POST
                         │ (existing sync-sts chain)
                         │
               ┌─────────┴─────────┐
               │  LIS www-data PHP │  courier
               │  (pending-        │
               │   commands.php)   │
               └─────────┬─────────┘
                         │ dispatches
             ┌───────────┴───────────┐
             │                       │
      [in-PHP as www-data]     [marker file on disk]
      resend-results,          ┌──────────┴──────────┐
      metadata-resync, ...     │ root systemd timer  │  executor
                               │ (intelis-runner)    │
                               └──────────┬──────────┘
                                          │
                                 intelis-update,
                                 intelis-refresh, ...
```

Three trust boundaries. Each layer trusts the layer below to do strictly less than it is capable of.

## 2. Command dictionary

| Command           | Runs as      | Uses                                                          |
| ----------------- | ------------ | ------------------------------------------------------------- |
| `resend-results`  | www-data PHP | `app/tasks/remote/results-sender.php [module] <days>`         |
| `resend-requests` | www-data PHP | `app/tasks/remote/requests-receiver.php`                      |
| `metadata-resync` | www-data PHP | `composer run metadata-sync --force`                          |
| `refresh-cache`   | www-data PHP | clear `var/cache`, invalidate file cache                      |
| `rotate-token`    | www-data PHP | drop STS token, re-fetch                                      |
| `upgrade`         | root runner  | prepare + apply back-to-back                                  |
| `upgrade-prepare` | root runner  | prepare only, stop at `READY`                                 |
| `upgrade-apply`   | root runner  | apply a previously prepared tree (by `commandId`)             |
| `refresh-perms`   | root runner  | `intelis-refresh -p <path> -m full`                           |
| `restart-apache`  | root runner  | `systemctl reload apache2`                                    |

Default path is www-data in-PHP. The root runner is reserved for the narrow set of ops that genuinely need root.

## 3. Data model

### STS side (new table)

```sql
s_lis_remote_commands (
  command_id       CHAR(26) PK,           -- ULID
  lab_id           INT,
  command          VARCHAR(32),           -- whitelist only
  params           JSON,                  -- e.g. {"module":"vl","days":45}
  status           ENUM('pending','picked','running','preparing','prepared',
                        'applying','completed','failed','expired','cancelled'),
  requested_by     INT,                   -- user_id
  requested_at     DATETIME,
  picked_at        DATETIME NULL,
  completed_at     DATETIME NULL,
  not_before       DATETIME NULL,
  expires_at       DATETIME NULL,
  depends_on       CHAR(26) NULL,         -- e.g. upgrade-apply depends on an upgrade-prepare
  result           JSON NULL,             -- exit code, log tail, new version, etc.
  last_error       TEXT NULL,
  nonce            CHAR(26),              -- anti-replay, runner enforces
  INDEX (lab_id, status)
)
```

Same migration runs on LIS. On LIS the table is dormant — same idiom as other STS-only tables. No special-casing.

### LIS side (filesystem)

No new DB tables. Filesystem is the privilege-crossing boundary:

```text
var/remote-commands/
  pending/<commandId>.json     # www-data writes, runner reads
  results/<commandId>.json     # runner writes, www-data reads
  nonces.db                    # runner-local anti-replay
```

Perms:

- `pending/` — www-data:www-data, 0770, runner reads + deletes after processing
- `results/` — root:www-data, 0770, runner writes, www-data reads + deletes after reporting
- Staging — `/var/intelis-staging/<commandId>/` — root:root, 0700

## 4. Transport — reuse, don't reinvent

New LIS task script `app/tasks/remote/pending-commands.php`:

- Same shape as `results-sender.php` / `requests-receiver.php`
- Uses existing bearer token via `$general->getSTSToken()` + `$apiService->setBearerToken()`
- POSTs to `$remoteURL/remote/v2/pending-commands.php` with:

  ```json
  {
    "labId": "...",
    "instanceId": "...",
    "currentVersion": "VERSION",
    "statusUpdates": [
      {"commandId": "...", "status": "...", "result": {}, "completedAt": "..."}
    ]
  }
  ```

- Response: `{commands: [{commandId, command, params, nonce}, ...]}`
- Tracked via `$general->addApiTracking(...)` like every other endpoint
- Wired into `composer.json` → `sync-sts` chain, after `receive-requests`

New STS endpoint `app/remote/v2/pending-commands.php`:

- Same auth shape as `v2/requests.php` / `v2/results.php`
- Bearer validated, reads request via `AppRegistry::get('request')` + `$apiService->getJsonFromRequest`
- Applies `statusUpdates` to matching rows (only if nonce matches, only if row is in a non-terminal state)
- Returns oldest eligible `pending` row(s) for this lab — marks them `picked`, stamps `picked_at`
- Eligibility filter: `status='pending'` AND (`not_before` IS NULL OR `not_before` <= NOW()) AND (`expires_at` IS NULL OR `expires_at` > NOW()) AND dependencies satisfied

Status round-trip is piggybacked on the same call. No separate endpoint. STS is eventually-consistent with the runner's local `results/<id>.json` (max lag = one sync tick ~ 5 min).

## 5. Upgrade flow — minimal downtime

Split into **prepare** (no downtime) and **apply** (the short critical window).

### Phase A — Prepare (runs whenever, idempotent, resumable)

When the runner picks up `upgrade` or `upgrade-prepare`:

1. `mkdir -p /var/intelis-staging/<commandId>/`
2. **Parallel downloads** with per-job logs:

   ```bash
   ( wget master.tar.gz && verify && tar -xz ) > master.log 2>&1 &
   mpid=$!
   ( wget vendor.tar.gz && wget vendor.tar.gz.md5 && verify && tar -xz ) > vendor.log 2>&1 &
   vpid=$!
   wait $mpid || fail "master download failed"
   wait $vpid || fail "vendor download failed"
   ```

3. Each artifact is verified independently. On restart, the runner skips whichever is already present + valid.
4. `composer validate` against the extracted tree
5. Write `READY` sentinel
6. Write `result.json` with `status: prepared, version: X.Y.Z`
7. For `upgrade` — proceed directly to Phase B
8. For `upgrade-prepare` — stop here, runner exits

Prepare can take 5–30 min on slow links with **zero user impact** — the live LIS keeps serving.

### Phase B — Apply (short downtime, target < 60s)

Starts only when:

- `READY` sentinel exists
- `global_config.allow_remote_upgrade = true` (kill switch)
- Command's `not_before` (if any) has passed — STS withholds the command until then
- No in-flight upgrade for this instance

Steps:

1. Take a hardlink snapshot of current tree → `/var/intelis-rollback/<commandId>/` (via `rsync -a --link-dest`)
2. Drop Apache maintenance conf → returns 503 with `Retry-After`
3. rsync staging tree → `lis_path` (local FS, fast)
4. rsync pre-extracted vendor → `lis_path/vendor`
5. `composer dump-autoload -o`
6. Run migrations + `run-once` scripts
7. Remove `var/cache/CompiledContainer.php`, clear file cache
8. Smoke check: `curl -f http://localhost/smoke.php` with expected response
9. If smoke fails → rollback from snapshot, raise `failed`
10. Remove maintenance conf, `apache2ctl -k graceful`
11. Write `result.json` with `status: completed, version: new VERSION`
12. Clean up `/var/intelis-staging/<commandId>/` after N days

### Gated-apply flow (risky releases)

Operator workflow for a version that needs approval:

1. STS admin queues `upgrade-prepare` on all affected labs
2. Labs prepare over the following day(s) — status transitions to `prepared` as each finishes
3. Admin UI shows which labs are `prepared` + the staged version
4. Operator queues `upgrade-apply` on 2 pilot labs, picking from the `prepared` list
5. `upgrade-apply` carries `depends_on = <prepare commandId>` and refuses if the matching `READY` sentinel is absent (prevents applying a stale staged tree from an older release)
6. Watch the pilots. If OK → queue `upgrade-apply` on the rest.

## 6. Safety rails

- **Kill switch:** `global_config.allow_remote_upgrade` (bool). Lab can opt out at any time.
- **Scheduled apply:** `not_before` on the command row — STS gates delivery to the lab until that timestamp.
- **Command whitelist:** runner has a hardcoded dispatch map. Unknown command → `failed: unknown command`. Never interprets shell strings from marker files.
- **Nonce enforcement:** runner tracks processed nonces in `var/remote-commands/nonces.db`. Refuses re-runs.
- **Expiry:** commands with `expires_at < now` never fire; status moves to `expired` on next tick.
- **Single-flight:** only one upgrade operation in-flight per instance.
- **Smoke check:** runs before removing maintenance mode. Failure → rollback.
- **Dependency gating:** `upgrade-apply` refuses without matching `READY` sentinel from its `depends_on` command.
- **Params validation:** each dispatch handler validates its own params before executing. No `shell_exec` on raw user input.

## 7. Privileged runner

- Root-owned executable at `/usr/local/bin/intelis-runner` (bash or PHP, TBD during implementation)
- Installed by `scripts/setup.sh` / `scripts/upgrade.sh`
- Triggered by systemd timer every 60s (`intelis-runner.timer` + `intelis-runner.service`)
- Loops over `var/remote-commands/pending/*.json` in lex order
- Hardcoded dispatch map — whitelist only:

  ```bash
  case "upgrade":         upgrade_prepare; upgrade_apply
  case "upgrade-prepare": upgrade_prepare
  case "upgrade-apply":   upgrade_apply
  case "refresh-perms":   intelis-refresh ...
  case "restart-apache":  systemctl reload apache2
  default: fail "unknown command"
  ```

- Writes `var/remote-commands/results/<commandId>.json` throughout
- Logs to `var/logs/runner-<date>.log`
- Does no network I/O for authorization — trusts that www-data wrote the marker only because STS told it to; the trust chain stops at the PHP layer

## 8. File layout

### LIS side (new)

- `app/tasks/remote/pending-commands.php` — courier
- `app/tasks/remote/command-handlers/` — www-data in-PHP handlers
  - `resend-results.php`, `resend-requests.php`, `metadata-resync.php`, `refresh-cache.php`, `rotate-token.php`
- `scripts/intelis-runner` — root executor
- `scripts/install-runner.sh` — systemd unit + timer installer
- `var/remote-commands/{pending,results}/` — created by setup
- `scripts/upgrade.sh` — gains `-P / --prepare-only` and `-A / --apply-prepared <staging-dir>` flags
- `composer.json` — add `pending-commands` to `sync-sts` chain

### STS side (new)

- `app/remote/v2/pending-commands.php` — endpoint
- `app/admin/monitoring/queue-lis-command.php` — admin action
- UI additions on `app/admin/monitoring/sync-status.php` — "Queue command" modal per lab
- Migration: `s_lis_remote_commands` table (runs on both sides; dormant on LIS)

## 9. Build order

Each step ships value on its own; each is testable without the next.

1. **Refactor `scripts/upgrade.sh` into `prepare_phase()` + `apply_phase()`.** Default invocation (`sudo intelis-update`) runs both back-to-back — manual UX unchanged. Add `--prepare-only` and `--apply-prepared <dir>` flags. Add rollback snapshot via `rsync -a --link-dest`. Add Apache maintenance-mode drop-in during apply. Parallel downloads of master + vendor. **Ships standalone benefit to every lab: shorter downtime, cleaner failure modes, no remote-command plumbing involved.**
2. **STS: table + admin UI** to queue `resend-results`. Safest remote command. Verify schema end-to-end.
3. **LIS: courier** (`pending-commands.php`) + `composer.json` wiring. Gated behind `global_config.remote_commands_enabled` (default false). Still only `resend-results`. Prove pull/execute/report loop.
4. **LIS: more www-data handlers** — `metadata-resync`, `refresh-cache`, `rotate-token`, `resend-requests`. No new infrastructure.
5. **Root runner + systemd timer** — dispatch the simpler root commands first (`refresh-perms`, `restart-apache`).
6. **Upgrade through the runner** — `upgrade`, `upgrade-prepare`, `upgrade-apply`. Runner just invokes `intelis-update --prepare-only` and `--apply-prepared <dir>` from step 1. Smoke check. Nonce enforcement.
7. **Safety rails** — kill switch, `not_before`, `expires_at`, dependency gating.
8. **Gated-apply UI** — admin UI shows `prepared` labs with staged version; operator picks from that list to queue `upgrade-apply`.

Step 1 is the critical one and pure win — it benefits 100% of existing manual users even if remote commands never ship. Steps 2–4 deliver a remote-control plane for non-root operations. Steps 5+ extend it to root-privileged ops. Stop at any point and what shipped still works.

**Backward compat invariant:** `sudo intelis-update` with no flags, same interactive prompts as today. Forever. Every new flag is opt-in. Every new code path is gated.
