---
description: Move an InteLIS lab to a new Ubuntu machine, or rebuild a dead one, by restoring from its backups.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Migrating From One Ubuntu Machine to Another

Move a lab to a new Ubuntu machine, or rebuild a machine that has died, from the
old machine's backups.

The new machine must run **Ubuntu 24.04 LTS or later** and be connected to the
internet.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Old machine still works"

    ### On the old machine

    1. Open a terminal and take a fresh backup, so nothing entered since the last
       automatic backup is lost:

        ```bash
        intelis backup
        ```

        If it asks `Set up off-machine backups now?`, press Enter (No).

        ??? info "If `intelis` is not recognised"

            The install is older. Export the database with this instead:

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
              `These databases were NOT backed up`, stop.

        ??? failure "If MySQL will not start"

            Nothing can be backed up until MySQL runs. Follow
            [MySQL will not start](mysql-will-not-start.md), then come back to
            step 1.

    2. Plug a USB drive into the old machine.
    3. In the terminal, type this, followed by a space. Do not press Enter yet:

        ```bash
        sudo cp -r /var/www/intelis/backups
        ```

        On older installs, type `/var/www/vlsm/backups` in place of
        `/var/www/intelis/backups`.

    4. Open the **Files** app. Drag the USB drive from the left sidebar onto the
       terminal window. Its path appears after the command.
    5. Press Enter. The `backups` folder is copied onto the USB drive.
    6. Unplug the USB drive.

    ### On the new machine

    7. Plug in the USB drive.
    8. Open a terminal and download the installer:

        ```bash
        cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)"
        ```

    9. Type this, followed by a space. Do not press Enter yet:

        ```bash
        sudo bash setup.sh --restore-from-backup-folder
        ```

    10. Open the USB drive in the **Files** app. Drag the `backups` folder onto
        the terminal window. Its path appears after the command.

        Instead of dragging, click the folder once and press **Ctrl+C**. Then
        click in the terminal and press **Ctrl+Shift+V**. If the path has spaces
        and no quotes around it, add `'` at both ends.

    11. Press Enter, then answer the installer's questions:

        | Question | Answer |
        | --- | --- |
        | Installation directory | Press Enter. |
        | Which backup should be restored? | Press Enter. The newest backup is on top and already selected. |
        | What is this machine? | **Lab machine (LIS)**. |
        | Remote STS URL | The STS address the old machine used. Leave it empty if the lab has no STS. |
        | New MySQL root password | A new password for this machine, typed twice. Write it down. |
        | Is this correct? | Check the summary, then press Enter (Yes). |

    12. Wait 10 to 20 minutes. The installer ends with `Setup complete`.

        ??? failure "If it stops with `Failed to decrypt`"

            Setup could not open the backup with the old machine's settings in
            `backups/config`. Ask the STS administrator for a one-time recovery
            token. They run this on the STS:

            ```bash
            cd /var/www/intelis && sudo -u www-data php bin/backup-key-admin.php approve --lab <lab-id>
            ```

            Then repeat steps 9 to 11, adding the STS address and the token after
            the folder path:

            ```bash
            sudo bash setup.sh --restore-from-backup-folder '<folder>' --sts-url https://sts.example.org --recovery-token ABCD-EFGH-JKMN-PQRS
            ```

            The second run asks `Reuse your previous setup answers and skip the prompts?`.
            Press Enter (Yes) to reuse the answers. If it says
            `An existing database was found`, choose **Keep a copy, then start fresh**.

    ### Check the lab

    13. Open InteLIS in the browser.
    14. Log in with an administrator account from the old machine. Do not create
        a new one. The restored database already holds the users, the lab
        settings and all the data.
    15. Check the lab settings under **Admin → System Configuration → General Configuration**.
    16. If the lab uses the interfacing tool, restore its database too.

        1. In a terminal, type `sudo cp` followed by a space. Do not press Enter
           yet.
        2. Drag the newest file starting with `interfacing-` from the `db`
           folder onto the terminal. Then type ` /tmp/` and press Enter.
        3. Restore it into the interfacing database:

            ```bash
            cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing /tmp/interfacing-*
            ```

        4. Delete the copy:

            ```bash
            sudo rm /tmp/interfacing-*
            ```

        If step 3 reports that the profile `interfacing` does not exist, set up
        the interfacing tool first with
        [Setting up the interfacing tool](setting-up-interfacing-tool.md), then
        repeat step 3. If it reports that it cannot open an encrypted file,
        contact support with the file name.

