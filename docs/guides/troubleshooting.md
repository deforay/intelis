---
description: Match what is going wrong on an InteLIS machine to the command that diagnoses it and the page that fixes it.
audience: [system-admin, lab-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.78
---
# Where to Start

Find the symptom in the table, run its command on the InteLIS machine, and
follow the page it points to. Each command reports what it found and prints
the next command to run.

| Symptom | Run first | Then read |
| --- | --- | --- |
| The site will not open in the browser | `sudo intelis doctor` | [Browser Shows PHP Code](browser-shows-php-code.md) |
| The browser shows PHP code instead of InteLIS | `sudo intelis doctor` | [Browser Shows PHP Code](browser-shows-php-code.md) |
| MySQL is stopped, or the site reports a database error | `sudo intelis fix-database` | [MySQL Will Not Start](mysql-will-not-start.md) |
| An update stops at `Running database migrations...` | Nothing yet. Read the page first. | [Update Stuck at Database Migrations](update-stuck-at-migrations.md) |
| InteLIS fails to start after an update or a server change | `sudo intelis check` | The fix it prints for each failure |
| `Permission denied` in the browser or a log | `sudo intelis check` | [Permission Denied Issue](permission-denied-issue.md) |
| `Illegal mix of collations` in the browser or a log | `sudo intelis check` | [Fix Collation Mismatch](fix-collation-issue.md) |
| The lab was never connected to the STS, or the STS address changed | `sudo intelis sts-setup` | [Connect a Lab to the STS](connect-lab-to-sts.md) |
| Results or requests are not reaching the STS | `sudo intelis check` | [Results Not Reaching the STS](sts-sync-not-working.md) |
| Analyzer results are not arriving in InteLIS | `sudo intelis interface` | [Analyzer Results Not Arriving](interfacing-results-not-arriving.md) |
| The site is slow, or the disk is nearly full | `sudo intelis health` | [Free disk space](maintenance.md#free-disk-space) |
| Backups are not running, or are old | `sudo intelis backup status` | [Setting Up Off-Machine Backups](setting-up-off-machine-backups.md) |
| Scheduled work stopped: no imports, no sync, no backups | `sudo crontab -l \| grep cron.sh` | [Check the scheduled tasks are running](maintenance.md#check-the-scheduled-tasks-are-running) |

!!! warning "Do not reinstall or reformat the machine"

    The data is almost always intact. A reinstall is the one step that can turn
    a fixable fault into lost data.

## What each command checks

The four diagnostic commands change nothing unless they ask first.

| Command | What it checks | When to use it |
| --- | --- | --- |
| `sudo intelis check` | PHP, the web server's PHP settings, `vendor/`, the configuration file, writable paths, the database, and pending migrations. Prints the command that fixes each failure. | InteLIS will not start, or after any change to the server. |
| `sudo intelis health` | Disk usage, MySQL response time, writable paths, and off-machine backups. | InteLIS runs, but something seems wrong. |
| `sudo intelis doctor` | Why the site will not open: the web server, PHP, and the files it serves and who may read them. Offers each safe repair. Runs `fix-database` itself when MySQL is at fault. | The site will not open. |
| `sudo intelis fix-database` | Why MySQL will not start: disk space, memory, edited configuration files, the data folder owner, and damaged data files. Offers each safe repair. | MySQL is stopped. |

`doctor` and `fix-database` accept `--check` to report without repairing.
[Maintenance scripts](maintenance.md#intelis-commands) lists every `intelis`
command.

## Before asking for help

Collect these and send them together:

1. The output of `sudo intelis check`.
2. The output of `sudo intelis health`.
3. The report the doctor saved, if it ran. It is `site-report.txt` or
   `mysql-report.txt` on the Desktop, or in the home folder when there is no
   Desktop. Passwords are removed from it.
4. The newest file in `/var/www/intelis/var/logs`.
