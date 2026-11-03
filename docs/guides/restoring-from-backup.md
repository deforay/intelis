---
description: Restore the InteLIS database, an older dated backup, uploaded files, the audit trail or a deleted file onto a machine where InteLIS already runs.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# How to restore from a backup

Put the database back from a backup on a machine where InteLIS already runs.
A restore from the off-machine backup also brings back the lab settings,
signatures, report templates, logos and the audit trail. Uploaded files and attachments can
come back too.

Restoring replaces everything in the database. Records entered after the backup
was made are lost.

!!! info "How far back the backups go"

    The newest database backups, about the last 2 days, are on this machine
    and in the off-machine mirror. The off-machine backup also keeps one
    database backup per day for 7 days and one per week for 4 more weeks, about
    5 weeks in all. To restore one of those, follow the **Older date** tab.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Backup on this machine"

    Use this when the database backup is still on this machine, for example to
    undo records deleted by mistake.

    ### Restore the database

    1. Open a terminal and go to the InteLIS folder:

        ```bash
        cd /var/www/intelis
        ```

        On older installs, type `cd /var/www/vlsm` instead.

    2. Start the restore:

        ```bash
        sudo -u www-data php vendor/bin/db-tools restore
        ```

    3. A list of the backups on this machine appears. Choose the newest file
       starting with `vlsm-` that was made before the problem. Move to it with
       the arrow keys and press Enter. If the list is numbered, type its number
       and press Enter.

        The name gives the date and time of the backup. For example,
        `vlsm-20260903-100002-…` was made on 3 September 2026 at 10:00.

        Do not choose a file starting with `interfacing-` or `pre-restore-`.

        ??? failure "If it says `No backup files found`"

            There are no database backups on this machine. Follow the
            **Only the database** tab to fetch one from where the backups are
            sent.

    4. Wait for `Restore completed to vlsm`. The restore first saves a safety
       copy of the current database, in a file starting with `pre-restore-vlsm-`.

        [Restoring the database from a backup on this machine: choosing the backup in the list by typing `vlsm`, then `intelis migrate`.](../assets/casts/restore-local.cast){ .asciicast }

        ??? failure "If it ends with `Restore failed`"

            The database may now be empty. Put the safety copy back. Run step 2
            again and choose the newest file starting with `pre-restore-vlsm-`.
            Then contact support with the message shown.

    5. Apply the database updates for this version of InteLIS:

        ```bash
        intelis migrate
        ```

    ### Check the lab

    6. Open InteLIS in the browser.
    7. Log in with an administrator account that existed when the backup was
       made.
    8. Check the lab settings under **Admin → Settings → General Configuration**.
    9. Open a request entered shortly before the backup, and check its results
       are there.

