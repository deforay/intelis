# Backing up InteLIS to a Windows Machine (Same Network)

This guide sets up **automatic** backups of your InteLIS server onto a shared
folder on a Windows PC sitting on the same local network. The Linux server does
all the work — every 8 hours it copies the InteLIS folder into the Windows
shared folder. No software needs to be installed on Windows.

!!! info "How it works"
    The Windows folder is *mounted* onto the Linux server like a local disk, and
    InteLIS is copied into it with `rsync`. Only changed files are copied each
    time, so backups after the first one are fast.

---

## Part A — Prepare the Windows machine (one time)

Do these five things on the Windows PC first. They take about five minutes.

### 1. Create the backup folder

Create a folder, for example:

```
C:\InteLIS-Backups
```

### 2. Share the folder

1. Right-click the folder → **Properties** → **Sharing** tab → **Advanced Sharing…**
2. Tick **Share this folder**.
3. Set the **Share name** to `InteLIS-Backups` (⚠️ **no spaces** in the name).
4. Click **Permissions**, and give the backup user (created next) **Change** and **Read**.
5. Click **OK** on all windows.

### 3. Create a dedicated Windows user for backups

Don't use your personal login. Create a separate local account:

1. Press `Win + R`, type `lusrmgr.msc`, press Enter.
2. **Users** → right-click → **New User…**
3. Username `lisbackup`, set a **strong password**, untick *"User must change password"*, tick *"Password never expires"*.
4. Create, then grant this user access to the share (step 2.4 above).

### 4. Give the Windows machine a fixed IP address

The Linux server needs to always find the same address. Either set a **static
IP** on the Windows PC, or reserve its IP in your router's DHCP settings. Note
the address down, for example `192.168.1.50`.

### 5. Allow file sharing through the firewall

Control Panel → **Windows Defender Firewall** → **Allow an app…** → make sure
**File and Printer Sharing** is ticked for **Private** networks.

You'll need three things from this part for the next step:

| What | Example |
|------|---------|
| Windows IP address | `192.168.1.50` |
| Share name | `InteLIS-Backups` |
| Username / password | `lisbackup` / *your password* |

---

## Part B — Set up the InteLIS (Linux) server

Run these three commands on the InteLIS server.

### 1. Download the script

```bash
cd ~
wget -O remote-backup-windows.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/remote-backup-windows.sh
```

### 2. Make it executable

```bash
sudo chmod u+x remote-backup-windows.sh
```

### 3. Run it

```bash
sudo ./remote-backup-windows.sh
```

The script will ask you a few questions:

- **Lab name / code** — a short identifier for this lab (e.g. `centrallab`).
- **LIS folder path** — press Enter to accept the default `/var/www/intelis`.
- **Windows IP, share name, username, password** — the three things from Part A.
- **SMB version** — press Enter to accept `3.0`.

That's it. The script mounts the share, runs a first backup in the background,
and schedules automatic backups going forward.

---

## What happens after setup

- **Automatic schedule:** backups run **every 8 hours** and once on every reboot.
- **Location on Windows:** `C:\InteLIS-Backups\backups\<your-lab-name>\`
- **What's copied:** the whole InteLIS folder, minus regenerable/temporary files
  (`vendor/`, caches, logs, version-control folders).

### Useful commands

| Task | Command |
|------|---------|
| Watch a backup as it runs | `tail -f /var/log/intelis-backup-windows.log` |
| Run a backup right now | `sudo /usr/local/bin/intelis-backup-windows.sh` |
| Turn automatic backups off | `sudo /usr/local/bin/intelis-backup-windows.sh --disable` |
| Turn them back on | re-run `sudo ./remote-backup-windows.sh` |

---

## Troubleshooting

!!! failure "Failed to mount / cannot reach the share"
    - Confirm the Windows PC is on and reachable: `ping 192.168.1.50`.
    - Re-check the share name (no spaces) and the username/password.
    - Make sure **File and Printer Sharing** is allowed through the Windows firewall.

!!! failure "Mounted but not writable"
    The Windows user needs **Change** permission on the share, not just Read
    (Part A, steps 2 and 3).

!!! warning "Share name has spaces"
    The script refuses share names with spaces because they break the Linux
    mount. Re-share the folder with a name like `InteLIS-Backups`.

!!! note "This is one copy, not a full disaster-recovery plan"
    The Windows PC is a single on-site copy. If it sits in the same room as the
    server, one fire, theft, or ransomware event can take both. For true safety,
    add an off-site copy as well (see
    [Backing up to Google Drive with Rclone](backing-up-to-google-drive-with-rclone.md)
    or [Backing up to a Remote Server](backing-up-to-remote-server.md)).
