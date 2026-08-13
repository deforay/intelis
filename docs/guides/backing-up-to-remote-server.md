# Backing up InteLIS to Another Linux Machine

Keep a copy of InteLIS on a second Linux machine, updated automatically every
8 hours.

One script sets up every kind of backup destination. This guide covers sending
the backup to another Linux machine over SSH. To send it to a Windows shared
folder instead, see
[Backing up to a Windows Machine](backing-up-to-windows-machine.md).

## Before you start

- The backup machine runs Linux and is reachable from the InteLIS server.
- You know a username and password on the backup machine.
- You can run commands with `sudo` on the InteLIS server.

## Set up the backup

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

| Question | Example answer |
|----------|----------------|
| Lab name or lab code | `kigali-central` |
| InteLIS folder path | Press Enter to accept the detected path |
| Where should the backup be sent | `1` for another Linux machine |
| Username on the backup server | `lisbackup` |
| Hostname or IP of the backup server | `192.168.1.60` |
| SSH port | Press Enter to accept `22` |

The script asks for the backup machine's password once, to install a key. After
that it connects without a password.

Wrong answers do not end the script. It reports the problem and asks again.

## Confirm it worked

The script runs the first backup before it finishes, and reports the result. To
check again at any time:

```bash
sudo intelis backup status
```

A working setup looks like this:

```text
Lab            : kigali-central (kigali-central-3f9a2b1c)
Backing up to  : lisbackup@192.168.1.60:/home/lisbackup/backups/kigali-central-3f9a2b1c
Last good backup: 2026-08-07T09:14:22Z (12 minutes ago)
Size on backup  : 4.2G
Last attempt    : succeeded in 47s
Schedule        : every 8 hours and after every restart
```

If the last good backup is more than a day old, the status output says so.

## Where the backup lands

Each installation gets its own folder, named after the lab and a short
identifier unique to that machine:

```text
/home/lisbackup/backups/kigali-central-3f9a2b1c/
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

!!! failure "Cannot reach the backup server"
    Check that the machine is on and on the same network. Run
    `ping 192.168.1.60`. Check that its SSH port is open.

!!! failure "Could not install the key"
    The username or password is wrong, or the backup machine refuses password
    logins. Ask whoever runs that machine to allow password logins once, or to
    add the contents of `/root/.ssh/id_ed25519_intelis.pub` to the backup user's
    `~/.ssh/authorized_keys`.

!!! failure "The backup folder does not belong to this installation any more"
    The folder at the destination was replaced by another machine's backup. Re-run
    `sudo intelis backup setup` to set the destination up again.

!!! warning "The newest database dump is more than 24 hours old"
    The scheduled job that dumps the database has stopped. The file copy still
    works, so the backup looks healthy while the data inside it goes stale. Check
    that `cron.sh` is in the crontab on the InteLIS server.

!!! note "One copy is not a disaster-recovery plan"
    A backup machine in the same room as the server does not survive a fire or a
    theft. Add an off-site copy as well. See
    [Backing up to Google Drive with Rclone](backing-up-to-google-drive-with-rclone.md).