=== "Only the database"

    Use this when the backups are sent to another Linux machine, a Windows
    shared folder or a USB drive, and only the data needs to come back.

    ### Fetch the backup

    1. Open a terminal and go to the InteLIS folder:

        ```bash
        cd /var/www/intelis
        ```

        On older installs, type `cd /var/www/vlsm` instead.

    2. Start the restore:

        ```bash
        intelis restore
        ```

        The command checks for the current restore script before it runs. If
        the machine is offline, it uses the installed script.

    3. At **Fetch the backup from there?**, choose **Yes**. The script uses the
       backup settings saved on this machine.

        ??? info "If it asks **Where is the backup stored?** instead"

            This machine has no saved backup settings. Choose where the backups
            are, then answer the questions for it:

            | Choice | Questions |
            | --- | --- |
            | **On another Linux machine** | The username, the hostname or IP address, and the SSH port (press Enter for `22`). Then type that user's password when asked. |
            | **In a shared folder on a Windows machine** | The hostname or IP address, the name of the shared folder, the Windows username, and its password. |
            | **On a USB or external drive plugged into this machine** | Choose the drive from the list. The script finds its `backups` folder, then asks which lab to restore. If the backup is elsewhere, choose **Browse folders inside a drive**. Open folders until you reach the backup, then choose **Use this folder**. |

        ??? failure "If it says `Could not connect`"

            Check the machine or drive is switched on and connected to the
            network, and that the details are right. Choose **Yes** at
            **Try different details?** (or **Try again?**) and type them again.

    4. At **Which lab should be restored?**, choose this lab. Each row starts
       with the lab name given when the backups were set up, and shows the
       newest database backup.

        ??? failure "If it says `There are no backups in …`"

            The script reached a place that holds no InteLIS backups. On the
            machine that sends the backups, run `intelis backup status`. The
            `Backing up to` line shows where they go.

    5. At **What should be copied back?**, choose **The database, settings and audit trail**.
    6. At **Where should the restore folder be created?**, press Enter to use
       `/var/intelis-restore`. Write down the new restore folder shown next. It
       looks like this:

        ```text
        /var/intelis-restore/centrallab-12345678-20260928-120000.ABCDEF
        ```

        Each restore uses a new folder. Files fetched by an earlier restore
        cannot be selected by mistake.

    7. Wait for the copy to finish. The script then checks each database
       backup. Files listed as encrypted are not checked. They open during the
       restore.

        ??? failure "If a file is reported as damaged"

            Check that another backup is listed as readable. At the confirmation,
            type the backup name. The script skips damaged files and restores
            the newest readable backup.

    ### Restore the database

    8. Check the displayed backup file and target installation. At
       **Type … to confirm that this is the backup to restore**, type the lab
       name exactly as shown.
    9. Wait for `Database restored`. The script first saves a safety copy of the
       current database, then restores the newest backup starting with `vlsm-`,
       then applies the database updates.

        [Restoring only the database from the backup machine, from `intelis restore` to the cleanup command. This test lab has no signatures or logos, so none are restored.](../assets/casts/restore-from-linux.cast){ .asciicast }

        ??? failure "If it says `The restore did not finish`"

            If the next line says `No safety copy was taken`, the database was
            not changed. Contact support with the message shown.

            Otherwise the database may now be empty. Put the safety copy back.
            The script prints the command to use. Replace `<pre-restore-file>`
            with the name of the file starting with `pre-restore-vlsm-` in
            `/var/www/intelis/backups/db` (`/var/www/vlsm/backups/db` on older
            installs), run it, then contact support with the message shown.

        ??? failure "If it says `The database is restored, but its migrations did not finish`"

            The data is back. Apply the updates by hand:

            ```bash
            intelis migrate
            ```

    10. Wait for `Signatures, report templates and logos restored`. The script
        puts back the lab settings from the newest settings backup, such as the
        STS address and the enabled modules. It keeps the database password of
        this machine. It then puts back the signatures, report templates and
        logos. The settings this machine had are saved in
        `configs/config.production.php.pre-restore`.

        ??? warning "If it says `This machine keeps its own settings`"

            The settings backup could not be used, so the lab settings did not
            change. Check the STS address and the enabled modules in
            **System Configuration**. See
            [System administration](../user-guides/admin-system-administration.md).

        ??? warning "If it says `holds no signatures, report templates or logos` or `did not bring back`"

            The settings backup was made by an older InteLIS, or is missing.
            Follow the **Database and uploaded files** tab to copy them back
            from the backup.

        The script then puts back the audit trail, the record of every change
        made to each sample, and shows `Audit trail restored`. On a lab with a
        long history, this copy can take a while.

        ??? warning "If it says `needs about … GB of free space` or `did not finish`"

            The audit trail was not restored, or only part of it. The rest of
            the restore is finished. When the disk is full, free up space on it
            first. Then run the `sudo bash -c` command shown with the message. It
            copies only what is still missing.

        ??? info "If it says `This installation already has an audit trail`"

            This machine keeps its own audit trail, which is newer than the one
            in the backup. Nothing more is needed.

        ??? warning "If it says `holds no audit trail` or `could not be copied`"

            The audit trail did not come down with the backup. The copy failed,
            or an older InteLIS fetched the backup. The lab works without it,
            but the change history of each sample is missing. Run
            `intelis restore` again to copy it down.

    ### Check the lab

    11. Open InteLIS in the browser.
    12. Log in with an administrator account that existed when the backup was
        made.
    13. Check the lab settings under **Admin → Settings → General Configuration**.
    14. Open a request entered shortly before the backup, and check its results
        are there.
    15. Open the result PDF of that request, and check its signatures and logo
        appear.
    16. If the lab uses the interfacing tool, restore its database too. Type
        this, followed by a space. Do not press Enter yet:

        ```bash
        sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing
        ```

        Open the **Files** app and press **Ctrl+L**. Type the restore folder from
        step 6, followed by `/db`, and press Enter. Drag the newest file starting
        with `interfacing-` onto the terminal window, and press Enter.

        Always keep `--profile=interfacing` in this command. Without it, the
        file is restored over the main database.

    17. When the lab works, run the exact cleanup command printed by the script.
        It removes only this fetched copy, which holds the database password.

