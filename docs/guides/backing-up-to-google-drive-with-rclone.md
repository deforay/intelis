# Backing up to Google Drive with Rclone on Ubuntu

Upload the InteLIS backups to Google Drive every 6 hours, so a copy survives a
fire or a theft at the lab.

`intelis restore` cannot fetch from Google Drive. The files are downloaded by
hand, as shown in [Get the backups back](#get-the-backups-back).

!!! warning "Keep the Google account private"
    The uploaded settings backups contain the InteLIS database password. Use a
    Google account kept for this purpose only. Turn on 2-Step Verification for
    it. Do not share the backup folder with anyone.

## Set up the upload

1. Open a terminal on the InteLIS machine and install Rclone:

    ```bash
    sudo apt install rclone
    ```

2. Start the Rclone setup:

    ```bash
    sudo rclone config
    ```

    Use `sudo`. The upload runs as the administrator account, and reads the
    Rclone settings saved by this step.

3. Answer the questions:

    | Question | Answer |
    | --- | --- |
    | `n/s/q>` or `e/n/d/r/c/s/q>` | `n` (new remote) |
    | `name>` | `gdrive` |
    | `Storage>` | `drive` |
    | `client_id>` | Press Enter. |
    | `client_secret>` | Press Enter. |
    | `scope>` | `1` (full access to all files) |
    | `service_account_file>` | Press Enter. |
    | Edit advanced config? | `n` |
    | Use web browser to automatically authenticate rclone with remote? | `y` |
    | Configure this as a Shared Drive (Team Drive)? | `n` |
    | Keep this "gdrive" remote? | `y` |
    | `e/n/d/r/c/s/q>` | `q` (quit) |

    After the web browser question, a browser window opens. Sign in to the
    Google account kept for backups, and allow Rclone access.

    ??? failure "If no browser window opens"

        Rclone prints a link starting with `http://127.0.0.1:53682/`. Open the
        link in a browser on the same machine.

4. Choose a folder name for this machine on Google Drive, for example
   `intelis/centrallab-01`. Name the machine as well as the lab. Two machines
   that upload to the same folder overwrite each other's backups.
5. Check the folder is empty or new:

    ```bash
    sudo rclone lsf gdrive:intelis/centrallab-01
    ```

    It must print nothing. If it lists files, another machine uses that name.
    Choose another name at step 4.

6. Create the upload script:

    ```bash
    sudo nano /usr/local/bin/intelis-gdrive-backup.sh
    ```

7. Paste this into the editor. Change `intelis/centrallab-01` to the folder
   name from step 4. On older installs, change `/var/www/intelis` to
   `/var/www/vlsm`.

    ```bash
    #!/bin/bash
    set -Eeuo pipefail

    source_dir="/var/www/intelis"
    remote_name="gdrive"
    remote_dir="intelis/centrallab-01"

    # Take a fresh database and settings backup first.
    # The full path is needed because cron runs with a minimal PATH.
    /usr/local/bin/intelis backup

    # copy, not sync: sync deletes files on Google Drive that are gone from
    # this machine, including older backups.
    rclone copy "$source_dir/backups" "$remote_name:$remote_dir/backups"
    ```

8. Press **Ctrl+X**, then **Y**, then Enter, to save and close.
9. Make the script runnable:

    ```bash
    sudo chmod +x /usr/local/bin/intelis-gdrive-backup.sh
    ```

10. Run it once:

    ```bash
    sudo /usr/local/bin/intelis-gdrive-backup.sh
    ```

    ??? info "If it asks `Set up off-machine backups now?`"

        Press Enter to answer No. The upload to Google Drive carries on. To also
        send backups to another machine or a drive on the network, see
        [Setting Up Off-Machine Backups](setting-up-off-machine-backups.md).

    ??? failure "If it stops with an error after `Step 2 of 2`"

        This machine also sends backups to another machine or drive, and that
        backup failed. Nothing was uploaded to Google Drive. Run
        `intelis backup status` to see why, fix it, then run step 10 again.

11. Check that a database backup from today arrived:

    ```bash
    sudo rclone lsl gdrive:intelis/centrallab-01/backups/db
    ```

    The list must include a file starting with `vlsm-` with today's date.

### Run it automatically

12. Add the upload to the schedule, keeping any other scheduled work on the
    machine:

    ```bash
    sudo crontab -l 2>/dev/null | grep -Fv intelis-gdrive-backup.sh > /tmp/intelis-cron
    printf '@reboot /usr/local/bin/intelis-gdrive-backup.sh\n0 */6 * * * /usr/local/bin/intelis-gdrive-backup.sh\n' >> /tmp/intelis-cron
    sudo crontab /tmp/intelis-cron
    rm -f /tmp/intelis-cron
    ```

13. Check the schedule:

    ```bash
    sudo crontab -l
    ```

    The list must include these two lines, below any lines that were there
    before:

    ```text
    @reboot /usr/local/bin/intelis-gdrive-backup.sh
    0 */6 * * * /usr/local/bin/intelis-gdrive-backup.sh
    ```

    The upload now runs every 6 hours and after every restart.

## Get the backups back

Use this when the InteLIS machine has died and a new machine is being set up.

1. On the new machine, repeat steps 1 to 3 of [Set up the upload](#set-up-the-upload).
2. Download the whole `backups` folder, using the folder name from step 4:

    ```bash
    sudo rclone copy gdrive:intelis/centrallab-01/backups ~/backups
    ```

    Download the whole folder, not only the newest database backup. The
    database backups are encrypted, and the key is read from the settings
    backups in `backups/config`.

    ??? info "If the download is too large"

        Download only the last 14 days:

        ```bash
        sudo rclone copy --max-age 14d gdrive:intelis/centrallab-01/backups ~/backups
        ```

3. Check the download holds both `db` and `config` folders:

    ```bash
    ls ~/backups
    ```

4. Follow the **Old machine is dead** tab of
   [Migrating From One Ubuntu Machine to Another](migrating-ubuntu-machines.md).
   Use the `backups` folder in the home folder in place of the one on the USB
   drive. In the **Files** app, it is under **Home**.
