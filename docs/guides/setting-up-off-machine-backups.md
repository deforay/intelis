# Setting Up Off-Machine Backups

Send a copy of InteLIS to another machine or drive, automatically, every 8 hours
and after every restart. The copy survives if the InteLIS machine fails.

**Choose where the backups go, then follow its steps from top to bottom.**

=== "Another Linux machine"

    Use this when there is a second Linux machine on the same network.

    ### Prepare the backup machine

    1. On the backup machine, open a terminal and install the SSH server:

        ```bash
        sudo apt install openssh-server
        ```

    2. Create an account for the backups. Set a strong password and write it
       down:

        ```bash
        sudo adduser lisbackup
        ```

    3. Find the backup machine's IP address:

        ```bash
        hostname -I
        ```

        Write down the first address, for example `192.168.1.60`.

    ### Set up the backup on the InteLIS machine

    4. On the InteLIS machine, open a terminal and run:

        ```bash
        intelis backup setup
        ```

        ??? info "If `intelis` is not recognised"

            The install is older. Download the setup script and run it:

            ```bash
            cd ~ && wget -O remote-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/remote-backup.sh
            sudo bash remote-backup.sh
            ```

    5. Answer the questions:

        | Question | Answer |
        | --- | --- |
        | Lab name or lab code | A short name for this lab, such as `kigali-central`. |
        | InteLIS folder path | Press Enter. |
        | Where should the backup be sent? | **Another Linux machine on the network**. |
        | Username on the backup server | `lisbackup` |
        | Hostname or IP of the backup server | The address from step 3. |
        | SSH port | Press Enter (`22`). |

        ??? failure "If it says `Cannot reach … on port 22`"

            Check the backup machine is switched on and on the same network.
            Check the address with `ping 192.168.1.60`, using the address from
            step 3. Check the SSH server from step 1 is installed. Then choose
            **Yes** at **Try different details?**.

    6. When asked for `lisbackup`'s password, type the password from step 2 and
       press Enter. It is asked once. The script installs a key, and later
       backups connect with the key alone.

        ??? failure "If it says `Could not install the key`"

            The username or password is wrong, or the backup machine refuses
            password logins. Choose **Yes** at **Try again?** and check both.

            If the backup machine refuses password logins, ask whoever runs it
            to add the contents of `/root/.ssh/id_ed25519_intelis.pub` from the
            InteLIS machine to `~/.ssh/authorized_keys` of `lisbackup`.

    7. Wait for the first backup to finish. It can take an hour or more. The
       script ends with `Backups are set up and the first one completed`.

        ??? failure "If it ends with `Setup finished, but the first backup failed`"

            The settings are saved. Read the error above the message, fix the
            cause, then run the backup again:

            ```bash
            intelis backup
            ```

    ### Confirm it works

    8. Run:

        ```bash
        intelis backup status
        ```

        A working backup looks like this:

        ```text
        Lab            : kigali-central (kigali-central-3f9a2b1c)
        Backing up to  : lisbackup@192.168.1.60:/home/lisbackup/backups/kigali-central-3f9a2b1c
        Last good backup: 2026-08-07T09:14:22Z (12 minutes ago)
        Size on backup  : 4.2G
        Last attempt    : succeeded in 47s
        Schedule        : every 8 hours and after every restart
        ```

        Check these lines:

        | Line | Must show |
        | --- | --- |
        | Last good backup | A time less than 8 hours ago. |
        | Last attempt | `succeeded`. |
        | Schedule | `every 8 hours and after every restart`. |

        ??? failure "If `Last attempt` shows `FAILED`"

            The `Reason` line below it gives the cause. Fix it, then run
            `intelis backup`. The full record is in
            `/var/log/intelis-backup.log`.

        ??? failure "If `Schedule` shows `OFF`"

            Start the scheduled backups again:

            ```bash
            intelis backup enable
            ```

    Once a week, check the status the same way, then run:

    ```bash
    intelis health
    ```

    The backup line must not show a warning. If it mentions `newest DB dump`,
    the database backups have stopped; see the warning under
    **Where the backup lands**. Once
    every three months, restore the newest backup onto a spare or test machine
    by following [Restoring from a Backup](restoring-from-backup.md). A backup
    that has never been restored is not yet proven to work.

    ### Where the backup lands

    The backup is on the backup machine, in a folder named after the lab and a
    code unique to this InteLIS machine:

    ```text
    /home/lisbackup/backups/kigali-central-3f9a2b1c/
    ```

    It holds the whole InteLIS folder. The database backups are in `backups/db`
    and the settings backups in `backups/config`. It leaves out files rebuilt on
    install: `vendor/`, `node_modules/`, caches, logs, temporary files and
    version-control folders.

    The folder is a mirror of this machine, so it holds the newest database
    backups only, about the last 2 days. Files deleted on this machine are
    deleted from the mirror at the next run. Older database backups are kept
    next to it, in `.history/`:

    | Kept in `.history/` | How many |
    | --- | --- |
    | One database backup per day | The newest 7 days |
    | One database backup per week, after that | 4 more weeks |
    | The settings backup | One per week, for the same period |

    That is about 5 weeks in all. A wipe or reinstall of this machine does not
    touch `.history/`. To keep more, change `HISTORY_DAYS` and `HISTORY_WEEKS`
    in `/etc/intelis/backup.conf`.

    The backup also holds the settings, which include the database password.
    Keep the backup folder readable only by the people who manage InteLIS.

    Two labs with the same name still get separate folders. One lab never
    overwrites another lab's backup.

    ??? warning "If a backup warns that the newest database dump is old"

        The InteLIS scheduler has stopped, so no new database backups are being
        made. The copy still runs and looks healthy. Check the scheduler:

        ```bash
        systemctl status intelis.timer
        ```

        If it does not show `active`, start it:

        ```bash
        sudo systemctl enable --now intelis.timer
        ```

    ### Other commands

    | Task | Command |
    | --- | --- |
    | Back up now | `intelis backup` |
    | Check the connection without copying | `intelis backup test` |
    | Watch a backup as it runs | `tail -f /var/log/intelis-backup.log` |
    | Stop the scheduled backups | `intelis backup disable` |
    | Start them again | `intelis backup enable` |
    | Change any answer | `intelis backup setup`, then press Enter to keep each saved answer. |

    To get the data back, see [Restoring from a Backup](restoring-from-backup.md).

