---
description: Reference for intelis commands, scheduled tasks, retention rules and server maintenance scripts, with steps for freeing disk space and restarting services.
audience: [system-admin]
module: [all]
type: reference
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Maintenance Scripts and Tools

Commands, scheduled tasks and scripts for keeping an InteLIS server running.

Every command on this page runs in a terminal on the InteLIS server. The
installation directory is `/var/www/intelis`. On older installs it is
`/var/www/vlsm`.

---

## `intelis` commands

`intelis` works from any directory. A command that needs administrator rights
asks for the password.

| Command | What it does | When to use it |
| --- | --- | --- |
| `intelis` | Shows a numbered menu of the most common commands. Outside a terminal, prints the help instead. | Any time the exact command is not known. |
| `intelis help` | Prints the list of commands. | To look up a command. |
| `intelis update` | Downloads the current updater and updates InteLIS to the current release. Options are passed to the updater, for example `-s` to skip Ubuntu system updates. | To install a new release. See [Updating InteLIS on Ubuntu](updating-intelis-on-ubuntu.md). |
| `intelis backup` | Saves a fresh copy of the database and settings on this machine, then sends it to the off-machine destination. Offers to set up a destination if none exists. | Before an update, a move, or any risky change. |
| `intelis backup status` | Shows when the last off-machine backup ran and whether it worked. | To confirm backups are working. |
| `intelis backup test` | Checks the backup destination. Changes nothing. | After changing the destination, or when `backup status` reports a failure. |
| `intelis backup setup` | Chooses or changes where backups are sent: another Linux machine, a Windows shared folder, or a USB drive. | Once per machine, or when the destination changes. |
| `intelis backup disable` | Stops the scheduled off-machine backups. | Before taking the destination offline. |
| `intelis backup enable` | Starts the scheduled off-machine backups again. | After `backup disable`. |
| `intelis restore` | Connects to the backup destination, lists the labs stored there, and copies the chosen backup back. On a working install it restores the database in place. `--list` only lists the labs. | To recover from a failed machine or lost data. See [Restoring from a Backup](restoring-from-backup.md). |
| `intelis check` | Checks PHP, the web server's PHP settings, `vendor/`, the configuration file, writable paths and the database. Prints the command that fixes each failure. `--quiet` prints only warnings and failures. Also runs as `intelis preflight`. | When InteLIS will not start, and after any change to the server. |
| `intelis health` | Checks disk usage, MySQL response time, writable paths and off-machine backups. | To see whether a running server is well right now. |
| `intelis doctor` | Finds out why InteLIS will not open in the browser and offers each safe repair. Runs `fix-database` itself when the database is at fault. `--check` only reports. `--yes` applies every safe repair without asking. `--quiet` prints only the verdict and the report path. | When the site will not open. See [Browser Shows PHP Code Instead of InteLIS](browser-shows-php-code.md). |
| `intelis fix-database` | Finds out why MySQL is down and offers each safe repair. Takes the same options as `doctor`. | When MySQL will not start. See [MySQL Will Not Start](mysql-will-not-start.md). |
| `intelis interface` | Imports the results the Interfacing Tool is holding. | To import results now instead of waiting for the scheduled import. See [Analyzer Results Not Arriving](interfacing-results-not-arriving.md). |
| `intelis interface setup` | Creates the interfacing database and the account the Interfacing Tool connects with, and writes the settings. | Once, when connecting the Interfacing Tool. See [Connect an Instrument to InteLIS](setting-up-interfacing-tool.md). |
| `intelis interface migrate` | Brings the interfacing database up to date with the Interfacing Tool's own migrations. | When `intelis check` or support asks for it. |
| `intelis provision` | Creates the directories InteLIS writes to and fixes their owner and permissions. | When `intelis check` reports a path that is missing or not writable. |
| `intelis migrate` | Runs the database migrations. | When `intelis check` reports pending migrations. |
| `intelis install` | Installs the PHP dependencies in `vendor/`. | When `intelis check` reports `vendor/` missing or out of date. |
| `intelis purge-cache` | Clears the application cache. | After a configuration change that does not show up. |
| `intelis token` | Generates or refreshes the STS API token. Does nothing on an STS. | When `intelis check` reports a missing token. |
| `intelis audit-triggers-install` | Installs or regenerates the audit triggers. | When `intelis check` reports missing audit triggers. |
| `intelis db:collation` | Converts the database tables and columns to the `utf8mb4` collation. | When `intelis check` reports a collation problem. See [Fix illegal or mismatched collation errors](fix-collation-issue.md). |
| `intelis composer <args>` | Runs Composer with the given arguments in the installation directory. | For Composer's own commands, such as `intelis composer update`. |
| `intelis <script>` | Runs any other Composer script, for example `intelis housekeeping` or `intelis scan`. | To run a script listed in `composer.json`. |

