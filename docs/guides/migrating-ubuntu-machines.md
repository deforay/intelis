# Migrating From One Ubuntu Machine to Another

InteLIS backs up its databases automatically every 6 hours to
`<install-path>/backups/db/` — compressed `.sql.zst` files for both the main
(`intelis-…`) and, if used, the interfacing (`interfacing-…`) database. Migration
reuses those backups directly; there is no separate manual export step.

Backups are usually **encrypted** (the file ends in `.sql.zst.gpg`). setup.sh
restores encrypted backups too — see [If your backups are encrypted](#if-your-backups-are-encrypted-gpg)
below for how it gets the key.

## 1. Put the backups on the new machine

Connect the drive that holds the old machine's `backups` folder, or copy that
folder onto the new machine (USB/external drive, or by mounting the old disk).
You only need `backups/db/`. A main-database backup is named like:

```text
intelis-YYYYMMDD-HHMM.sql.zst
```

> Want the freshest possible snapshot and the old machine still runs? Force one
> first, then copy the new file across:
>
> ```bash
> cd /var/www/intelis && sudo -u www-data composer db:backup
> ```

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
  `sudo bash setup.sh --db /media/USB/intelis-20260608-0100.sql.zst`

When prompted, enter the **new** machine's MySQL credentials and the STS URL.

## If your backups are encrypted (`.gpg`)

If the files end in `.sql.zst.gpg`, setup.sh still restores them with `--db` /
`--db latest:` exactly as above — it just needs the key. Use whichever fits:

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
  sudo bash setup.sh --db /media/USB/intelis-….sql.zst.gpg --encryption-password '<recovery-code>'
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
cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore /media/USB/backups/db/interfacing-YYYYMMDD-HHMM.sql.zst
```
