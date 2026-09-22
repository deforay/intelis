---
description: Update an InteLIS machine on Ubuntu to the current release with the intelis command.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Updating InteLIS on Ubuntu

Update an InteLIS lab machine to the current release.

The machine must run **Ubuntu 22.04 LTS or later**, have an internet
connection, and have an account with `sudo` rights. A machine still on 22.04
should move to 24.04 LTS with
[Migrating From One Ubuntu Machine to Another](migrating-ubuntu-machines.md).

The current release requires **PHP 8.4+ (minimum 8.4.1)**. PHP 8.2, 8.3,
and older versions are not supported. Both Apache and command-line PHP must
meet this requirement. The update switches PHP to 8.4 (8.5 on Ubuntu 26.04
and later) automatically.

**Choose the situation that fits, then follow its steps from top to bottom.**
To tell which one fits, open a terminal, type `intelis` and press Enter:

- A menu headed **InteLIS** with eight numbered options means the first
  situation. Type `8` and press Enter to leave the menu.
- `command not found`, or a long list of Composer commands, means the second.

=== "`intelis` shows a menu"

    ### Before the update

    1. Tell the lab that InteLIS may not respond for a few minutes.
    2. Open a terminal on the InteLIS machine and take a fresh backup:

        ```bash
        intelis backup
        ```

        Enter the account password if asked. Wait for the line
        `Database and settings saved on this machine`.

        ??? info "If it asks `Set up off-machine backups now?`"

            Press Enter to answer No and continue with the update. Set up
            off-machine backups later with `intelis backup setup`.

        ??? failure "If it prints `The local backup did not finish`"

            Do not update. If MySQL is down, follow
            [MySQL will not start](mysql-will-not-start.md), then repeat step 2.
            Otherwise, send the whole output to support.

    ### Update

    3. Start the update:

        ```bash
        intelis update
        ```

        Enter the account password if asked.

        ??? info "If it asks for the MySQL root password"

            The configuration file holds no MySQL password. Enter the MySQL root
            password of this machine.

            If it asks for the password twice and then for the **Remote STS URL**,
            the configuration file was missing or damaged and the update is
            rebuilding it. Enter the MySQL root password twice. Then enter the
            STS address the lab uses, or press Enter if the lab has no STS.

    4. Wait. Keep the terminal window open until the last line reads
       `Total time:`.

        ??? info "If it asks `Do you want to run maintenance scripts?`"

            On some older machines, the update asks this question. Press Enter
            to answer No. After 30 seconds without an answer, the
            update continues with No.

        ??? failure "If the summary shows `Failed to update`"

            The update restores the previous code where it can. Do not repair the
            machine by hand.

            1. Run `intelis check`.
            2. Send its whole output to support, together with the newest
               `/tmp/intelis-upgrade-….log` file.

            Running `intelis update` again is safe.

    ### Check the update

    5. Read the **Post-Upgrade Check** near the end of the output. Its summary
       line (`… passed, … warning(s), … failed, … skipped`) must show
       `0 failed`.

        ??? failure "If a line starts with `WARN` or `FAIL`"

            Each line prints the command that fixes it. Type that command exactly
            as printed, then run `intelis check`. If a `FAIL` line remains, send
            the whole output of `intelis check` to support.

    6. Open InteLIS in the browser and log in.
    7. Check the page footer. It shows the version followed by a short commit
       code in brackets, for example `v5.7.72 (22928fe)`. The code matches the
       start of the second code on the `Updated commit … -> …` line of the
       update output.

        If the update printed `Already at commit`, the machine was already
        current and the footer is unchanged.

=== "`intelis` not found, or shows Composer"

    ### Before the update

    1. Tell the lab that InteLIS may not respond for a few minutes.
    2. Open a terminal on the InteLIS machine and install the current `intelis`
       and `intelis-update` commands:

        ```bash
        sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/deforay/intelis/master/scripts/bootstrap.sh)"
        ```

        Type the command exactly as shown, with `bash -c` and the quotes. Wait
        for `Done. This machine's commands are current.`

        ??? info "If `curl` is not installed"

            Use `wget` instead:

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/bootstrap.sh)"
            ```

        ??? failure "If it prints `Could not download` or `is not a script`"

            The machine cannot reach GitHub, or a network portal intercepted the
            download. Fix the internet connection, then repeat step 2.

    3. Take a fresh backup:

        ```bash
        intelis backup
        ```

        Enter the account password if asked. Wait for the line
        `Database and settings saved on this machine`.

        ??? info "If it asks `Set up off-machine backups now?`"

            Press Enter to answer No and continue with the update. Set up
            off-machine backups later with `intelis backup setup`.

        ??? failure "If it prints `The local backup did not finish`"

            If MySQL is down, follow [MySQL will not start](mysql-will-not-start.md),
            then repeat step 3.

            On an installation too old to have the backup command, export the
            database with this instead:

            ```bash
            cd ~ && wget -O db-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/db-backup.sh
            sudo bash db-backup.sh
            ```

            - Enter the MySQL username and password.
            - Choose the `vlsm` database, and `interfacing` if the lab uses the
              interfacing tool.
            - When asked for the location, enter `/var/www/intelis/backups/db`.
              On older installs, enter `/var/www/vlsm/backups/db`.
            - Wait for `Script completed.` If it prints
              `These databases were NOT backed up`, do not update.

    ### Update

    4. Start the update:

        ```bash
        intelis update
        ```

        Enter the account password if asked.

        ??? info "If it asks for the MySQL root password"

            The configuration file holds no MySQL password. Enter the MySQL root
            password of this machine.

            If it asks for the password twice and then for the **Remote STS URL**,
            the configuration file was missing or damaged and the update is
            rebuilding it. Enter the MySQL root password twice. Then enter the
            STS address the lab uses, or press Enter if the lab has no STS.

    5. Wait. Keep the terminal window open until the last line reads
       `Total time:`.

        ??? info "If it asks `Do you want to run maintenance scripts?`"

            On some older machines, the update asks this question. Press Enter
            to answer No. After 30 seconds without an answer, the
            update continues with No.

        ??? failure "If the summary shows `Failed to update`"

            The update restores the previous code where it can. Do not repair the
            machine by hand.

            1. Run `intelis check`.
            2. Send its whole output to support, together with the newest
               `/tmp/intelis-upgrade-….log` file.

            Running `intelis update` again is safe.

    ### Check the update

    6. Read the **Post-Upgrade Check** near the end of the output. Its summary
       line (`… passed, … warning(s), … failed, … skipped`) must show
       `0 failed`.

        ??? failure "If a line starts with `WARN` or `FAIL`"

            Each line prints the command that fixes it. Type that command exactly
            as printed, then run `intelis check`. If a `FAIL` line remains, send
            the whole output of `intelis check` to support.

    7. Open InteLIS in the browser and log in.
    8. Check the page footer. It shows the version followed by a short commit
       code in brackets, for example `v5.7.72 (22928fe)`. The code matches the
       start of the second code on the `Updated commit … -> …` line of the
       update output.

        If the update printed `Already at commit`, the machine was already
        current and the footer is unchanged.

    9. Type `intelis` and press Enter. The menu headed **InteLIS** appears. From
       now on, follow the first situation on this page.

Which code a lab receives, and how it is published, is described in
[Release tracks](../release-tracks.md).