=== "Old machine is dead"

    Use the `backups` folder that was copied off the old machine.

    ### Check the copied folder

    1. Plug the USB drive with the copied folder into the new machine.
    2. Open the `backups` folder in the **Files** app. Check it holds both of
       these folders:

        | Folder | What it holds |
        | --- | --- |
        | `db` | The database backups. |
        | `config` | The old machine's settings. The key to the database backups is read from here. |

        If `config` is missing, carry on. Step 8 shows what to do if the backup
        does not open.

    3. Open `db` and find the newest file starting with `vlsm-`. Its name gives
       the date and time of the backup. For example, `vlsm-20260903-100002-…`
       was made on 3 September 2026 at 10:00. The lab returns to that point.

    Do not rename any file. Each file's name is part of its key.

    ### Install and restore

    4. Open a terminal and download the installer:

        ```bash
        cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)"
        ```

    5. Type this, followed by a space. Do not press Enter yet:

        ```bash
        sudo bash setup.sh --restore-from-backup-folder
        ```

    6. Drag the `backups` folder from the **Files** app onto the terminal window.
       Its path appears after the command.

        Instead of dragging, click the folder once and press **Ctrl+C**. Then
        click in the terminal and press **Ctrl+Shift+V**. If the path has spaces
        and no quotes around it, add `'` at both ends.

    7. Press Enter, then answer the installer's questions:

        | Question | Answer |
        | --- | --- |
        | Installation directory | Press Enter. |
        | Which backup should be restored? | Press Enter. The newest backup is on top and already selected. |
        | What is this machine? | **Lab machine (LIS)**. |
        | Remote STS URL | The STS address the old machine used. Leave it empty if the lab has no STS. |
        | New MySQL root password | A new password for this machine, typed twice. Write it down. |
        | Is this correct? | Check the summary, then press Enter (Yes). |

    8. Wait 10 to 20 minutes. The installer ends with `Setup complete`.

        ??? failure "If it stops with `Failed to decrypt`"

            The installer could not find the key. Either the `config` folder was
            not copied, or the backup uses a key held by the STS. Ask the STS
            administrator for a one-time recovery token. They run this on the STS:

            ```bash
            cd /var/www/intelis && sudo -u www-data php bin/backup-key-admin.php approve --lab <lab-id>
            ```

            Then repeat steps 5 to 7, adding the STS address and the token after
            the folder path:

            ```bash
            sudo bash setup.sh --restore-from-backup-folder '<folder>' --sts-url https://sts.example.org --recovery-token ABCD-EFGH-JKMN-PQRS
            ```

            The second run asks `Reuse your previous setup answers and skip the prompts?`.
            Press Enter (Yes) to reuse the answers. If it says
            `An existing database was found`, choose **Keep a copy, then start fresh**.

            If this machine cannot reach the STS, ask the STS administrator for
            the recovery code instead. They get it by running this on the STS:

            ```bash
            cd /var/www/intelis && sudo -u www-data php bin/backup-key-admin.php show-code --lab <lab-id>
            ```

            Then run:

            ```bash
            sudo bash setup.sh --restore-from-backup-folder '<folder>' --encryption-password '<recovery-code>'
            ```

    ### Check the lab

    9. Open InteLIS in the browser.
    10. Log in with an administrator account from the old machine. Do not create
        a new one. The restored database already holds the users, the lab
        settings and all the data.
    11. Check the lab settings under **Admin → System Configuration → General Configuration**.
    12. If the lab uses the interfacing tool, restore its database too.

        1. In a terminal, type `sudo cp` followed by a space. Do not press Enter
           yet.
        2. Drag the newest file starting with `interfacing-` from the `db`
           folder onto the terminal. Then type ` /tmp/` and press Enter.
        3. Restore it into the interfacing database:

            ```bash
            cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing /tmp/interfacing-*
            ```

        4. Delete the copy:

            ```bash
            sudo rm /tmp/interfacing-*
            ```

        If step 3 reports that the profile `interfacing` does not exist, set up
        the interfacing tool first with
        [Setting up the interfacing tool](setting-up-interfacing-tool.md), then
        repeat step 3. If it reports that it cannot open an encrypted file,
        contact support with the file name.

