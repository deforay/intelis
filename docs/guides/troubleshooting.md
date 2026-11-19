---
description: Match what is going wrong, in the lab or on the InteLIS machine, to the page that fixes it.
audience: [lab-staff, lab-supervisor, lab-admin, system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-26
reviewed_against: 5.8.3
---
# Where to start

Find the problem in one of the two tables below. Each row points to the page
that fixes it.

- [Problems while using InteLIS](#problems-while-using-intelis): a sample,
  a result, a manifest or a form in the browser.
- [Problems with the machine](#problems-with-the-machine): the site will not
  open, MySQL, an update, backups or the analyzer connection.

## Problems while using InteLIS

| Problem | Read |
| --- | --- |
| Sign-in fails, or asks for the text in an image | **If sign-in fails** in [Sign in and navigate InteLIS](../user-guides/signing-in.md) |
| A password is forgotten | Ask the administrator to set a new one. If no administrator can sign in, see [Forgotten password](forgotten-password.md) |
| The sidebar is empty, or a menu or button is missing | The role lacks the permission. Ask the administrator. See [Manage users and roles](../user-guides/admin-users-and-roles.md) |
| A facility is missing from the request form | **If the facility is missing from the list** in [Register a viral load test request](../user-guides/register-a-request.md) |
| A sample cannot be found | [Find a sample that is not in a batch or on a manifest](../user-guides/signing-in.md#find-a-sample-that-is-not-in-a-batch-or-on-a-manifest) |
| A **Needs attention** box shows above the request list | [Act on the Needs attention box](../user-guides/signing-in.md#act-on-the-needs-attention-box) |
| A sample is missing from the list when making a batch | **If a sample is missing from the list** in [Batch samples for testing](../user-guides/batch-samples.md) |
| A sample is missing from the list when making a manifest | **If a sample is missing from the list** in [Send samples on a manifest](../user-guides/send-samples-on-a-manifest.md) |
| **Edit** is greyed out on a manifest | **If Edit is greyed out** in [Send samples on a manifest](../user-guides/send-samples-on-a-manifest.md) |
| A message shows instead of the samples when receiving a manifest | **If a message appears instead of the samples** in [Receive samples sent on a manifest](../user-guides/receive-referred-samples.md) |
| Analyzer results do not appear in InteLIS | **If results do not arrive** in [Capture viral load results](../user-guides/capture-results.md), then [Analyzer results not arriving](interfacing-results-not-arriving.md) |
| Imported results show **Awaiting Approval** | **If the results show Awaiting Approval** in [Capture viral load results](../user-guides/capture-results.md) |
| A sample failed on the analyzer, or is on hold | [Handle failed and held samples](../user-guides/failed-and-held-samples.md) |
| A result shows **Locked** and cannot be corrected | **If the row shows Locked** in [Review and approve results](../user-guides/approve-results.md) |
| A rejected sample is missing from the email list | **If a rejected sample is missing from the list** in [Release results](../user-guides/release-results.md) |
| The facility cannot see an approved result | [Results not reaching the STS](sts-sync-not-working.md) |
| A status name is unclear | [Sample statuses](../user-guides/sample-statuses.md) |
| A word in these guides is unclear | [Words used in these guides](../user-guides/glossary.md) |

## Problems with the machine

Run the command on the InteLIS machine, then follow the page it points to.
Each command reports what it found and prints the next command to run.

| Symptom | Run first | Then read |
| --- | --- | --- |
| The site will not open in the browser | `sudo intelis doctor` | [Site will not open](site-will-not-open.md) |
| The browser shows `The InteLIS database is not responding`, or `System Temporarily Unavailable` on older versions | `sudo intelis doctor` | [Site will not open](site-will-not-open.md) |
| The browser shows `This server's PHP cannot reach the database` | `sudo intelis doctor` | [Site will not open](site-will-not-open.md) |
| The browser shows `InteLIS is being updated` for more than 30 minutes | `sudo intelis doctor` | [Site will not open](site-will-not-open.md) |
| phpMyAdmin shows `The mysqli extension is missing` | `sudo intelis doctor` | [Site will not open](site-will-not-open.md) |
| The browser shows PHP code instead of InteLIS | `sudo intelis doctor` | [Browser shows PHP code](browser-shows-php-code.md) |
| A user, or the only admin, cannot sign in because the password is forgotten | `sudo intelis reset-password` | [Forgotten password](forgotten-password.md) |
| MySQL is stopped, or the site reports a database error | `sudo intelis fix-database` | [MySQL will not start](mysql-will-not-start.md) |
| An update stops at `Running database migrations...` | Nothing yet. Read the page first. | [Update stuck at database migrations](update-stuck-at-migrations.md) |
| InteLIS fails to start after an update or a server change | `sudo intelis check` | The fix it prints for each failure |
| `Permission denied` in the browser or a log | `sudo intelis check` | [Permission denied errors](permission-denied-issue.md) |
| `Illegal mix of collations` in the browser or a log | `sudo intelis check` | [Fix collation errors](fix-collation-issue.md) |
| The lab was never connected to the STS, or the STS address changed | `sudo intelis sts-setup` | [Connect a lab to the STS](connect-lab-to-sts.md) |
| Results or requests are not reaching the STS | `sudo intelis check` | [Results not reaching the STS](sts-sync-not-working.md) |
| Analyzer results are not arriving in InteLIS | `sudo intelis interface` | [Analyzer results not arriving](interfacing-results-not-arriving.md) |
| The site is slow, or the disk is nearly full | `sudo intelis health` | [Free disk space](maintenance.md#free-disk-space) |
| Data or a file was lost or deleted by mistake | `sudo intelis restore` | [Restore from a backup](restoring-from-backup.md) |
| Backups are not running, or are old | `sudo intelis backup status` | [Set up off-machine backups](setting-up-off-machine-backups.md) |
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
| `sudo intelis doctor` | Why the site will not open: the web server, PHP and its extensions, and the files it serves and who may read them. Offers each safe repair. Runs `fix-database` itself when MySQL is at fault. | The site will not open. |
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