=== "Older date"

    Use this to put the database back as it was on an earlier day, for example
    when a mistake is noticed after more than 2 days. The off-machine backup
    keeps one database backup per day for 7 days and one per week for 4 more
    weeks.

    ### Fetch the backup

    1. Open a terminal and go to the InteLIS folder:

        ```bash
        cd /var/www/intelis
        ```

        On older installs, type `cd /var/www/vlsm` instead.

    2. Start the restore:

        ```bash
        intelis restore
        ```

        The command checks for the current restore script before it runs. If
        the machine is offline, it uses the installed script.

    3. At **Fetch the backup from there?**, choose **Yes**. The script uses the
       backup settings saved on this machine.

        ??? info "If it asks **Where is the backup stored?** instead"

            This machine has no saved backup settings. Choose where the backups
            are, then answer the questions for it:

            | Choice | Questions |
            | --- | --- |
            | **On another Linux machine** | The username, the hostname or IP address, and the SSH port (press Enter for `22`). Then type that user's password when asked. |
            | **In a shared folder on a Windows machine** | The hostname or IP address, the name of the shared folder, the Windows username, and its password. |
            | **On a USB or external drive plugged into this machine** | Choose the drive from the list. The script finds its `backups` folder, then asks which lab to restore. If the backup is elsewhere, choose **Browse folders inside a drive**. Open folders until you reach the backup, then choose **Use this folder**. |

        ??? failure "If it says `Could not connect`"

            Check the machine or drive is switched on and connected to the
            network, and that the details are right. Choose **Yes** at
            **Try different details?** (or **Try again?**) and type them again.

    4. At **Which lab should be restored?**, choose this lab. Each row starts
       with the lab name given when the backups were set up, and shows the
       newest database backup.

        ??? failure "If it says `There are no backups in …`"

            The script reached a place that holds no InteLIS backups. On the
            machine that sends the backups, run `intelis backup status`. The
            `Backing up to` line shows where they go.

    5. At **What should be copied back?**, choose
       **An older database backup (choose a date)**.
    6. At **Which date should be restored?**, choose the day. Each row shows
       the date and time the backup was made.

        ??? failure "If it says `There is no dated history in this backup yet`"

            The history starts with the first backup made after InteLIS was
            updated. Until then, only the newest backups exist. Follow the
            **Only the database** tab instead.

    7. At **Where should the restore folder be created?**, press Enter to use
       `/var/intelis-restore`. Write down the new restore folder shown next. It
       looks like this:

        ```text
        /var/intelis-restore/centrallab-12345678-20260928-120000.ABCDEF
        ```

        Each restore uses a new folder. Files fetched by an earlier restore
        cannot be selected by mistake.

    8. Wait for the copy to finish. The script then checks the backup. A file
       listed as encrypted is not checked. It opens during the restore.

        ??? failure "If the file is reported as damaged"

            Do not type the backup name at the confirmation. Run
            `intelis restore` again and choose another date.

    ### Restore the database

    9. Check the displayed backup file and target installation. At
       **Type … to confirm that this is the backup to restore**, type the lab
       name exactly as shown.
    10. Wait for `Database restored`. The script first saves a safety copy of
        the current database, then restores the chosen backup, then applies the
        database updates.

        ??? failure "If it says `The restore did not finish`"

            If the next line says `No safety copy was taken`, the database was
            not changed. Contact support with the message shown.

            Otherwise the database may now be empty. Put the safety copy back.
            The script prints the command to use. Replace `<pre-restore-file>`
            with the name of the file starting with `pre-restore-vlsm-` in
            `/var/www/intelis/backups/db` (`/var/www/vlsm/backups/db` on older
            installs), run it, then contact support with the message shown.

        ??? failure "If it says `The database is restored, but its migrations did not finish`"

            The data is back. Apply the updates by hand:

            ```bash
            intelis migrate
            ```

    11. Wait for `Signatures, report templates and logos restored`. The script
        puts back the lab settings from the newest settings backup, such as the
        STS address and the enabled modules. It keeps the database password of
        this machine. It then puts back the signatures, report templates and
        logos. The settings this machine had are saved in
        `configs/config.production.php.pre-restore`.

        ??? warning "If it says `This machine keeps its own settings`"

            The settings backup could not be used, so the lab settings did not
            change. Check the STS address and the enabled modules in
            **System Configuration**. See
            [System administration](../user-guides/admin-system-administration.md).

        ??? warning "If it says `holds no signatures, report templates or logos` or `did not bring back`"

            The settings backup was made by an older InteLIS, or is missing.
            Follow the **Database and uploaded files** tab to copy them back
            from the backup.

        The script then puts back the audit trail, the record of every change
        made to each sample, and shows `Audit trail restored`. On a lab with a
        long history, this copy can take a while.

        ??? warning "If it says `needs about … GB of free space` or `did not finish`"

            The audit trail was not restored, or only part of it. The rest of
            the restore is finished. When the disk is full, free up space on it
            first. Then run the `sudo bash -c` command shown with the message. It
            copies only what is still missing.

        ??? info "If it says `This installation already has an audit trail`"

            This machine keeps its own audit trail, which is newer than the one
            in the backup. Nothing more is needed.

        ??? warning "If it says `holds no audit trail` or `could not be copied`"

            The audit trail did not come down with the backup. The copy failed,
            or an older InteLIS fetched the backup. The lab works without it,
            but the change history of each sample is missing. Run
            `intelis restore` again to copy it down.

    ### Check the lab

    12. Open InteLIS in the browser.
    13. Log in with an administrator account that existed when the backup was
        made.
    14. Check the lab settings under **Admin → Settings → General Configuration**.
    15. Open a request entered shortly before the backup, and check its results
        are there.
    16. Open the result PDF of that request, and check its signatures and logo
        appear.
    17. If the lab uses the interfacing tool, its database stays as it is. To
        put it back to the same day too, contact support.

    18. When the lab works, run the exact cleanup command printed by the script.
        It removes only this fetched copy, which holds the database password.