=== "Backups on a server or share"

    Use this when the old machine sent its backups to another Linux machine or a
    Windows shared folder.

    ### Fetch the backups

    1. On the new machine, open a terminal and run:

        ```bash
        cd ~ && wget -O restore-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/restore-backup.sh
        sudo bash restore-backup.sh
        ```

    2. Answer where the backups are stored, and sign in when asked.
    3. Choose the lab from the list.
    4. Choose **Just the database backups**.
    5. Press Enter to accept the folder it offers.
    6. Wait for the copy to finish. Near the end it prints a two-line command
       starting with `cd ~ && wget -O setup.sh`. Select both lines and press
       **Ctrl+Shift+C** to copy them.

    ### Install and restore

    7. Press **Ctrl+Shift+V** to paste the command, then press Enter.
    8. Answer the installer's questions:

        | Question | Answer |
        | --- | --- |
        | Installation directory | Press Enter. |
        | Which backup should be restored? | Press Enter. The newest backup is on top and already selected. |
        | What is this machine? | **Lab machine (LIS)**. |
        | Remote STS URL | The STS address the old machine used. Leave it empty if the lab has no STS. |
        | New MySQL root password | A new password for this machine, typed twice. Write it down. |
        | Is this correct? | Check the summary, then press Enter (Yes). |

    9. Wait 10 to 20 minutes. The installer ends with `Setup complete`.

        ??? failure "If it stops with `Failed to decrypt`"

            The backup uses a key held by the STS. Ask the STS administrator for a
            one-time recovery token. They run this on the STS:

            ```bash
            cd /var/www/intelis && sudo -u www-data php bin/backup-key-admin.php approve --lab <lab-id>
            ```

            Then paste the command from step 6 again. Before pressing Enter, add
            the STS address and the token at the end:

            ```bash
            --sts-url https://sts.example.org --recovery-token ABCD-EFGH-JKMN-PQRS
            ```

            The second run asks `Reuse your previous setup answers and skip the prompts?`.
            Press Enter (Yes) to reuse the answers. If it says
            `An existing database was found`, choose **Keep a copy, then start fresh**.

    ### Check the lab

    10. Open InteLIS in the browser.
    11. Log in with an administrator account from the old machine. Do not create
        a new one. The restored database already holds the users, the lab
        settings and all the data.
    12. Check the lab settings under **Admin → System Configuration → General Configuration**.
    13. If the lab uses the interfacing tool, restore its database too.

        1. Copy the newest interfacing backup out of the fetched folder:

            ```bash
            sudo bash -c 'cp "$(ls -t /var/intelis-restore/*/db/interfacing-* | head -1)" /tmp/'
            ```

        2. Restore it into the interfacing database:

            ```bash
            cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing /tmp/interfacing-*
            ```

        3. Delete the copy:

            ```bash
            sudo rm /tmp/interfacing-*
            ```

        If step 2 reports that the profile `interfacing` does not exist, set up
        the interfacing tool first with
        [Setting up the interfacing tool](setting-up-interfacing-tool.md), then
        repeat step 2. If it reports that it cannot open an encrypted file,
        contact support with the file name.
