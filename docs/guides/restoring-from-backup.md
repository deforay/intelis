# Restoring from a Backup

Get InteLIS data back from the backup server, Windows share, or drive that
`remote-backup.sh` writes to.

One script fetches the backup, whichever destination it went to. It lists every
lab stored there, copies the one you choose back to this machine, checks that
the database backups are readable, and then either restores the database or
prints the command that rebuilds the machine.

## Before you start

- You know which lab's backup you need.
- You can reach the backup destination from this machine.
- You can run commands with `sudo`.

To rebuild a machine from scratch, read
[Migrating From One Ubuntu Machine to Another](migrating-ubuntu-machines.md)
first. That guide covers the whole rebuild. This one covers fetching the backup.

## Fetch the backup

```bash
sudo intelis restore
```

??? info "If `intelis` is not recognised"

    On a machine with no InteLIS on it yet, fetch the script directly:

    ```bash
    cd ~
    wget -O restore-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/restore-backup.sh
    sudo chmod u+x restore-backup.sh
    sudo ./restore-backup.sh
    ```

On the machine that made the backups, the script reads the saved settings and
connects without asking. On a replacement machine, it asks where the backup is
stored, in the same way `remote-backup.sh` does.

The script then lists every lab it finds:

```text
   1) kigali-central-3f9a2b1c
      lab: kigali-central  (machine: lis-server-01)
      backup last updated: 2026-08-07T09:14:22Z
      newest database dump: vlsm-20260807-091422.sql.zst

   2) huye-district-7c04e5aa
      lab: huye-district  (machine: lis-server-02)
      backup last updated: 2026-08-06T22:03:10Z
      newest database dump: vlsm-20260806-220310.sql.zst
```

Choose the lab by number. Then choose what to copy back:

| Option | Copies | Use it when |
|--------|--------|-------------|
| 1 | The database backups only | Rebuilding a machine |
| 2 | Everything, including uploads and attachments | Files are missing as well as data |

To see what is stored without copying anything, run
`sudo intelis restore --list`.

## Put the data back

What happens next depends on the machine.

### If InteLIS is already installed

The script offers to restore the database in place. It takes a safety copy of
the current database first, so the restore can be undone.

Answer `y` to restore. The script restores the newest main-database backup, then
applies any pending database migrations.

To restore a specific backup instead of the newest one, answer `n` and run:

```bash
cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore /root/intelis-restore/<lab>/<file>
```

If the interfacing database is in use, restore its backup separately. Those
files start with `interfacing-`.

### If InteLIS is not installed yet

The script prints the command that installs the stack and restores the backup in
one step:

```bash
cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)" \
  && sudo bash setup.sh --db latest:/root/intelis-restore/<lab>
```

If the backups are encrypted, their names end in `.gpg`. Give the new machine the
same MySQL root password as the old one, and the command above is all that is
needed. For the other options, see
[Migrating From One Ubuntu Machine to Another](migrating-ubuntu-machines.md).

## Confirm it worked

1. Log in with an administrator account that existed before the restore.
2. Open **Admin**, then **System Config**, and check the instance and lab settings.
3. Open a recent request and confirm its results are present.
4. If this instance syncs to an STS, run a **Force Sync** and watch it finish.

## Troubleshooting

!!! failure "There are no backups in this folder"
    The destination holds no lab folders. Confirm you connected to the right
    machine or drive. On the InteLIS server, run
    `sudo intelis backup status` to see where its backups go.

!!! failure "A backup file is damaged"
    The script reports which file failed its check. Choose an older backup from
    the same folder. Check the disk on the backup destination.

!!! warning "The newest database dump is old"
    The listing shows when each lab's backup was last updated, and the name of
    its newest database dump. A dump from weeks ago means the scheduled dump job
    on the source machine stopped running. Restore it, then check `cron.sh` on
    that machine.

!!! note "Encrypted backups need the key"
    A file ending in `.gpg` cannot be opened without the key. The script reports
    these without checking them. Recover the key before restoring. See
    [Migrating From One Ubuntu Machine to Another](migrating-ubuntu-machines.md).
