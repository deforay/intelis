---
layout: default
title: Backing up LIS or STS to a Remote Backup Server
---

# Backing up LIS or STS to a Remote Backup Server

## Step 1: Download the Script

```bash
cd ~
wget -O remote-backup.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/remote-backup.sh
```

## Step 2: Make the Script Executable

```bash
sudo chmod u+x remote-backup.sh
```

## Step 3: Run the Script

```bash
sudo ./remote-backup.sh
```

## What the Script Does

1. **Instance Name Configuration** — You'll specify a unique identifier for your facility (such as `lab1` or `centerA`), which the script processes for system compatibility.

2. **Tool Installation** — The script verifies and installs `rsync` if needed.

3. **SSH Key Generation** — An SSH keypair is created to establish secure, encrypted communication channels with your backup destination.

4. **Backup Server Configuration** — You'll provide the username and network address (IP or hostname) of your backup server.

5. **Connection Testing** — The script validates connectivity and requests corrected credentials if the initial connection attempt fails.

6. **Key Transfer** — Your SSH public key is automatically deployed to the backup server.

7. **Backup Script Creation** — A dedicated script is generated to synchronize `/var/www/vlsm` with your backup location.

8. **Scheduling** — Cron jobs are configured to execute backups every 6 hours and upon system restart.
