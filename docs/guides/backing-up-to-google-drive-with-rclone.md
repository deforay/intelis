# Backing up to Google Drive with Rclone on Ubuntu

This guide covers labs that have no backup server and no Windows share to send
backups to. Where either exists, `intelis backup setup` is the supported route:
it offers another Linux machine over SSH, a shared Windows folder over SMB, or a
USB drive, and `intelis restore` can read all three back.

Google Drive is not one of those destinations. Nothing in InteLIS restores from
Drive automatically, so a lab using this method restores by downloading the files
by hand and then running the normal restore. Read [Restoring from
backup](restoring-from-backup.md) before relying on it.

## 1. Install Rclone

```bash
sudo apt install rclone
```

## 2. Configure Rclone

Start the configuration process:

```bash
rclone config
```

Answer the prompts as follows:

1. Select `n` for a new remote.
2. Name the remote `gdrive`.
3. Choose `drive` for Google Drive storage.
4. Accept the default client ID and secret by pressing Enter.
5. Keep the default scope settings.
6. Select `1` for full file access.
7. Leave the advanced options empty and select `n` for advanced config.
8. Select `n` for auto config.
9. Open the URL shown, authenticate with Google, and authorize Rclone.
10. Paste the authorization code into the terminal.
11. Select `n` for team drive.
12. Confirm and save with `y`.

## 3. Choose a folder that belongs to this lab alone

Every installation must upload to its own folder. Two machines sharing one folder
overwrite each other's backups, and the loss is silent.

Pick a name that identifies the machine, not just the lab, for example
`intelis/<lab>-<machine>`. The rest of this guide calls it `remote_dir`.

Confirm the folder is either empty or holds only this machine's earlier backups:

```bash
rclone lsf gdrive:intelis/LABNAME-01
```

An unexpected listing means the name is already in use. Choose another before
continuing.

## 4. Create a backup script

```bash
sudo nano /usr/local/bin/intelis-gdrive-backup.sh
```

Paste this content and change `remote_dir` to the folder chosen above. On an
installation made before the rename, set `source_dir` to `/var/www/vlsm`.

```bash
#!/bin/bash
set -Eeuo pipefail

source_dir="/var/www/intelis"
remote_name="gdrive"
remote_dir="intelis/LABNAME-01"

# A fresh dump first. Uploading without this copies whatever dump was last
# written, which on a new machine is nothing at all. The absolute path matters:
# cron runs with a minimal PATH that does not always include /usr/local/bin.
/usr/local/bin/intelis backup

# copy, not sync: sync deletes anything at the destination that is absent from
# the source, which would erase older backups and, if remote_dir is ever wrong,
# erase whatever else is in that folder.
rclone copy "$source_dir/backups" "$remote_name:$remote_dir/backups"
```

Save and exit (Ctrl+X, Y, Enter), then make it executable:

```bash
sudo chmod +x /usr/local/bin/intelis-gdrive-backup.sh
```

## 5. Run it once and verify

```bash
sudo /usr/local/bin/intelis-gdrive-backup.sh
```

Then confirm a current database dump actually arrived:

```bash
rclone lsl gdrive:intelis/LABNAME-01/backups/db
```

The listing must contain a `vlsm-*` file dated today. A backup folder without one
holds no database, whatever else it contains.

## 6. Automate backups

Add the schedule without disturbing any other scheduled work on the machine:

```bash
sudo crontab -l 2>/dev/null | grep -Fv intelis-gdrive-backup.sh > /tmp/intelis-cron
printf '@reboot /usr/local/bin/intelis-gdrive-backup.sh\n0 */6 * * * /usr/local/bin/intelis-gdrive-backup.sh\n' >> /tmp/intelis-cron
sudo crontab /tmp/intelis-cron
rm -f /tmp/intelis-cron
```

Verify the result, which must list the two new lines alongside everything that
was already scheduled:

```bash
sudo crontab -l
```

This runs a backup at restart and every six hours.

## Restoring from Google Drive

There is no Drive source in `intelis restore`. Recovery is manual:

1. Download the newest `vlsm-*` dump from `gdrive:<remote_dir>/backups/db`.
2. Follow [Restoring from backup](restoring-from-backup.md), pointing the restore
   at the downloaded file.

Database backups are encrypted by default, so the recovery key or passphrase is
required to read them. A lab that cannot produce that key cannot restore, so
verify a test restore on a spare machine before depending on this method.
