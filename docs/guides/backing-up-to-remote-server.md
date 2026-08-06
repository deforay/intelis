# Backing up LIS or STS to a Remote Backup Server

## Step 1: Download the Script

```bash
cd ~
wget -O remote-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/remote-backup.sh
```

## Step 2: Make the Script Executable

```bash
sudo chmod u+x remote-backup.sh
```

## Step 3: Run the Script

```bash
sudo ./remote-backup.sh
```

## What the script asks for

| Prompt | Example |
|--------|---------|
| Lab name or lab code | `lab1` |
| LIS folder path | `/var/www/intelis` (press Enter to accept) |
| Backup server username | `lisbackup` |
| Backup server hostname or IP | `192.168.1.60` |
| SSH port | `22` (press Enter to accept) |

## What the script does

1. Installs `rsync` if it is missing.
2. Generates an SSH keypair for the connection to the backup server.
3. Copies the public key to the backup server.
4. Tests the connection, and asks again if the credentials fail.
5. Writes `/usr/local/bin/intelis-backup.sh`, which syncs the LIS folder to the backup server.
6. Schedules that script to run every 8 hours and on every reboot.
