# Maintenance Scripts

InteLIS ships with several CLI tools for system health, database management, and cleanup. This page covers the key maintenance scripts and their usage.

---

## Service Guard

A systemd watchdog that checks Apache and MySQL every 60 seconds and restarts them if unresponsive.

```bash
# Install
sudo ./scripts/service-guard.sh

# Uninstall
sudo ./scripts/service-guard.sh --uninstall
```

**How it works:**

- Auto-detects `apache2`/`httpd` and `mysql`/`mariadb`
- Validates Apache config with `apachectl -t` and probes `http://127.0.0.1/`
- Pings MySQL with `mysqladmin ping`
- Adds restart policies (up to 10 restarts within 2 minutes)

```bash
# Check status
systemctl status service-guard.timer

# View logs
journalctl -u service-guard.service -n 100 --no-pager
```

---

## Resource Monitor

A systemd timer that monitors memory, disk, CPU, and load every 2 minutes. Takes emergency action at critical thresholds.

```bash
# Install
sudo ./scripts/resource-monitor.sh

# Uninstall
sudo ./scripts/resource-monitor.sh --uninstall
```

**Thresholds:**

| Resource | Warning | Critical |
|----------|---------|----------|
| Memory   | 80%     | 90%      |
| Disk     | 85%     | 95%      |

**Emergency actions at critical level:**

- **Memory** — Clears system caches
- **Disk `/`** — Purges old journal entries, cleans package cache
- **Disk `/var`** — Deletes archived logs (30+ days)
- **Disk `/tmp`** — Removes temp files older than 3 days

```bash
# Check status
systemctl status resource-monitor.timer

# View logs
journalctl -u resource-monitor.service -n 50 --no-pager
```

---

## Database Tools (db-tools)

A CLI utility for database backup, restore, maintenance, and diagnostics.

```bash
php bin/db-tools.php <command> [options]
```

**Commands:**

| Command | Description |
|---------|-------------|
| `backup [target]` | Create encrypted backup (default) |
| `restore [file]` | Restore from backup (interactive selection) |
| `export <target> [file]` | Export as plain SQL |
| `import <target> [file]` | Import SQL file (`.sql`, `.gz`, `.zst`, `.zip`) |
| `list` | List available backups |
| `verify [file]` | Verify backup integrity |
| `clean` | Delete old backups (`--keep=N` or `--days=N`) |
| `size [target]` | Show database size breakdown |
| `maintain [target]` | Run mysqlcheck + binlog purge |
| `purge-binlogs [--days=N]` | Clean old binary logs (default: 7 days) |
| `collation` | Launch collation conversion utility |

**Targets:** `intelis` (default), `interfacing`, `both`/`all`

**Examples:**

```bash
php bin/db-tools.php backup all
php bin/db-tools.php clean --days=30
php bin/db-tools.php maintain all
php bin/db-tools.php size
php bin/db-tools.php restore
```

**Automated schedules:**

| Task | Schedule |
|------|----------|
| Backup | Every 6 hours |
| Binlog purge | Daily at 04:05 |
| Weekly maintenance | Sundays at 03:00 |
| Monthly optimization | 1st of month at 04:00 |

---

## Cleanup

### cleanup.php

Cleans database backups, temporary files, logs, and old database records.

```bash
php bin/cleanup.php [DAYS]
```

`DAYS` sets the retention period (default: 30). Runs automatically at 00:45 daily.

**File system cleanup:**

| Directory | Max Age | Max Size |
|-----------|---------|----------|
| `var/logs/` | — | 1 GB |
| `var/temporary/` | 3 days | 500 MB |
| `var/track-api/requests/` | 120 days | 1 GB |
| `var/track-api/responses/` | 120 days | 1 GB |

**Database cleanup** — Deletes records older than 365 days from `activity_log`, `user_login_history`, and `track_api_requests`.

### cleanup-logs.sh

Shell script for managing log files by size with safe in-place truncation.

```bash
./scripts/cleanup-logs.sh [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--dir DIR` | `var/logs/` | Directory to clean |
| `--max-size MB` | 500 | Truncate files larger than this |
| `--keep-tail MB` | 5 | Keep last N MB when truncating |
| `--max-total MB` | 10240 | Max total size of all matched logs |
| `--keep N` | 30 | Always keep N most recent files |
| `-n` | — | Dry run |
| `-v` | — | Verbose |

---

## System Scanner

Displays a full overview of the InteLIS instance — configuration, connectivity, and sync status.

```bash
php bin/scan.php
```

**Output includes:**

- Instance type (LIS or STS), version, and SmartConnect URL
- Database configuration (sensitive values are masked)
- System settings (debug mode, caching, interfacing)
- Sync status for all services (LIS instances only)
- Health checks (database, disk, system requirements)

---

## Scheduled Tasks

InteLIS uses [Crunz](https://github.com/lavary/crunz) for task scheduling. All definitions are in `sys/cron/ScheduledTasks.php`.

**Setup** — Add a single cron entry:

```bash
* * * * * cd /var/www/intelis && ./vendor/bin/crunz schedule:run
```

**Core tasks:**

| Task | Schedule |
|------|----------|
| DB Backup | Every 6 hours |
| Binlog Purge | Daily 04:05 |
| Weekly Maintenance | Sundays 03:00 |
| Monthly Optimization | 1st of month 04:00 |
| Config Backup | Sundays 03:00 |
| File Cleanup | Daily 00:45 |
| Archive Audit Tables | Every 6 hours |
| Generate Sample Codes | Every minute |
| Update Sample Status | Daily 00:05 |

**Conditional tasks** (run only when the feature is enabled):

| Task | Schedule | Condition |
|------|----------|-----------|
| SQLite-to-MySQL Sync | Every 5 min | Interfacing enabled |
| Interface Import | Every minute | Interfacing enabled |
| STS Sync | Every 5 min | STS URL configured |
| SmartConnect Metadata | Every 20 min | SmartConnect configured |
| SmartConnect VL/EID/COVID | Every 25–35 min | Module + SmartConnect |
| TB Referrals | Every minute | TB module active |

Each task uses `preventOverlapping()` to prevent concurrent execution. A heartbeat file at `var/.cron_heartbeat` is updated on every scheduler run.

```bash
# Verify cron is running
ls -la /var/www/intelis/var/.cron_heartbeat
```