=== "Windows shared folder"

    Use this when there is a Windows computer on the same network. Nothing is
    installed on Windows.

    ### Prepare the Windows computer

    1. On the Windows computer, create this folder:

        ```text
        C:\InteLIS-Backups
        ```

    2. Press **Win+R**, type `lusrmgr.msc`, and press Enter.

        ??? failure "If Windows says it cannot find `lusrmgr.msc`"

            Windows Home editions do not have it. Open **Settings → Accounts →
            Other users** and select **Add account**. Select
            **I don't have this person's sign-in information**, then
            **Add a user without a Microsoft account**. Create the user from
            step 3 there, then carry on at step 5.

    3. Right-click **Users** and select **New User**. Set the user name to
       `lisbackup` and set a strong password. Write the password down.
    4. Untick **User must change password at next logon**. Tick
       **Password never expires**. Select **Create**.
    5. Right-click the `C:\InteLIS-Backups` folder and select **Properties**.
       Open the **Sharing** tab and select **Advanced Sharing**.
    6. Tick **Share this folder**. Set **Share name** to `InteLIS-Backups`.
       The name must not contain spaces.
    7. Select **Permissions**. Add `lisbackup`, then tick **Change** and
       **Read** under **Allow**. Select **OK** on each window.
    8. Give the Windows computer a fixed IP address. Either set a static
       address on it, or reserve its address in the router. Write the address
       down, for example `192.168.1.50`.
    9. Open **Control Panel → Windows Defender Firewall → Allow an app or
       feature through Windows Defender Firewall**. Check that
       **File and Printer Sharing** is ticked under **Private**.

    ### Set up the backup on the InteLIS machine

    10. On the InteLIS machine, open a terminal and run:

        ```bash
        intelis backup setup
        ```

        ??? info "If `intelis` is not recognised"

            The install is older. Download the setup script and run it:

            ```bash
            cd ~ && wget -O remote-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/remote-backup.sh
            sudo bash remote-backup.sh
            ```

    11. Answer the questions:

        | Question | Answer |
        | --- | --- |
        | Lab name or lab code | A short name for this lab, such as `centrallab`. |
        | InteLIS folder path | Press Enter. |
        | Where should the backup be sent? | **A shared folder on a Windows machine**. |
        | Windows hostname or IP | The address from step 8. |
        | Name of the shared folder | `InteLIS-Backups` |
        | Windows username | `lisbackup` |
        | Windows password for lisbackup | The password from step 3. |

        ??? failure "If it says `Could not connect to //…`"

            Check the Windows computer is switched on and not asleep. Check the
            address from step 8, the share name, the username and the password.
            Check step 9. Then choose **Yes** at **Try again?**.

        ??? failure "If it says `Connected, but the folder is read-only`"

            On the Windows computer, repeat step 7 and make sure **Change** is
            ticked. Then choose **Yes** at **Try again?**.

        ??? failure "If it says `The share name contains a space`"

            On the Windows computer, repeat steps 5 and 6 with a share name
            without spaces, such as `InteLIS-Backups`. The script asks the
            questions again.

    12. Wait for the first backup to finish. It can take an hour or more. The
        script ends with `Backups are set up and the first one completed`.

        ??? failure "If it ends with `Setup finished, but the first backup failed`"

            The settings are saved. Read the error above the message, fix the
            cause, then run the backup again:

            ```bash
            intelis backup
            ```

    ### Confirm it works

    13. Run:

        ```bash
        intelis backup status
        ```

        A working backup looks like this:

        ```text
        Lab            : centrallab (centrallab-3f9a2b1c)
        Backing up to  : //192.168.1.50/InteLIS-Backups -> /mnt/intelis-backup/backups/centrallab-3f9a2b1c
        Last good backup: 2026-08-07T09:14:22Z (12 minutes ago)
        Size on backup  : not measured
        Last attempt    : succeeded in 96s
        Schedule        : every 8 hours and after every restart
        ```

        Check these lines:

        | Line | Must show |
        | --- | --- |
        | Last good backup | A time less than 8 hours ago. |
        | Last attempt | `succeeded`. |
        | Schedule | `every 8 hours and after every restart`. |

        ??? failure "If `Last attempt` shows `FAILED`"

            The `Reason` line below it gives the cause. A Windows computer that
            is switched off or asleep is the usual one. Fix it, then run
            `intelis backup`. The full record is in
            `/var/log/intelis-backup.log`.

        ??? failure "If `Schedule` shows `OFF`"

            Start the scheduled backups again:

            ```bash
            intelis backup enable
            ```

    Once a week, check the status the same way, then run:

    ```bash
    intelis health
    ```

    The backup line must not show a warning. If it mentions `newest DB dump`,
    the database backups have stopped; see the warning under
    **Where the backup lands**. Once
    every three months, restore the newest backup onto a spare or test machine
    by following [Restoring from a Backup](restoring-from-backup.md). A backup
    that has never been restored is not yet proven to work.

    ### Where the backup lands

    The backup is on the Windows computer, in a folder named after the lab and
    a code unique to this InteLIS machine:

    ```text
    C:\InteLIS-Backups\backups\centrallab-3f9a2b1c\
    ```

    It holds the whole InteLIS folder. The database backups are in `backups\db`
    and the settings backups in `backups\config`. It leaves out files rebuilt on
    install: `vendor/`, `node_modules/`, caches, logs, temporary files and
    version-control folders.

    The folder is a mirror of this machine, so it holds the newest database
    backups only, about the last 2 days. Files deleted on this machine are
    deleted from the mirror at the next run. Older database backups are kept
    next to it, in `.history/`:

    | Kept in `.history/` | How many |
    | --- | --- |
    | One database backup per day | The newest 7 days |
    | One database backup per week, after that | 4 more weeks |
    | The settings backup | One per week, for the same period |

    That is about 5 weeks in all. A wipe or reinstall of this machine does not
    touch `.history/`. To keep more, change `HISTORY_DAYS` and `HISTORY_WEEKS`
    in `/etc/intelis/backup.conf`.

    The backup also holds the settings, which include the database password.
    Keep the backup folder readable only by the people who manage InteLIS.

    Two labs with the same name still get separate folders. One lab never
    overwrites another lab's backup.

    ??? warning "If a backup warns that the newest database dump is old"

        The InteLIS scheduler has stopped, so no new database backups are being
        made. The copy still runs and looks healthy. Check the scheduler:

        ```bash
        systemctl status intelis.timer
        ```

        If it does not show `active`, start it:

        ```bash
        sudo systemctl enable --now intelis.timer
        ```

    ### Other commands

    | Task | Command |
    | --- | --- |
    | Back up now | `intelis backup` |
    | Check the connection without copying | `intelis backup test` |
    | Watch a backup as it runs | `tail -f /var/log/intelis-backup.log` |
    | Stop the scheduled backups | `intelis backup disable` |
    | Start them again | `intelis backup enable` |
    | Change any answer | `intelis backup setup`, then press Enter to keep each saved answer. |

    To get the data back, see [Restoring from a Backup](restoring-from-backup.md).

