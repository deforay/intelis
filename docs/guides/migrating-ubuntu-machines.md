# Migrating From One Ubuntu Machine to Another

InteLIS backs up its databases automatically every 6 hours to
`<install-path>/backups/db/` — compressed `.sql.zst` files for both the main
(`vlsm-…`) and, if used, the interfacing (`interfacing-…`) database. Migration
normally reuses those backups directly, with no separate export step. Machines
that have no backups, or whose backups are stale, take one first: see
[Exporting by hand](#exporting-by-hand).

Backup encryption is off unless it was deliberately turned on for the lab, so on
most machines these files are plain `.sql.zst` and nothing extra is needed. If
the names end in `.sql.zst.gpg` they are encrypted; setup.sh restores those too,
see [If your backups are encrypted](#if-your-backups-are-encrypted-gpg) below.

!!! tip "If off-machine backups were set up, there is a shorter route"

    `intelis restore` on the new machine fetches the backup from wherever
    it was sent, checks the files are readable, and prints the exact install
    command below with the path already filled in. This page is the manual
    version of the same thing, for when the backups are on a drive in your hand.
    See [Restoring from a backup](restoring-from-backup.md).

## Start here: check what the old machine actually has

Before anything else, look at the backup folder on the **old** machine:

```bash
ls -lh /var/www/intelis/backups/db/
```

What comes back decides which route to take:

| What you see | What to do |
| --- | --- |
| A list of `.sql.zst` or `.sql.zst.gpg` files, the newest dated today or yesterday | Normal route. Go to [step 1](#1-put-the-backups-on-the-new-machine). |
| Nothing, "No such file or directory", or only old files | The install predates automatic backups, or the backup job stopped. Take a fresh export first: [Exporting by hand](#exporting-by-hand). |

If MySQL on the old machine is stopped and will not start, no export of any kind
is possible until that is fixed. Go to
[If the old machine's MySQL will not start](#if-the-old-machines-mysql-will-not-start)
before anything else.

!!! warning "Older printed instructions"

    Some copies of this guide in circulation start with `wget -O db-backup.sh …`
    and treat a manual export as the first step of every migration. That step is
    no longer needed on an install that has `backups/db/`, but it still works and
    is still supported. It is kept below under
    [Exporting by hand](#exporting-by-hand).

## 1. Put the backups on the new machine

Connect the drive that holds the old machine's `backups` folder, or copy that
folder onto the new machine (USB/external drive, or by mounting the old disk).
You only need `backups/db/`. Each backup is named after the database it came
from, so the main-database files start with `vlsm-` and the interfacing files
start with `interfacing-`:

```text
vlsm-20260615-095652.sql.zst                                    # unencrypted
vlsm-20260615-095652-ObzpDjNoe5NkHF0ootx2nYxxfD2wJoPU.sql.zst.gpg   # encrypted
```

Encrypted backups carry a random token in the filename. Keep the name intact.
The restore uses that token to rebuild the passphrase.

> Want the freshest possible snapshot and the old machine still runs? Force one
> first, then copy the new file across:
>
> ```bash
> intelis backup
> ```
>
> That takes a fresh dump and also sends it to wherever off-machine backups go,
> so the new machine can be built from either copy.

## 2. Install on the new machine and restore the latest backup

**Requirement:** Ubuntu 22.04 LTS or newer.

Point `--db latest:` at the folder where you placed the backups (e.g. the mounted
drive). setup.sh installs the stack and restores the newest backup it finds there
— `.sql.zst` / `.sql.gz` are imported as-is, no need to decompress or rename:

```bash
cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)" && sudo bash setup.sh --db latest:/media/USB/backups/db
```

- Replace `/media/USB/backups/db` with the actual path to your copied folder, e.g.
  `/media/<user>/<drive>/backups/db`, `~/Desktop/backups/db`, or
  `/mnt/old-disk/var/www/intelis/backups/db`.
- To restore one specific file instead of the newest, pass it directly:
  `sudo bash setup.sh --db /media/USB/vlsm-20260608-010012.sql.zst`

When prompted, enter the **new** machine's MySQL credentials and the STS URL.

## If your backups are encrypted (`.gpg`)

!!! tip "The old machine still runs? Skip this section entirely"

    Encryption protects a backup that travels or sits on a shelf. Moving to a
    machine standing next to the old one is neither. If the old machine still
    works, take a fresh **unencrypted** export instead of recovering a key:
    see [Exporting by hand](#exporting-by-hand). setup.sh reads that file with
    the same `--db` option, and no key is involved at any point.

    Recover the key only when the old machine is gone or dead and an encrypted
    backup is all that is left.

If the files end in `.sql.zst.gpg`, setup.sh still restores them with `--db` /
`--db latest:` exactly as above — it needs the key. Use whichever fits:

- **Easiest — use the same MySQL root password** on the new machine as the old
  one. setup.sh then derives the key automatically and the restore needs nothing
  extra. This is the recommended path for a straightforward move.

- **Recover the key from the STS** (when the new machine has a different MySQL
  password). Ask your STS administrator to approve a one-time key release for your
  lab — on the STS they run:

  ```bash
  cd /var/www/intelis && sudo -u www-data composer backup-key-admin approve --lab <your-lab-id>
  ```

  They give you the short token it prints. On the new machine:

  ```bash
  sudo bash setup.sh --db latest:/media/USB/backups/db \
      --sts-url https://your-sts.example.org --recovery-token ABCD-EFGH-JKMN-PQRS
  ```

- **Offline (STS unreachable)** — ask your STS administrator for the recovery code
  (on the STS: `sudo -u www-data composer backup-key-admin show-code --lab <id>`)
  and pass it directly:

  ```bash
  sudo bash setup.sh --db /media/USB/vlsm-….sql.zst.gpg --encryption-password '<recovery-code>'
  ```

> The STS-based recovery (token / recovery code) requires the STS to be running a
> release that includes the key-recovery support. setup.sh itself is always current
> (downloaded fresh), so the new machine never needs an upgrade first.

## 3. After install

The restored database already contains your users, lab/instance settings, and
data, so you do **not** create a new admin or re-select the lab:

- Browse to the instance and log in with your existing administrator account.
- Verify instance/lab settings under **Admin → System Config**.
- If connected to an STS, run a **Force Sync** and monitor until complete.

### Interfacing database (only if you use it)

`--db` restores the main database. If the interfacing database is in use, restore
its backup separately after install:

```bash
cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore /media/USB/backups/db/interfacing-20260615-095737.sql.zst
```

## Exporting by hand

Use this when `backups/db/` is empty or missing, when the newest backup there is
too old to move a lab onto, or when you simply want a guaranteed-fresh
**unencrypted** file so no key is needed at the other end. The old machine's
MySQL has to be running for any of it; if it is not, see the next section.

### On a working install

```bash
cd /var/www/intelis
sudo -u www-data php vendor/bin/db-tools backup --all --no-encrypt
```

`--all` covers the interfacing database as well, if the lab uses one. The files
land in `/var/www/intelis/backups/db/` alongside the automatic ones. Copy that
folder to the drive and carry on from [step 2](#2-install-on-the-new-machine-and-restore-the-latest-backup).

!!! note "If the lab has backup encryption switched on"

    A passphrase configured for the instance takes priority over `--no-encrypt`,
    so on those machines the file still comes out as `.sql.zst.gpg`. The
    extension tells you which you got. To force a plain file regardless:

    ```bash
    cd /var/www/intelis
    sudo -u www-data php vendor/bin/db-tools export backups/db/vlsm-manual.sql
    ```

    That writes an uncompressed `.sql`, which `--db` accepts like any other. Add
    the database name as a second argument for the interfacing database.

### If the install is too old or too broken to run db-tools

This route needs nothing but a working MySQL, so it also covers installs that
predate automatic backups. It is the step the older printed instructions
describe:

```bash
cd ~
wget -O db-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/db-backup.sh
sudo chmod u+x db-backup.sh
sudo ./db-backup.sh
```

- Enter the MySQL username and password when prompted.
- Choose the databases to export. The main one is normally `vlsm`; export
  `interfacing` as well if the lab uses the interfacing tool.
- Choose where to write them, for example the mounted drive.

It produces `.sql.gz` files, which setup.sh imports as they are. Carry on from
[step 2](#2-install-on-the-new-machine-and-restore-the-latest-backup), pointing
`--db` at the file it wrote.

!!! warning "Check the script finished"

    A dump interrupted part way through still leaves a file that looks
    reasonable, and restoring it produces a database that is missing recent
    records without ever saying so. The script prints a line naming every
    database it failed on and exits with an error, so read the last few lines
    before unplugging the drive. Nothing is safe to migrate until it says
    `Script completed`.

## If the old machine's MySQL will not start

A machine being replaced is often a machine that has already gone wrong, so this
is common. Nothing can be exported until MySQL runs, but in almost every case the
data itself is fine and only the server is refusing to come up.

Get the reason first. Do not reinstall or reformat anything before reading this:

```bash
sudo systemctl status mysql --no-pager -l
sudo tail -n 40 /var/log/mysql/error.log
df -h /
free -m
```

In order of how often it turns out to be the cause:

1. **The disk is full.** `df -h /` shows 100% or close to it. MySQL cannot write
   its logs, so it stops. Delete old backups and rotated logs, then start it
   again:

    ```bash
    sudo journalctl --vacuum-time=2d
    sudo systemctl start mysql
    ```

    Old `.sql.zst` / `.sql.gz` files under `backups/db/` are usually the largest
    thing on the disk. Copy them off to the drive you are migrating with before
    deleting any, and keep the newest one.

2. **The machine ran out of memory** and the kernel killed `mysqld`. The error
   log or `journalctl -k` mentions "Out of memory" or "oom-kill". Close other
   programs and start MySQL again. If it recurs, the machine needs more RAM or a
   smaller `innodb_buffer_pool_size`.

3. **A configuration file was edited** and MySQL rejects an option in it. The
   error log names the exact line. Undo that edit.

4. **The data directory is damaged**, usually after an unclean shutdown. The
   error log mentions InnoDB recovery or a specific table. This is the only case
   that needs care, and it is worth getting help before touching
   `innodb_force_recovery`.

!!! danger "Last resort: move the disk, not the data"

    If MySQL cannot be brought up at all, do not give up on the data. Shut the
    old machine down and copy the whole of `/var/lib/mysql` off the disk, along
    with `/etc/mysql` and the install folder (`/var/www/intelis`). Those files
    are the database. A raw copy of them can be recovered on another machine,
    but only if it is taken before anyone reinstalls the operating system.
