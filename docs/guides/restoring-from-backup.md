# Restoring from a Backup

Put the database back from a backup on a machine where InteLIS already runs.
Uploaded files and attachments can come back too.

Restoring replaces everything in the database. Records entered after the backup
was made are lost.

!!! warning "How far back the backups go"

    A database backup is made every 6 hours and only the newest 7 are kept, so
    the backups cover about the last 2 days. The off-machine copy mirrors this
    folder, so it holds the same 2 days. A mistake noticed later than that
    cannot be undone from these backups. Keep a weekly copy of `backups/db` on a
    drive that is then unplugged and stored away.

To set up InteLIS on a new or empty machine from a backup, follow the
**Backups on a server or share** tab of
[Migrating From One Ubuntu Machine to Another](migrating-ubuntu-machines.md)
instead.

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
    8. Check the lab settings under **Admin → System Configuration → General Configuration**.
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

        ??? info "If `intelis` is not recognised"

            The install is older. Download the restore script and run it from
            the same folder:

            ```bash
            wget -O ~/restore-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/restore-backup.sh
            sudo bash ~/restore-backup.sh
            ```

    3. At **Fetch the backup from there?**, choose **Yes**. The script uses the
       backup settings saved on this machine.

        ??? info "If it asks **Where is the backup stored?** instead"

            This machine has no saved backup settings. Choose where the backups
            are, then answer the questions for it:

            | Choice | Questions |
            | --- | --- |
            | **On another Linux machine** | The username, the hostname or IP address, and the SSH port (press Enter for `22`). Then type that user's password when asked. |
            | **In a shared folder on a Windows machine** | The hostname or IP address, the name of the shared folder, the Windows username, and its password. |
            | **On a USB or external drive plugged into this machine** | The folder on the drive. The script lists the drives. Type the path shown in the `MOUNTPOINT` column for the USB drive, for example `/media/labuser/BACKUP`. |

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

    5. At **What should be copied back?**, choose **Just the database backups**.
    6. At **Where should the files be put on this machine?**, type this and
       press Enter:

        ```text
        /var/intelis-restore
        ```

        Type exactly this path, so the commands in the later steps match.
        Older versions of the script offer a folder under `/root`. Do not accept
        it: the restore runs as the web server's account, which cannot read
        `/root`, and it fails.

    7. Wait for the copy to finish. The script then checks each database
       backup. Files listed as encrypted are not checked. They open during the
       restore.

        ??? failure "If a file is reported as damaged"

            At the next question, choose **No**. Then restore the newest file
            starting with `vlsm-` that is not damaged. Type this, followed by a
            space. Do not press Enter yet:

            ```bash
            sudo -u www-data php vendor/bin/db-tools restore
            ```

            Open the **Files** app and press **Ctrl+L**. Type
            `/var/intelis-restore/db` and press Enter. Drag the file onto the
            terminal window, and press Enter. Then run `intelis migrate` and
            carry on at step 10.

    ### Restore the database

    8. At **Restore … into /var/www/intelis now?**, check the lab name, then
       choose **Yes**.
    9. Wait for `Database restored`. The script first saves a safety copy of the
       current database, then restores the newest backup starting with `vlsm-`,
       then applies the database updates.

        ??? failure "If it says `The restore did not finish`"

            If the next line says `No safety copy was taken`, the database was
            not changed. Contact support with the message shown.

            Otherwise the database may now be empty. Put the safety copy back.
            The script prints the command to use. Replace `<pre-restore-file>`
            with the name of the file starting with `pre-restore-vlsm-` in
            `/var/intelis-restore/db`, run it, then contact support with the
            message shown.

        ??? failure "If it says `Could not apply database migrations`"

            The data is back. Apply the updates by hand:

            ```bash
            intelis migrate
            ```

    ### Check the lab

    10. Open InteLIS in the browser.
    11. Log in with an administrator account that existed when the backup was
        made.
    12. Check the lab settings under **Admin → System Configuration → General Configuration**.
    13. Open a request entered shortly before the backup, and check its results
        are there.
    14. If the lab uses the interfacing tool, restore its database too. Type
        this, followed by a space. Do not press Enter yet:

        ```bash
        sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing
        ```

        Open the **Files** app and press **Ctrl+L**. Type
        `/var/intelis-restore/db` and press Enter. Drag the newest file starting
        with `interfacing-` onto the terminal window, and press Enter.

        Always keep `--profile=interfacing` in this command. Without it, the
        file is restored over the main database.

    15. When the lab works, delete the fetched copy. It holds the database
        password.

        ```bash
        sudo rm -rf /var/intelis-restore
        ```

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

        ??? info "If `intelis` is not recognised"

            The install is older. Download the restore script and run it from
            the same folder:

            ```bash
            wget -O ~/restore-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/restore-backup.sh
            sudo bash ~/restore-backup.sh
            ```

    3. At **Fetch the backup from there?**, choose **Yes**. The script uses the
       backup settings saved on this machine.

        ??? info "If it asks **Where is the backup stored?** instead"

            This machine has no saved backup settings. Choose where the backups
            are, then answer the questions for it:

            | Choice | Questions |
            | --- | --- |
            | **On another Linux machine** | The username, the hostname or IP address, and the SSH port (press Enter for `22`). Then type that user's password when asked. |
            | **In a shared folder on a Windows machine** | The hostname or IP address, the name of the shared folder, the Windows username, and its password. |
            | **On a USB or external drive plugged into this machine** | The folder on the drive. The script lists the drives. Type the path shown in the `MOUNTPOINT` column for the USB drive, for example `/media/labuser/BACKUP`. |

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
    6. At **Where should the files be put on this machine?**, type this and
       press Enter:

        ```text
        /var/intelis-restore
        ```

        Type exactly this path, so the commands in the later steps match.
        Older versions of the script offer a folder under `/root`. Do not accept
        it: the restore runs as the web server's account, which cannot read
        `/root`, and it fails.

    7. Wait for the copy to finish. It copies the whole InteLIS folder and can
       take hours over a network. The script then checks each database backup.
       Files listed as encrypted are not checked. They open during the restore.

        ??? failure "If a file is reported as damaged"

            At the next question, choose **No**. Then restore the newest file
            starting with `vlsm-` that is not damaged. Type this, followed by a
            space. Do not press Enter yet:

            ```bash
            sudo -u www-data php vendor/bin/db-tools restore
            ```

            Open the **Files** app and press **Ctrl+L**. Type
            `/var/intelis-restore/backups/db` and press Enter. Drag the file
            onto the terminal window, and press Enter. Then run
            `intelis migrate` and carry on at step 10.

    ### Restore the database

    8. At **Restore … into /var/www/intelis now?**, check the lab name, then
       choose **Yes**.
    9. Wait for `Database restored`. The script first saves a safety copy of the
       current database, then restores the newest backup starting with `vlsm-`,
       then applies the database updates.

        ??? failure "If it says `The restore did not finish`"

            If the next line says `No safety copy was taken`, the database was
            not changed. Contact support with the message shown.

            Otherwise the database may now be empty. Put the safety copy back.
            The script prints the command to use. Replace `<pre-restore-file>`
            with the name of the file starting with `pre-restore-vlsm-` in
            `/var/intelis-restore/db`, run it, then contact support with the
            message shown.

        ??? failure "If it says `Could not apply database migrations`"

            The data is back. Apply the updates by hand:

            ```bash
            intelis migrate
            ```

    ### Put the uploaded files back

    10. Check what the copy holds:

        ```bash
        ls /var/intelis-restore/public/uploads
        ```

    11. List what would be copied, without changing anything:

        ```bash
        sudo rsync -a --dry-run --itemize-changes /var/intelis-restore/public/uploads/ /var/www/intelis/public/uploads/
        ```

        Each line is a file that is missing or different on this machine.

    12. Copy the files across:

        ```bash
        sudo rsync -a /var/intelis-restore/public/uploads/ /var/www/intelis/public/uploads/
        ```

        On older installs, type `/var/www/vlsm/public/uploads/` as the last
        path. Do not add `--delete`. Files added since the backup would be
        removed.

        Then copy back the audit trail, which holds the change history of
        older samples:

        ```bash
        sudo rsync -a /var/intelis-restore/var/audit-trail/ /var/www/intelis/var/audit-trail/
        ```

        On older installs, type `/var/www/vlsm/var/audit-trail/` as the last
        path.

    13. Repair the file ownership, so the web server can read the restored
        files:

        ```bash
        sudo intelis provision
        ```

    ### Check the lab

    14. Open InteLIS in the browser.
    15. Log in with an administrator account that existed when the backup was
        made.
    16. Check the lab settings under **Admin → System Configuration → General Configuration**.
    17. Open a request entered shortly before the backup, and check its results
        are there.
    18. Open a result PDF or an attachment, and check it displays.
    19. If the lab uses the interfacing tool, restore its database too. Type
        this, followed by a space. Do not press Enter yet:

        ```bash
        sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing
        ```

        Open the **Files** app and press **Ctrl+L**. Type
        `/var/intelis-restore/backups/db` and press Enter. Drag the newest file
        starting with `interfacing-` onto the terminal window, and press Enter.

        Always keep `--profile=interfacing` in this command. Without it, the
        file is restored over the main database.

    20. When the lab works, delete the fetched copy. It holds the database
        password.

        ```bash
        sudo rm -rf /var/intelis-restore
        ```