`doctor` also answers to `fix-site`, `fix-web`, `site`, `web`, `apache` and
`php`. `fix-database` also answers to `fix-db`, `database`, `db`, `mysql` and
`mysql-doctor`.

`doctor` and `fix-database` write a full report to
`/var/log/intelis-doctor-<timestamp>.log` and
`/var/log/intelis-mysql-doctor-<timestamp>.log`. When run with `sudo` by an
ordinary user, they also copy the report to that user's home folder as
`site-report.txt` or `mysql-report.txt`. Passwords are removed from the copy.

On a machine without the `intelis` command, run the doctor straight from GitHub:

```bash
sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
```

---

## Free disk space

Use these steps when the disk is full or `intelis health` reports high disk usage.

1. Check which disk is full:

    ```bash
    df -h
    ```

2. Go to the installation directory:

    ```bash
    cd /var/www/intelis
    ```

3. Run housekeeping now:

    ```bash
    sudo -u www-data php bin/housekeeping.php 30
    ```

    Housekeeping runs in the background and writes its progress to
    `var/logs/housekeeping-<date>-<time>.log`. To keep less, use a smaller
    number of days, for example `7`. The number applies to database backups
    and application logs. See [Housekeeping retention](#housekeeping-retention).

4. Delete application logs older than 7 days:

    ```bash
    sudo -u www-data php bin/clear-logs.php --days=7
    ```

5. Delete MySQL binary logs older than 3 days:

    ```bash
    sudo -u www-data php vendor/bin/db-tools purge-binlogs --days=3
    ```

6. Delete system journal entries older than 7 days:

    ```bash
    sudo journalctl --vacuum-time=7d
    ```

7. Delete the downloaded package files:

    ```bash
    sudo apt-get clean
    ```

8. Check the disk again:

    ```bash
    df -h
    ```

    The `Use%` of the full disk is now lower. If it is still above 90%, list
    the largest folders and send the output to support:

    ```bash
    sudo du -xh --max-depth=2 /var | sort -h | tail -20
    ```

---

## Restart stuck services

Use these steps when InteLIS pages hang or show a database connection error.

1. Restart Apache:

    ```bash
    sudo systemctl restart apache2
    ```

2. Restart MySQL:

    ```bash
    sudo systemctl restart mysql
    ```

    On a MariaDB server, use `mariadb` in place of `mysql`.

3. Check both services are running:

    ```bash
    systemctl is-active apache2 mysql
    ```

    The output is `active` twice. If MySQL does not start, run
    `sudo intelis fix-database`. If both are active and the site still does not
    open, run `sudo intelis doctor`.

---

## Check the scheduled tasks are running

Use these steps when sample codes stop being generated, or sync and backups
stop.

1. Check the heartbeat file:

    ```bash
    ls -l /var/www/intelis/var/.cron_heartbeat
    ```

    The scheduler updates this file every minute. If its time is within the
    last two minutes, the scheduler is running and nothing more is needed.

2. Check the scheduler entry in root's crontab:

    ```bash
    sudo crontab -l | grep cron.sh
    ```

    The output is:

    ```text
    * * * * * cd /var/www/intelis && ./cron.sh
    ```

    If the line is missing or starts with `#`, run `intelis update`. The update
    adds the line back.

3. Check for a pause marker:

    ```bash
    ls -l /var/www/intelis/var/cron-paused
    ```

    `cron.sh` skips every run while this file exists. `intelis update` creates
    it during database migrations and deletes it afterwards. The file expires
    after 30 minutes. If no update is running, delete it:

    ```bash
    sudo rm /var/www/intelis/var/cron-paused
    ```

4. Wait one minute, then repeat step 1. The heartbeat time is now current.

---

## Restore a database backup on the same machine

Use these steps to return the database on this machine to an earlier local
backup. To restore on a new machine, see
[Migrating From One Ubuntu Machine to Another](migrating-ubuntu-machines.md).

1. Go to the installation directory:

    ```bash
    cd /var/www/intelis
    ```

2. Start the restore:

    ```bash
    sudo -u www-data php vendor/bin/db-tools restore
    ```

3. Choose the backup from the list. The newest is first.

    To restore a known file instead, give its path:

    ```bash
    sudo -u www-data php vendor/bin/db-tools restore backups/db/vlsm-20260908-060002-<32 characters>.sql.zst.gpg
    ```

    Before restoring, db-tools backs up the current database to `backups/db/`.
    The name of that backup starts with `pre-restore-`.

4. Apply the database updates:

    ```bash
    intelis migrate
    ```

5. Log in to InteLIS and check the newest samples are the ones expected.

Do not rename a backup file. The 32 characters in its name are part of its
decryption key. The date and time in the name are in UTC. When backup
encryption with an STS-held key is on, the name has no random part, and the
key comes from the STS.

---

## Reference

### Scheduled tasks

The scheduler runs from root's crontab every minute through `cron.sh`, which
runs [Crunz](https://github.com/crunzphp/crunz). The tasks are defined in
`sys/cron/ScheduledTasks.php`. Times are in the instance's time zone. A task
does not start while its previous run is still going.

| Task | Command | Schedule | Runs when |
| --- | --- | --- | --- |
| Archive audit tables | `app/tasks/archive-audit-tables.php` | Every 6 hours (00:00, 06:00, 12:00, 18:00) | Always |
| Bundle audit trail | `app/tasks/bundle-audit-trail.php` | Daily 05:15 | Always |
| Generate sample codes | `bin/sample-code-generator.php` | Every minute | Always |
| Database backup | `db-tools backup --all` | Every 6 hours (00:00, 06:00, 12:00, 18:00) | Always |
| Binary log purge | `db-tools purge-binlogs --days=7` | Daily 04:05 | Always |
| Weekly database maintenance | `db-tools maintain --all --days=7` | Sundays 03:00 | Always |
| Monthly database optimization | `db-tools maintain --all --optimize --days=7` | 1st of the month 04:00 | Always |
| Config backup | `bin/backup-configs.php` | Sundays 03:00 | Always |
| Housekeeping | `bin/housekeeping.php 30` | Daily 00:45 | Always |
| Health checks | `bin/health.php` | Hourly, on the hour | Always |
| Update sample status | `bin/update-sample-status.php` | Daily 00:05 | Always |
| Flag data issues | `bin/flag-data-issues.php` | Daily 00:25 | Always |
| VL result interpretation | `bin/update-vl-suppression.php` | Every minute | Always |
| Prune remote commands | `bin/prune-remote-commands.php` | Daily 03:15 | Always |
| Interface SQLite to MySQL sync | `bin/sync-interface-sqlite-mysql.php` | Every 5 minutes | Interfacing enabled |
| Interface import | `bin/interface.php` | Every minute | Interfacing enabled |
| STS sync | `composer run sync-sts` | Every 5 minutes | LIS only, with an STS URL set |
| SmartConnect metadata | `bin/smart-connect/sync.php metadata` | Every 20 minutes | SmartConnect URL set |
| SmartConnect VL | `bin/smart-connect/sync.php vl` | At minutes 0, 25 and 50 | SmartConnect URL set, VL module on |
| SmartConnect EID | `bin/smart-connect/sync.php eid` | Every 30 minutes | SmartConnect URL set, EID module on |
| SmartConnect COVID-19 | `bin/smart-connect/sync.php covid19` | At minutes 0 and 35 | SmartConnect URL set, COVID-19 module on |
| Inter-lab referrals | `bin/referrals.php` | Every minute | STS only |
| Apply rejection reason map | `bin/apply-rejection-reason-map.php --quiet` | Daily 03:40 | STS only |

`bin/health.php` raises an admin alert only when a check changes state.

The scheduler updates `var/.cron_heartbeat` on every run. While
`var/cron-paused` exists and is less than 30 minutes old, `cron.sh` runs no
tasks.

### Other timers

These run outside the scheduler.

| Timer | Schedule | Installed by |
| --- | --- | --- |
| Off-machine backup (`/usr/local/bin/intelis-backup.sh`, root crontab) | Every 8 hours (00:00, 08:00, 16:00) and at boot | `intelis backup setup` |
| Remote command runner (`intelis-runner.timer`) | Every 60 seconds | `intelis update` |
| Service guard (`service-guard.timer`) | Every 60 seconds | `scripts/service-guard.sh` |
| Resource monitor (`resource-monitor.timer`) | Every 120 seconds | `scripts/resource-monitor.sh` |

### Housekeeping retention

`bin/housekeeping.php [DAYS]` runs daily at 00:45 with `DAYS` set to 30.

| Data | Location | Kept |
| --- | --- | --- |
| Database backups | `backups/db/` | `DAYS` days. The `INTELIS_DB_BACKUP_DAYS` environment variable overrides the days. `INTELIS_DB_BACKUP_KEEP` keeps a number of backups instead. |
| Application logs | `var/logs/` | `DAYS` days. Above 1,000 MB, the oldest files are deleted until the folder is at 80% of the limit. |
| Temporary files | `public/temporary/` | 3 days. Above 500 MB, the oldest files are deleted until the folder is at 80% of the limit. |
| Temporary files | `var/temporary/` | 3 days. Above 500 MB, the oldest files are deleted until the folder is at 80% of the limit. |
| API request and response bodies | `var/track-api/requests/`, `var/track-api/responses/` | 30 days |
| Activity log | `activity_log` table | 365 days |
| Login history | `user_login_history` table | 365 days |
| API request records | `track_api_requests` table | 365 days |
| Page usage | `user_page_usage` table | 365 days |
| Completed sample code queue entries | `queue_sample_code_generation` table | 90 days. Unprocessed entries are never deleted. |

Housekeeping never deletes `.htaccess`, `index.php`, `.gitkeep` or `.hgkeep`.

Other retention rules:

| Data | Kept | Set by |
| --- | --- | --- |
| Database backups | Newest 7 per database, applied after each backup | `retention` in `db-tools.php` |
| Finished remote commands (`s_lis_remote_commands`) | 90 days | `remote_command_retention_days` in global config, pruned daily at 03:15 |

### Scripts

Run each script from the installation directory with `sudo`.

| Script | What it does | Command |
| --- | --- | --- |
| `scripts/service-guard.sh` | Installs a systemd timer that restarts Apache and MySQL when they stop responding. `--uninstall` removes it. | `sudo bash scripts/service-guard.sh` |
| `scripts/resource-monitor.sh` | Installs a systemd timer that checks memory, disk, CPU and load, and deletes files when a limit is critical. `--uninstall` removes it. | `sudo bash scripts/resource-monitor.sh` |
| `scripts/cleanup-logs.sh` | Truncates large log files in place and deletes the oldest when the total is too large. | `sudo bash scripts/cleanup-logs.sh -n` |
| `scripts/intelis-doctor.sh` | Runs behind `intelis doctor`. | `sudo intelis doctor` |
| `scripts/mysql-doctor.sh` | Runs behind `intelis fix-database`. | `sudo intelis fix-database` |
| `scripts/remote-backup.sh` | Runs behind `intelis backup setup`. | `intelis backup setup` |
| `scripts/restore-backup.sh` | Runs behind `intelis restore`. | `intelis restore` |
| `scripts/upgrade.sh` | Runs behind `intelis update`. | `intelis update` |
| `scripts/bootstrap.sh` | Installs the current `intelis` command and updater from GitHub. Run once on a machine where `intelis update` does not work. | `sudo bash scripts/bootstrap.sh` |
| `scripts/default-host-setup.sh` | Points Apache's default site at the installation. `-p PATH` sets the installation, `-n NAME` the server name. | `sudo bash scripts/default-host-setup.sh -p /var/www/intelis` |

PHP maintenance scripts run as `www-data`:

| Script | What it does | Command |
| --- | --- | --- |
| `bin/scan.php` | Prints the instance type, version, configuration with secrets masked, sync status and the `health.php` report. | `sudo -u www-data php bin/scan.php` |
| `bin/health.php` | Checks disk, MySQL response time, writable paths and off-machine backups. Exits non-zero on a critical result. | `sudo -u www-data php bin/health.php` |
| `bin/preflight.php` | Runs behind `intelis check`. | `sudo -u www-data php bin/preflight.php` |
| `bin/housekeeping.php` | Deletes old backups, files and database rows. See [Housekeeping retention](#housekeeping-retention). | `sudo -u www-data php bin/housekeeping.php 30` |
| `bin/clear-logs.php` | Deletes log files from `var/logs/`. `--keep=N` keeps the newest N, `--days=N` keeps the last N days, `--all` deletes all, `--dry-run` only reports. | `sudo -u www-data php bin/clear-logs.php --days=7` |
| `bin/duplicate-indexes.php` | Lists indexes that duplicate another index on the same table. `--fix` drops the duplicates. | `sudo -u www-data php bin/duplicate-indexes.php` |

All commands above assume `cd /var/www/intelis` first.

### Resource monitor

| Resource | Warning | Critical |
| --- | --- | --- |
| Memory | 80% | 90% |
| Disk | 85% | 95% |
| Load | | Above 2.0 per CPU core |

The monitor checks `/`, `/var`, `/tmp` and `/home` when each is a separate
mount. It logs processes above 50% CPU. Warnings and alerts go to the system
journal.

At a critical level it deletes these without asking:

| Trigger | Action |
| --- | --- |
| Memory at 90% | Clears the system caches |
| Disk `/` at 95% | Deletes journal entries older than 7 days and the package cache |
| Disk `/var` at 95% | Deletes `*.log.*.gz` files older than 30 days and `*.log.*` files older than 7 days under `/var/log` |
| Disk `/tmp` at 95% | Deletes files older than 3 days under `/tmp` |

Do not install the monitor on a machine that keeps files of value under
`/var/log` or `/tmp`.

```bash
systemctl status resource-monitor.timer
journalctl -u resource-monitor.service -n 50 --no-pager
```

### Service guard

Every 60 seconds the guard:

- starts `apache2`, `httpd`, `mysql` or `mariadb` when it is installed and not running
- restarts Apache when `apachectl -t` fails or `http://127.0.0.1/` does not answer within 3 seconds
- restarts MySQL when `mysqladmin ping` fails

It also sets Apache and MySQL to restart whenever they stop, up to 10 times
within 2 minutes.

```bash
systemctl status service-guard.timer
journalctl -u service-guard.service -n 100 --no-pager
```

### cleanup-logs.sh options

| Option | Description | Default |
| --- | --- | --- |
| `--dir DIR` | Directory to clean | `var/logs/` |
| `--pattern GLOB` | File names to match | `*-logfile.log` |
| `--max-size MB` | Truncate files larger than this | `500` |
| `--keep-tail MB` | Keep the last N MB of a truncated file. `0` empties it. | `5` |
| `--max-total MB` | Delete the oldest files when the total passes this | `10240` |
| `--keep N` | Always keep the N newest files | `30` |
| `-n`, `--dry-run` | Report only. Change nothing. | Off |
| `-v`, `--verbose` | Log each file | Off |

The script writes its output to `/var/log/intelis-log-cleanup.log`, not to the
screen.

### db-tools commands

Run from the installation directory:

```bash
sudo -u www-data php vendor/bin/db-tools <command> [options]
```

| Command | Description |
| --- | --- |
| `backup [database]` | Creates an encrypted backup. `--all` covers every profile. `--no-encrypt` skips encryption. |
| `restore [file]` | Restores a backup. Without a file, lists the backups to choose from. Saves a `pre-restore-` backup first. |
| `export <output> [database]` | Exports the database as plain SQL. The output file comes first. |
| `import <file> [database]` | Imports a `.sql`, `.sql.gz`, `.sql.zst`, `.sql.gpg` or `.zip` file. The file comes first. |
| `show` | Lists the backups. |
| `verify <file or folder>` | Checks a backup, or every backup in a folder, can be read. |
| `clean` | Deletes old backups. `--keep=N` keeps the newest N. `--days=N` deletes those older than N days. |
| `size [database]` | Shows the database size and the largest tables. `--top=N` sets the number of tables. |
| `maintain [database]` | Runs `mysqlcheck` and purges binary logs. `--optimize` also optimizes the tables. `--all` covers every profile. |
| `purge-binlogs` | Deletes MySQL binary logs older than `--days=N`. Default: 7. |
| `collation` | Converts tables and columns to the `utf8mb4` collation. |

The profiles are `intelis` (default) and `interfacing`. `interfacing` exists
only when interfacing is enabled. `--all` is the only way to act on both. A
profile name given where a database name is expected is read as a database
name.

Backups are saved in `backups/db/` as
`vlsm-YYYYMMDD-HHMMSS-<32 characters>.sql.zst.gpg` and
`interfacing-YYYYMMDD-HHMMSS-<32 characters>.sql.zst.gpg`. When backup
encryption with an STS-held key is on, the name has no random part, and the
key comes from the STS.

```bash
cd /var/www/intelis
sudo -u www-data php vendor/bin/db-tools backup --all
sudo -u www-data php vendor/bin/db-tools size
sudo -u www-data php vendor/bin/db-tools clean --days=30
```
