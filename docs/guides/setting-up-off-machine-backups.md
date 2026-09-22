---
description: Send InteLIS backups every 8 hours to another Linux machine, a Windows shared folder or a USB drive, and confirm they work.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Setting Up Off-Machine Backups

Send a copy of InteLIS to another machine or drive, automatically, every 8 hours
and after every restart. The copy survives if the InteLIS machine fails.

!!! info "Storage needed"

    Use a backup machine or drive with at least 1 TB of space. That covers one
    STS and up to about 30 LIS machines, with 5 weeks of database history. For
    a larger network, allow 50 GB for each STS and 20 GB for each LIS machine,
    plus 30 GB for the operating system, and keep a quarter of the disk free.

Another Linux machine is the recommended place for backups. One backup machine
takes the backups of every lab: prepare it once, then run the setup on each
lab's InteLIS machine. Use a USB drive only when there is no other machine.

**Choose where the backups go, then follow its steps from top to bottom.**

=== "Another Linux machine"

    Use this when there is a second Linux machine on the same network. This is
    the recommended choice.

    ### Prepare the backup machine

    Do this once. Every lab then sends its backups to the same machine.

    1. On the backup machine, open a terminal and install the SSH server:

        ```bash
        sudo apt install openssh-server
        ```

    2. Find the backup machine's IP address:

        ```bash
        hostname -I
        ```

        Write down the first address, for example `192.168.1.60`.

    3. Write down the username and password used to manage the backup machine.
       Each lab's setup logs in with them once.

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
        | Where should the backup be sent? | **Another Linux machine on the network (recommended)**. |
        | Address of the backup machine | The address from step 2. |
        | This machine needs access to … How should it be given? | **Log in as the backup machine's administrator (recommended)**. |
        | Administrator account on the backup machine | The username from step 3. |

        ??? failure "If it says `Cannot reach …`"

            Check the backup machine is switched on and on the same network.
            Check the address with `ping 192.168.1.60`, using the address from
            step 2. Check the SSH server from step 1 is installed. Then choose
            **Yes** at **Try again?**.

        ??? info "If the backup machine uses another SSH port or account"

            Write them into the address: `192.168.1.60:2222` for port 2222,
            or `backup@192.168.1.60` for the account `backup`. Without them,
            setup uses port 22 and the account `lisbackup`.

    6. When asked for a password, type the password from step 3 and press
       Enter. It can be asked twice: once to log in, and once more for
       `sudo`. The first lab creates the `lisbackup` account on the backup
       machine. Every later lab adds its own access to that account. Later
       backups need no password.

        ??? failure "If it says `Could not log in as … either`"

            The username or password is wrong, or the backup machine refuses
            that account's password. Check both on the backup machine. Setup
            asks **How should it be given?** again.

        ??? failure "If it says `adding the key failed`"

            The account logged in but is not allowed to use `sudo`. Choose
            **Log in as the backup machine's administrator** again and type
            an account that can.

        ??? info "Without the administrator's password"

            At **How should it be given?**, choose one:

            | Choice | Use it when |
            | --- | --- |
            | **Type the lisbackup password** | `lisbackup` was created on the backup machine with a password. |
            | **Add it by hand on the backup machine** | Someone else manages the backup machine. The script prints three commands. Send them to that person. When they have run them, choose **Yes** at **Has it been added? Check now?**. |

        ??? tip "Logging in to the backup machine by hand"

            Setup adds the backup machine to `/root/.ssh/config`, so
            `sudo ssh lisbackup@192.168.1.60` from the InteLIS machine uses the
            backup key. Use the address from step 2.

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
        History         : 2026-08-01 to 2026-08-07 (7 days)
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
        made. The copy still runs and looks healthy. Check the scheduler by
        following [Check the scheduled tasks are running](maintenance.md#check-the-scheduled-tasks-are-running).

    ### Other commands

    | Task | Command |
    | --- | --- |
    | Back up now | `intelis backup` |
    | Check the connection without copying | `intelis backup test` |
    | Watch a backup as it runs | `tail -f /var/log/intelis-backup.log` |
    | Stop the scheduled backups | `intelis backup disable` |
    | Start them again | `intelis backup enable` |
    | Change where backups go | `intelis backup setup`, then choose **Change where backups go**. The saved answers are offered; press Enter to keep one. |

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
        History         : 2026-08-01 to 2026-08-07 (7 days)
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
        made. The copy still runs and looks healthy. Check the scheduler by
        following [Check the scheduled tasks are running](maintenance.md#check-the-scheduled-tasks-are-running).

    ### Other commands

    | Task | Command |
    | --- | --- |
    | Back up now | `intelis backup` |
    | Check the connection without copying | `intelis backup test` |
    | Watch a backup as it runs | `tail -f /var/log/intelis-backup.log` |
    | Stop the scheduled backups | `intelis backup disable` |
    | Start them again | `intelis backup enable` |
    | Change where backups go | `intelis backup setup`, then choose **Change where backups go**. The saved answers are offered; press Enter to keep one. |

    To get the data back, see [Restoring from a Backup](restoring-from-backup.md).

=== "USB or external drive"

    Use this when there is no other machine to send backups to. The drive must
    stay plugged into the InteLIS machine.

    ### Set up the backup

    1. Plug the drive into the InteLIS machine.
    2. Open a terminal and run:

        ```bash
        intelis backup setup
        ```

        ??? info "If `intelis` is not recognised"

            The install is older. Download the setup script and run it:

            ```bash
            cd ~ && wget -O remote-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/remote-backup.sh
            sudo bash remote-backup.sh
            ```

    3. Answer the first questions:

        | Question | Answer |
        | --- | --- |
        | Lab name or lab code | A short name for this lab, such as `centrallab`. |
        | Where should the backup be sent? | **A USB or external drive plugged into this machine**. |

    4. At **Which drive should the backups go to?**, choose the USB drive.
       Each row shows the drive's name and size. The script connects the
       drive, and connects it again by itself after every restart.

        ??? failure "If it says `No drive was found apart from this machine's own disk`"

            The drive is not plugged in, or is not recognised. Plug it in, wait
            a few seconds, then choose **Look again**.

        ??? failure "If it says `This drive is formatted as FAT32`"

            FAT32 cannot hold a file of 4 GB or more, and database backups grow
            past that. Reformat the drive as exFAT or ext4. This erases
            everything on it. Open the **Disks** app, select the drive, then
            select **Format Partition**. Then choose **Look again**.

        ??? failure "If it says `The drive is open at …`"

            A window is showing the drive's files. Close it, then choose
            **Yes** at **Choose again?** and choose the drive again.

    5. Wait for the first backup to finish. It can take an hour or more. The
       script ends with `Backups are set up and the first one completed`.

        ??? failure "If it ends with `Setup finished, but the first backup failed`"

            The settings are saved. Read the error above the message, fix the
            cause, then run the backup again:

            ```bash
            intelis backup
            ```

    ### Confirm it works

    6. Run:

        ```bash
        intelis backup status
        ```

        A working backup looks like this:

        ```text
        Lab            : centrallab (centrallab-3f9a2b1c)
        Backing up to  : /mnt/intelis-usb/backups/centrallab-3f9a2b1c
        Last good backup: 2026-08-07T09:14:22Z (12 minutes ago)
        Size on backup  : 4.2G
        History         : 2026-08-01 to 2026-08-07 (7 days)
        Last attempt    : succeeded in 52s
        Schedule        : every 8 hours and after every restart
        ```

        Check these lines:

        | Line | Must show |
        | --- | --- |
        | Last good backup | A time less than 8 hours ago. |
        | Last attempt | `succeeded`. |
        | Schedule | `every 8 hours and after every restart`. |

        ??? failure "If the reason is `The backup drive is not plugged in`"

            Plug the drive in, then run `intelis backup`. The backup connects
            the drive by itself.

        ??? failure "If the reason is `The backup drive at … is not there`"

            The drive was set up by an older version, which relied on it being
            opened in the **Files** app. Run `intelis backup setup`, choose
            **Change where backups go**, and choose the drive from the list.

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
    /mnt/intelis-usb/backups/centrallab-3f9a2b1c/
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
        made. The copy still runs and looks healthy. Check the scheduler by
        following [Check the scheduled tasks are running](maintenance.md#check-the-scheduled-tasks-are-running).

    ### Other commands

    | Task | Command |
    | --- | --- |
    | Back up now | `intelis backup` |
    | Check the drive without copying | `intelis backup test` |
    | Watch a backup as it runs | `tail -f /var/log/intelis-backup.log` |
    | Stop the scheduled backups | `intelis backup disable` |
    | Start them again | `intelis backup enable` |
    | Change where backups go | `intelis backup setup`, then choose **Change where backups go**. The saved answers are offered; press Enter to keep one. |

    To get the data back, see [Restoring from a Backup](restoring-from-backup.md).

A backup in the same room as the InteLIS machine does not survive a fire or a
theft. To keep a second copy elsewhere, see
[Backing up to Google Drive with Rclone](backing-up-to-google-drive-with-rclone.md).