=== "Database and uploaded files"

    Use this when the backups are sent to another Linux machine, a Windows
    shared folder or a USB drive, and uploaded files and attachments are missing
    as well as data.

    ### Fetch the backup

    1. Open a terminal and go to the InteLIS folder:

        ```bash
        cd /var/www/intelis
        ```

        On older installs, type `cd /var/www/vlsm` instead.

    2. Start the restore:

        ```bash
        intelis restore
        ```

        The command checks for the current restore script before it runs. If
        the machine is offline, it uses the installed script.

    3. At **Fetch the backup from there?**, choose **Yes**. The script uses the
       backup settings saved on this machine.

        ??? info "If it asks **Where is the backup stored?** instead"

            This machine has no saved backup settings. Choose where the backups
            are, then answer the questions for it:

            | Choice | Questions |
            | --- | --- |
            | **On another Linux machine** | The username, the hostname or IP address, and the SSH port (press Enter for `22`). Then type that user's password when asked. |
            | **In a shared folder on a Windows machine** | The hostname or IP address, the name of the shared folder, the Windows username, and its password. |
            | **On a USB or external drive plugged into this machine** | Choose the drive from the list. The script finds its `backups` folder, then asks which lab to restore. If the backup is elsewhere, choose **Browse folders inside a drive**. Open folders until you reach the backup, then choose **Use this folder**. |

        ??? failure "If it says `Could not connect`"

            Check the machine or drive is switched on and connected to the
            network, and that the details are right. Choose **Yes** at
            **Try different details?** (or **Try again?**) and type them again.

    4. At **Which lab should be restored?**, choose this lab. Each row starts
       with the lab name given when the backups were set up, and shows the
       newest database backup.

        ??? failure "If it says `There are no backups in …`"

            The script reached a place that holds no InteLIS backups. On the
            machine that sends the backups, run `intelis backup status`. The
            `Backing up to` line shows where they go.

    5. At **What should be copied back?**, choose
       **Everything, including uploaded files and attachments**.
    6. At **Where should the restore folder be created?**, press Enter to use
       `/var/intelis-restore`. Write down the new restore folder shown next. It
       looks like this:

        ```text
        /var/intelis-restore/centrallab-12345678-20260928-120000.ABCDEF
        ```

        Each restore uses a new folder. Files fetched by an earlier restore
        cannot be selected by mistake.

    7. Wait for the copy to finish. It copies the whole InteLIS folder and can
       take hours over a network. The script then checks each database backup.
       Files listed as encrypted are not checked. They open during the restore.

        ??? failure "If a file is reported as damaged"

            Check that another backup is listed as readable. At the confirmation,
            type the backup name. The script skips damaged files and restores
            the newest readable backup.

    ### Restore the database

    8. Check the displayed backup file and target installation. At
       **Type … to confirm that this is the backup to restore**, type the lab
       name exactly as shown.
    9. Wait for `Database restored`. The script first saves a safety copy of the
       current database, then restores the newest backup starting with `vlsm-`,
       then applies the database updates.

        ??? failure "If it says `The restore did not finish`"

            If the next line says `No safety copy was taken`, the database was
            not changed. Contact support with the message shown.

            Otherwise the database may now be empty. Put the safety copy back.
            The script prints the command to use. Replace `<pre-restore-file>`
            with the name of the file starting with `pre-restore-vlsm-` in
            `/var/www/intelis/backups/db` (`/var/www/vlsm/backups/db` on older
            installs), run it, then contact support with the message shown.

        ??? failure "If it says `The database is restored, but its migrations did not finish`"

            The data is back. Apply the updates by hand:

            ```bash
            intelis migrate
            ```

    10. Wait for `Signatures, report templates and logos restored`. The script
        puts back the lab settings from the newest settings backup, such as the
        STS address and the enabled modules. It keeps the database password of
        this machine. It then puts back the signatures, report templates and
        logos. The settings this machine had are saved in
        `configs/config.production.php.pre-restore`.

        ??? warning "If it says `This machine keeps its own settings`"

            The settings backup could not be used, so the lab settings did not
            change. Check the STS address and the enabled modules in
            **System Configuration**. See
            [System administration](../user-guides/admin-system-administration.md).

        ??? warning "If it says `holds no signatures, report templates or logos` or `did not bring back`"

            The settings backup was made by an older InteLIS, or is missing.
            The steps below copy them back from the backup too.

        The script then puts back the audit trail, the record of every change
        made to each sample, and shows `Audit trail restored`. On a lab with a
        long history, this copy can take a while.

        ??? warning "If it says `needs about … GB of free space` or `did not finish`"

            The audit trail was not restored, or only part of it. The rest of
            the restore is finished. When the disk is full, free up space on it
            first. Then run the `sudo bash -c` command shown with the message. It
            copies only what is still missing.

        ??? info "If it says `This installation already has an audit trail`"

            This machine keeps its own audit trail, which is newer than the one
            in the backup. Nothing more is needed.

        ??? warning "If it says `holds no audit trail` or `could not be copied`"

            The audit trail did not come down with the backup. The copy failed,
            or an older InteLIS fetched the backup. The lab works without it,
            but the change history of each sample is missing. Run
            `intelis restore` again to copy it down.

    ### Put the uploaded files back

    11. Save the restore folder from step 6 in the terminal. Replace the example
        with the path shown by the script:

        ```bash
        restore_dir="/var/intelis-restore/centrallab-12345678-20260928-120000.ABCDEF"
        ```

    12. Check what the copy holds:

        ```bash
        ls "$restore_dir/public/uploads"
        ```

    13. List what would be copied, without changing anything:

        ```bash
        sudo rsync -a --dry-run --itemize-changes "$restore_dir/public/uploads/" /var/www/intelis/public/uploads/
        ```

        Each line is a file that is missing or different on this machine.

    14. Copy the files across:

        ```bash
        sudo rsync -a "$restore_dir/public/uploads/" /var/www/intelis/public/uploads/
        ```

        On older installs, type `/var/www/vlsm/public/uploads/` as the last
        path. Do not add `--delete`. Files added since the backup would be
        removed.

    15. Repair the file ownership, so the web server can read the restored
        files:

        ```bash
        sudo intelis provision
        ```

    ### Check the lab

    16. Open InteLIS in the browser.
    17. Log in with an administrator account that existed when the backup was
        made.
    18. Check the lab settings under **Admin → Settings → General Configuration**.
    19. Open a request entered shortly before the backup, and check its results
        are there.
    20. Open a result PDF, and check its signatures and logo appear.
    21. Open an attachment, and check it displays.
    22. If the lab uses the interfacing tool, restore its database too. Type
        this, followed by a space. Do not press Enter yet:

        ```bash
        sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing
        ```

        Open the **Files** app and press **Ctrl+L**. Type the restore folder from
        step 6, followed by `/backups/db`, and press Enter. Drag the newest file
        starting with `interfacing-` onto the terminal window, and press Enter.

        Always keep `--profile=interfacing` in this command. Without it, the
        file is restored over the main database.

    23. When the lab works, run the exact cleanup command printed by the script.
        It removes only this fetched copy, which holds the database password.

