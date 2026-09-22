---
description: Diagnose and repair a MySQL server that will not start, using the InteLIS database doctor or by hand.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# MySQL Will Not Start

MySQL refuses to start, so InteLIS cannot open and nothing can be backed up.

1. Open a terminal on the InteLIS machine and run the database doctor:

    ```bash
    sudo intelis fix-database
    ```

    ??? info "If `intelis` is not recognised, or `fix-database` does not exist"

        The install is older. Run the same tool straight from the internet:

        ```bash
        sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/mysql-doctor.sh)"
        ```

        Type it exactly as shown. Piping the download into `bash` instead
        feeds the script its own text as the answers to its questions.

2. Answer its questions. It asks before each repair, and starts MySQL again
   after a repair.
3. Wait for `The database is running again.` If MySQL was already running,
   the doctor reports what it fixed instead.

    ??? failure "If it reports `It still will not start.`"

        The doctor puts `mysql-report.txt` on the Desktop, or in the home
        folder when there is no Desktop. Send that file when asking for help.
        It holds the service log, the database error log, memory, disk and
        settings. Passwords are removed from it.

!!! warning "Do not reinstall or reformat the machine"

    The data is almost always intact. A reinstall is the one step that can turn
    a fixable fault into lost data.

## Causes the doctor checks

The doctor checks each cause below. Open one to repair it by hand.

??? info "The disk is full"

    `df -h /` shows less than 500 MB free. MySQL stops when it cannot write.
    The doctor offers to trim the system logs itself. It also reports a disk that has space left but no room for more files:
    `df -i /` shows 95% or more.

    1. Trim the system logs:

        ```bash
        sudo journalctl --vacuum-size=100M
        ```

    2. Start MySQL:

        ```bash
        sudo systemctl start mysql
        ```

    Old database backups in `/var/www/intelis/backups/db` are usually the
    largest files on the disk. They end in `.sql.zst.gpg`, `.sql.zst` or
    `.sql.gz`. Copy them to a USB drive before deleting any, and keep the
    newest one on the machine.

??? info "The machine ran out of memory"

    The system stopped MySQL to free memory. `sudo journalctl -k` mentions
    `Out of memory` or `oom-kill`. The doctor also checks
    `innodb_buffer_pool_size`. If it is set above 70% of the machine's memory,
    the doctor offers to switch that setting off, which returns MySQL to its
    default size.

    Close other programs, then start MySQL:

    ```bash
    sudo systemctl start mysql
    ```

    If it happens again, the machine needs more memory.

??? info "A configuration file was edited"

    MySQL rejects a setting in a file under `/etc/mysql`. The doctor quotes the
    rejected line. If an earlier working copy of the configuration exists, the
    doctor offers to put it back. Otherwise, undo the edit by hand, then start
    MySQL:

    ```bash
    sudo systemctl start mysql
    ```

??? info "The data folder has the wrong owner"

    This usually follows a copy or a restore run as the administrator. MySQL
    cannot open files it does not own. `ls -ld /var/lib/mysql` shows an owner
    other than `mysql`.

    1. Give the files back to MySQL:

        ```bash
        sudo chown -R mysql:mysql /var/lib/mysql
        ```

    2. Start MySQL:

        ```bash
        sudo systemctl start mysql
        ```

??? info "The data files are damaged"

    This usually follows a power cut or an unclean shutdown. The error log
    mentions corruption, a checksum or InnoDB recovery. The doctor does not
    repair this, because the wrong setting makes the damage permanent.

    Send `mysql-report.txt` and ask for help. Do not change
    `innodb_force_recovery`. If `/var/www/intelis/backups/db` holds a recent
    backup, restoring it is usually faster and safer than repairing the files.
    See [Restoring from a backup](restoring-from-backup.md).

The doctor also checks for a blocked MySQL service, a missing connection folder,
settings placed under the wrong heading in a MySQL configuration file (the
doctor switches them off), another program on the MySQL port, and a wrong
database password saved in InteLIS.

## Last resort: copy the disk

If MySQL cannot be started at all, the data can still be recovered on another
machine. Shut the machine down and copy these folders off its disk:

- `/var/lib/mysql` (the database files)
- `/etc/mysql` (the MySQL settings)
- `/var/www/intelis` (the InteLIS installation)

Take the copy before anyone reinstalls the operating system.
