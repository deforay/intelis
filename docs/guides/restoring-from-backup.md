# Restoring from a Backup

Get InteLIS data back from the backup server, Windows share, or drive that
`remote-backup.sh` writes to.

One script fetches the backup, whichever destination it went to. It lists every
lab stored there, copies the chosen one back to this machine, checks the database
backups it can open, and then either restores the database or prints the command
that rebuilds the machine.

Encrypted backups are listed but not opened. Database backups are encrypted by
default, so their names end in `.gpg`, and the script reports those as present
without testing them. A damaged encrypted archive is therefore only discovered at
the moment of restore. Confirm the key is available before relying on one.

## Before starting

- The lab whose backup is needed is known.
- The backup destination is reachable from this machine.
- Commands can be run with `sudo`.

To rebuild a machine from scratch, read
[Migrating From One Ubuntu Machine to Another](migrating-ubuntu-machines.md)
first. That guide covers the whole rebuild. This one covers fetching the backup.

## Fetch the backup

```bash
intelis restore
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

| Option | Copies | Copied to | Use it when |
|--------|--------|-----------|-------------|
| 1 | The database backups only | `/root/intelis-restore/<lab>/` | Rebuilding a machine |
| 2 | Everything, including uploads and attachments | `/root/intelis-restore/<lab>/backups/db/` for the dumps, with the rest of the installation alongside | Files are missing as well as data |

Both options only fetch. Neither writes anything into a live installation, so
option 2 does not put uploads or attachments back by itself. Restoring those
files is a separate, manual step described below.

To see what is stored without copying anything, run
`intelis restore --list`.

## Put the data back

What happens next depends on the machine.

### If InteLIS is already installed

The script offers to restore the database in place. It takes a safety copy of
the current database first, so the restore can be undone.

Answer `y` to restore. The script restores the newest main-database backup, then
applies any pending database migrations.

To restore a specific backup instead of the newest one, answer `n` and run:

```bash
# after option 1
cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore /root/intelis-restore/<lab>/<file>

# after option 2, the dumps sit one level down
cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore /root/intelis-restore/<lab>/backups/db/<file>
```

If the interfacing database is in use, restore its backup separately. Those
files start with `interfacing-`.

### Putting uploaded files back

The script restores the database only. Uploads and attachments fetched by option 2
stay in the staging directory until they are copied across by hand.

1. Confirm what is about to be written over, and that the staging copy holds what
   is expected:

   ```bash
   ls /root/intelis-restore/<lab>/public/uploads | head
   ls /var/www/intelis/public/uploads | head
   ```

2. Copy the files across without deleting anything already in place. Run it first
   as a dry run and read the list:

   ```bash
   sudo rsync -a --dry-run /root/intelis-restore/<lab>/public/uploads/ /var/www/intelis/public/uploads/
   sudo rsync -a /root/intelis-restore/<lab>/public/uploads/ /var/www/intelis/public/uploads/
   ```

   Do not add `--delete`. The live directory can hold files added since the
   backup, and they would be removed.

3. Repair ownership, or the web server cannot read what was just restored:

   ```bash
   sudo intelis provision
   ```

4. Open a result PDF or an attachment in the application to confirm the files are
   served.

### If InteLIS is not installed yet

The script prints the command that installs the stack and restores the backup in
one step:

```bash
cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)" \
  && sudo bash setup.sh --db latest:/root/intelis-restore/<lab>
```

After option 2, the dumps are one level down, so the path is
`latest:/root/intelis-restore/<lab>/backups/db`.

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
    The destination holds no lab folders. Confirm the connection is to the right
    machine or drive. On the InteLIS server, run
    `intelis backup status` to see where its backups go.

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