=== "Only the audit trail"

    Use this when InteLIS runs normally but the audit trail is missing or
    incomplete, for example when `intelis backup status` shows
    `WITH A WARNING`. The audit trail is the record of every change made to
    each sample. The database and settings are not changed.

    ### Fetch the backup

    1. Open a terminal and go to the InteLIS folder:

        ```bash
        cd /var/www/intelis
        ```

        On older installs, type `cd /var/www/vlsm` instead.

    2. Start the restore:

        ```bash
        intelis restore
        ```

        The command checks for the current restore script before it runs. If
        the machine is offline, it uses the installed script.

    3. At **Fetch the backup from there?**, choose **Yes**. The script uses the
       backup settings saved on this machine.

        ??? info "If it asks **Where is the backup stored?** instead"

            This machine has no saved backup settings. Choose where the backups
            are, then answer the questions for it:

            | Choice | Questions |
            | --- | --- |
            | **On another Linux machine** | The username, the hostname or IP address, and the SSH port (press Enter for `22`). Then type that user's password when asked. |
            | **In a shared folder on a Windows machine** | The hostname or IP address, the name of the shared folder, the Windows username, and its password. |
            | **On a USB or external drive plugged into this machine** | Choose the drive from the list. The script finds its `backups` folder, then asks which lab to restore. If the backup is elsewhere, choose **Browse folders inside a drive**. Open folders until you reach the backup, then choose **Use this folder**. |

        ??? failure "If it says `Could not connect`"

            Check the machine or drive is switched on and connected to the
            network, and that the details are right. Choose **Yes** at
            **Try different details?** (or **Try again?**) and type them again.

    4. At **Which lab should be restored?**, choose this lab. Each row starts
       with the lab name given when the backups were set up, and shows the
       newest database backup.

        ??? failure "If it says `There are no backups in …`"

            The script reached a place that holds no InteLIS backups. On the
            machine that sends the backups, run `intelis backup status`. The
            `Backing up to` line shows where they go.

    5. At **What should be copied back?**, choose **Only the audit trail**.

        ??? info "If it says `already has the whole audit trail`"

            This machine holds at least as much audit trail as the backup.
            Nothing was copied, and nothing was changed. Nothing more is
            needed.

    6. At **Where should the restore folder be created?**, press Enter to use
       `/var/intelis-restore`.

    ### Put the audit trail back

    7. Wait for `Audit trail restored`. The script copies back only the audit
       trail files that are missing on this machine, or older here than in the
       backup. Every newer file on this machine is kept. On a lab with a long
       history, this copy can take a while.

        ??? warning "If it says `needs about … GB of free space` or `did not finish`"

            The audit trail was not restored, or only part of it. Keep the
            restore folder. When the disk is full, free up space on it first.
            Then run the `sudo bash -c` command shown with the message. It
            copies only what is still missing.

        ??? warning "If it says `could not be copied from the backup`"

            The copy from the backup failed, or the backup holds no audit
            trail. Nothing was changed. Check that the backup machine or drive
            is reachable, then run `intelis restore` again.

    ### Check the lab

    8. Open InteLIS in the browser.
    9. Open a sample changed several months ago, and open its audit trail.
       Check its earlier changes are listed.
    10. Run `intelis backup`, then `intelis backup status`. Check that
        `Last attempt` shows `succeeded`.
    11. Run the cleanup command printed by the script. It removes only this
        fetched copy.