=== "USB or external drive"

    Use this when there is no other machine to send backups to. The drive must
    stay plugged into the InteLIS machine.

    ### Set up the backup

    1. Plug the drive into the InteLIS machine.
    2. Open the **Files** app and select the drive in the left sidebar. This
       connects it.
    3. Open a terminal and run:

        ```bash
        intelis backup setup
        ```

        ??? info "If `intelis` is not recognised"

            The install is older. Download the setup script and run it:

            ```bash
            cd ~ && wget -O remote-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/remote-backup.sh
            sudo bash remote-backup.sh
            ```

    4. Answer the first questions:

        | Question | Answer |
        | --- | --- |
        | Lab name or lab code | A short name for this lab, such as `centrallab`. |
        | InteLIS folder path | Press Enter. |
        | Where should the backup be sent? | **A USB or external drive plugged into this machine**. |

    5. The script lists the drives. Find the USB drive by its size. At
       **Folder on the drive to back up into**, type the path shown in its
       `MOUNTPOINT` column, for example `/media/labuser/BACKUP`, and press
       Enter.

        ??? failure "If it says `does not exist. Is the drive plugged in and mounted?`"

            The path is mistyped, or the drive is not connected. Repeat step 2,
            then type the path again.

        ??? failure "If it says `is on the same disk as the installation`"

            The path is on the InteLIS machine's own disk, not on the USB drive.
            Choose **No** at **Use it anyway?** and type the USB drive's path.

    6. Wait for the first backup to finish. It can take an hour or more. The
       script ends with `Backups are set up and the first one completed`.

        ??? failure "If it ends with `Setup finished, but the first backup failed`"

            The settings are saved. Read the error above the message, fix the
            cause, then run the backup again:

            ```bash
            intelis backup
            ```

    ### Confirm it works

    7. Run:

        ```bash
        intelis backup status
        ```

        A working backup looks like this:

        ```text
        Lab            : centrallab (centrallab-3f9a2b1c)
        Backing up to  : /media/labuser/BACKUP/backups/centrallab-3f9a2b1c
        Last good backup: 2026-08-07T09:14:22Z (12 minutes ago)
        Size on backup  : 4.2G
        Last attempt    : succeeded in 52s
        Schedule        : every 8 hours and after every restart
        ```

        Check these lines:

        | Line | Must show |
        | --- | --- |
        | Last good backup | A time less than 8 hours ago. |
        | Last attempt | `succeeded`. |
        | Schedule | `every 8 hours and after every restart`. |

        ??? failure "If the reason is `The backup drive at … is not there`"

            The drive was unplugged, or it was not connected after a restart.
            Plug it in, repeat step 2, then run `intelis backup`.

        ??? failure "If `Last attempt` shows `FAILED` for another reason"

            The `Reason` line below it gives the cause. Fix it, then run
            `intelis backup`. The full record is in
            `/var/log/intelis-backup.log`.

        ??? failure "If `Schedule` shows `OFF`"

            Start the scheduled backups again:

            ```bash
            intelis backup enable
            ```

    Once a week, check the status the same way, then run:

    ```bash
    intelis health
    ```

    The backup line must not show a warning. If it mentions `newest DB dump`,
    the database backups have stopped; see the warning under
    **Where the backup lands**. Once
    every three months, restore the newest backup onto a spare or test machine
    by following [Restoring from a Backup](restoring-from-backup.md). A backup
    that has never been restored is not yet proven to work.

    ### Where the backup lands

    The backup is on the drive, in a folder named after the lab and a code
    unique to this InteLIS machine:

    ```text
    /media/labuser/BACKUP/backups/centrallab-3f9a2b1c/
    ```

    It holds the whole InteLIS folder. The database backups are in `backups/db`
    and the settings backups in `backups/config`. It leaves out files rebuilt on
    install: `vendor/`, `node_modules/`, caches, logs, temporary files and
    version-control folders.

    The folder is a mirror of this machine, so it holds the newest database
    backups only, about the last 2 days. Files deleted on this machine are
    deleted from the mirror at the next run. Older database backups are kept
    next to it, in `.history/`:

    | Kept in `.history/` | How many |
    | --- | --- |
    | One database backup per day | The newest 7 days |
    | One database backup per week, after that | 4 more weeks |
    | The settings backup | One per week, for the same period |

    That is about 5 weeks in all. A wipe or reinstall of this machine does not
    touch `.history/`. To keep more, change `HISTORY_DAYS` and `HISTORY_WEEKS`
    in `/etc/intelis/backup.conf`.

    The backup also holds the settings, which include the database password.
    Keep the backup folder readable only by the people who manage InteLIS.

    Two labs with the same name still get separate folders. One lab never
    overwrites another lab's backup.

    ??? warning "If a backup warns that the newest database dump is old"

        The InteLIS scheduler has stopped, so no new database backups are being
        made. The copy still runs and looks healthy. Check the scheduler:

        ```bash
        systemctl status intelis.timer
        ```

        If it does not show `active`, start it:

        ```bash
        sudo systemctl enable --now intelis.timer
        ```

    ### Other commands

    | Task | Command |
    | --- | --- |
    | Back up now | `intelis backup` |
    | Check the drive without copying | `intelis backup test` |
    | Watch a backup as it runs | `tail -f /var/log/intelis-backup.log` |
    | Stop the scheduled backups | `intelis backup disable` |
    | Start them again | `intelis backup enable` |
    | Change any answer | `intelis backup setup`, then press Enter to keep each saved answer. |

    To get the data back, see [Restoring from a Backup](restoring-from-backup.md).

A backup in the same room as the InteLIS machine does not survive a fire or a
theft. To keep a second copy elsewhere, see
[Backing up to Google Drive with Rclone](backing-up-to-google-drive-with-rclone.md).
