# Backing up InteLIS to a Windows Machine (Same Network)

Keep a copy of InteLIS in a shared folder on a Windows PC, updated automatically
every 8 hours. No software is installed on Windows.

One script sets up every kind of backup destination. This guide covers sending
the backup to a Windows shared folder. To send it to another Linux machine
instead, see [Backing up to Another Linux Machine](backing-up-to-remote-server.md).

!!! info "How it works"
    The Windows folder is mounted onto the Linux server like a local disk. The
    server copies InteLIS into it with `rsync`. Only changed files are copied
    each time, so backups after the first one are fast.

---

## Part A: Prepare the Windows machine (one time)

Do these five things on the Windows PC first. They take about five minutes.

### 1. Create the backup folder

Create a folder, for example:

```text
C:\InteLIS-Backups
```

### 2. Share the folder

1. Right-click the folder, then choose **Properties**, **Sharing**, **Advanced Sharing**.
2. Tick **Share this folder**.
3. Set the **Share name** to `InteLIS-Backups`. The name must not contain spaces.
4. Click **Permissions**, then give the backup user (created next) **Change** and **Read**.
5. Click **OK** on all windows.

### 3. Create a dedicated Windows user for backups

Do not use a personal login. Create a separate local account:

1. Press `Win + R`, type `lusrmgr.msc`, then press Enter.
2. Under **Users**, right-click, then choose **New User**.
3. Set the username to `lisbackup`. Set a strong password.
4. Untick *User must change password*. Tick *Password never expires*.
5. Create the user, then grant it access to the share as in step 2.4 above.

### 4. Give the Windows machine a fixed IP address

The Linux server needs to find the same address every time. Set a static IP on
the Windows PC, or reserve its IP in the router's DHCP settings. Note the
address down, for example `192.168.1.50`.

### 5. Allow file sharing through the firewall

Open Control Panel, then **Windows Defender Firewall**, then **Allow an app**.
Confirm that **File and Printer Sharing** is ticked for **Private** networks.

Part B needs three things from this part:

| What | Example |
|------|---------|
| Windows IP address | `192.168.1.50` |
| Share name | `InteLIS-Backups` |
| Username and password | `lisbackup` and its password |

---

## Part B: Set up the InteLIS server

On the InteLIS server, run:

```bash
sudo intelis backup setup
```

??? info "If `intelis` is not recognised"

    The installation predates the command. Fetch the script directly instead:

    ```bash
    cd ~
    wget -O remote-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/remote-backup.sh
    sudo chmod u+x remote-backup.sh
    sudo ./remote-backup.sh
    ```

The script asks these questions:

| Question | Answer |
|----------|--------|
| Lab name or lab code | A short name for this lab, such as `centrallab` |
| InteLIS folder path | Press Enter to accept the detected path |
| Where should the backup be sent | `2` for a Windows shared folder |
| Windows hostname or IP | The address from Part A |
| Name of the shared folder | `InteLIS-Backups` |
| Windows username and password | The account from Part A |

The script works out which SMB version the Windows machine speaks. There is
nothing to choose.

Wrong answers do not end the script. It reports the problem and asks again.

## Confirm it worked

The script runs the first backup before it finishes, and reports the result. To
check again at any time:

```bash
sudo intelis backup status
```

A working setup looks like this:

```text
Lab            : centrallab (centrallab-3f9a2b1c)
Backing up to  : //192.168.1.50/InteLIS-Backups -> /mnt/intelis-backup/backups/centrallab-3f9a2b1c
Last good backup: 2026-08-07T09:14:22Z (12 minutes ago)
Size on backup  : 4.2G
Last attempt    : succeeded in 96s
Schedule        : every 8 hours and after every restart
```

If the last good backup is more than a day old, the status output says so.

## Where the backup lands

Each installation gets its own folder, named after the lab and a short
identifier unique to that machine:

```text
C:\InteLIS-Backups\backups\centrallab-3f9a2b1c\
```

Two labs that choose the same name still get separate folders. One lab can never
overwrite another lab's backup.

The backup holds the whole InteLIS folder, including the database dumps written
to `backups/db` every 6 hours. It leaves out files that are rebuilt on install:
`vendor/`, caches, logs, and version-control folders.

## Commands

| Task | Command |
|------|---------|
| Check the last backup | `sudo intelis backup status` |
| Test the connection without copying | `sudo intelis backup test` |
| Back up right now | `sudo intelis backup` |
| Watch a backup as it runs | `tail -f /var/log/intelis-backup.log` |
| Stop the scheduled backups | `sudo intelis backup disable` |
| Start them again | `sudo intelis backup enable` |
| Change any setting | Re-run `sudo intelis backup setup` |

## Getting the backup back

See [Restoring from a Backup](restoring-from-backup.md).

## Troubleshooting

!!! failure "Could not connect to the share"
    Confirm the Windows PC is on and reachable. Run `ping 192.168.1.50`. Re-check
    the share name and the username and password. Confirm that File and Printer
    Sharing is allowed through the Windows firewall.

!!! failure "Connected, but the folder is read-only"
    The Windows user needs the **Change** permission on the share, not only
    **Read**. See Part A, steps 2 and 3.

!!! warning "Share name has spaces"
    A share name with spaces breaks the Linux mount. The script refuses it.
    Re-share the folder with a name such as `InteLIS-Backups`.

!!! warning "The newest database dump is more than 24 hours old"
    The scheduled job that dumps the database has stopped. The file copy still
    works, so the backup looks healthy while the data inside it goes stale. Check
    that `cron.sh` is in the crontab on the InteLIS server.

!!! note "One copy is not a disaster-recovery plan"
    A Windows PC in the same room as the server does not survive a fire, a theft,
    or a ransomware attack. Add an off-site copy as well. See
    [Backing up to Google Drive with Rclone](backing-up-to-google-drive-with-rclone.md).

!!! info "The old script still works"
    `remote-backup-windows.sh` now fetches and runs `remote-backup.sh`. Choose
    option 2 when it asks where the backup should go.