=== "A deleted file"

    Use this when a file was deleted or changed on this machine by mistake in
    the last 14 days, for example a signature or a report template. The
    database, the settings and the audit trail are not changed.

    ### Fetch the backup

    1. Open a terminal and go to the InteLIS folder:

        ```bash
        cd /var/www/intelis
        ```

        On older installs, type `cd /var/www/vlsm` instead.

    2. Start the restore:

        ```bash
        intelis restore
        ```

        The command checks for the current restore script before it runs. If
        the machine is offline, it uses the installed script.

    3. At **Fetch the backup from there?**, choose **Yes**. The script uses the
       backup settings saved on this machine.

        ??? info "If it asks **Where is the backup stored?** instead"

            This machine has no saved backup settings. Choose where the backups
            are, then answer the questions for it:

            | Choice | Questions |
            | --- | --- |
            | **On another Linux machine** | The username, the hostname or IP address, and the SSH port (press Enter for `22`). Then type that user's password when asked. |
            | **In a shared folder on a Windows machine** | The hostname or IP address, the name of the shared folder, the Windows username, and its password. |
            | **On a USB or external drive plugged into this machine** | Choose the drive from the list. The script finds its `backups` folder, then asks which lab to restore. If the backup is elsewhere, choose **Browse folders inside a drive**. Open folders until you reach the backup, then choose **Use this folder**. |

        ??? failure "If it says `Could not connect`"

            Check the machine or drive is switched on and connected to the
            network, and that the details are right. Choose **Yes** at
            **Try different details?** (or **Try again?**) and type them again.

    4. At **Which lab should be restored?**, choose this lab. Each row starts
       with the lab name given when the backups were set up, and shows the
       newest database backup.

        ??? failure "If it says `There are no backups in …`"

            The script reached a place that holds no InteLIS backups. On the
            machine that sends the backups, run `intelis backup status`. The
            `Backing up to` line shows where they go.

    5. At **What should be copied back?**, choose
       **A file deleted or changed in the last 14 days**.
    6. At **Which backup removed the file?**, choose the first backup made
       after the file was lost. Each row shows the date and time in UTC, and
       how many files that backup removed.

        ??? info "If it says `holds no deleted or changed files`"

            No backup in the last 14 days removed a file, apart from the audit
            trail. To restore the audit trail, follow the
            **Only the audit trail** tab.

    7. At **Which file should be put back?**, choose the file, or
       **All … files** to put back everything that backup removed.
    8. Check the list of places shown. At **Put back the file(s) listed
       above?**, choose **Yes**.

        A file already at one of those places is not overwritten. It is kept
        beside the restored file as `<name>.before-restore-<time>`.

    ### Check the lab

    9. Open InteLIS in the browser.
    10. Check the restored file where it is used. For a signature or a logo,
        open a result PDF.
    11. When the restored file is right, delete the `.before-restore-` copy if
        there is one.

