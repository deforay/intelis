# Backing up to Google Drive with Rclone on Ubuntu

## 1. Install Rclone

```bash
sudo apt install rclone
```

## 2. Configure Rclone

Start the configuration process:

```bash
rclone config
```

Follow these steps:
- Select "n" for new remote
- Name your remote (e.g., "gdrive")
- Choose "drive" for Google Drive storage
- Accept default client ID and secret by pressing Enter
- Keep default scope settings
- Select "1" for full file access
- Leave advanced options empty
- Select "n" for advanced config
- Choose "n" for auto config
- Open the provided URL in a browser, authenticate with Google, and authorize Rclone
- Paste the authorization code into the terminal
- Select "n" for team drive
- Confirm and save the configuration with "y"

## 3. Create a Backup Script

```bash
nano /var/www/backup.sh
```

Paste this content (update LABNAME):

```bash
#!/bin/bash

source_dir="/var/www/vlsm"
remote_name="gdrive"
remote_dir="LABNAME"

rclone sync "$source_dir" "$remote_name:$remote_dir"
```

Save and exit (Ctrl+X, Y, Enter).

## 4. Make Script Executable

```bash
chmod +x /var/www/backup.sh
```

## 5. Run the Backup Script

```bash
/var/www/backup.sh
```

Rclone will synchronize your `/var/www/vlsm` folder to Google Drive.

## 6. Automate Backups

Schedule automatic backups using cron:

```bash
(crontab -l 2>/dev/null | grep -q "@reboot /var/www/backup.sh" || echo "@reboot /var/www/backup.sh") | crontab -
```

```bash
(crontab -l 2>/dev/null | grep -q "0 */6 * * * /var/www/backup.sh" || echo "0 */6 * * * /var/www/backup.sh") | crontab -
```

This runs backups on system restart and every 6 hours thereafter.