=== "Lab machine hacked or encrypted"

    Use this when a lab machine was hacked or encrypted by ransomware, and its
    backups on the backup machine were deleted or damaged too. The backup
    machine keeps nightly copies that no lab machine can change. This works
    only when the backups go to another Linux machine, and
    `intelis backup status` showed **Server snapshots** before the attack.

    ### Stop the lab machine from reaching its backups

    1. Disconnect the lab machine from the network.
    2. On the backup machine, log in with its administrator account.
    3. End every open connection of the backup account:

        ```bash
        sudo pkill -KILL -u lisbackup
        ```

        A backup another lab was sending at that moment fails once, and its
        next scheduled backup sends it again.

    4. Remove every key from the backup account:

        ```bash
        sudo truncate -s 0 /home/lisbackup/.ssh/authorized_keys
        ```

        Whoever hacked the lab machine had its key, and the backup account can
        change its own list of keys, so any key on it may now be theirs, even
        one carrying another lab's name. Removing all of them is the only way
        to be sure. Every lab's backups stop until step 5 is done for it.

    5. On every other lab's machine, run `intelis backup setup`, and choose
       the administrator login when it asks how to give access. It adds that
       lab's key again. The dashboard of each lab, and the **Backup** column of
       **Lab Sync Status** on the STS, show which labs still need it.

    ### Copy the lab's backups out of a snapshot

    6. On the backup machine, run:

        ```bash
        sudo intelis-backup-snapshots restore
        ```

    7. At **Which lab?**, type the number of the lab.
    8. The lab's size in each snapshot is listed, newest first. At
       **Which date?**, type the number of the last date before the size
       dropped. The snapshots after the attack hold the damage.
    9. Write down the folder name shown at the end, for example
       `centrallab-12345678-snapshot-20261101`. The lab's live backup folder
       is not changed.

    ### Rebuild the lab machine

    10. Reinstall Ubuntu on the lab machine, or use a new machine. A hacked
        machine cannot be trusted again, even after cleaning.
    11. On the rebuilt machine, follow the **Backups on a server or share** tab
        of [Migrating From One Ubuntu Machine to Another](migrating-ubuntu-machines.md).
        It installs InteLIS and restores the backup in one step. At step 3 of
        that tab, choose the folder from step 9 above instead of the lab's own
        folder.

    12. Set up backups again with `intelis backup setup`. Choose the same
        backup machine, and the administrator login when asked for access.

    ### Clean up

    13. When the lab works, remove the copied folder on the backup machine.
        Replace the name with the one from step 9:

        ```bash
        sudo rm -rf /home/lisbackup/backups/centrallab-12345678-snapshot-20261101
        ```
