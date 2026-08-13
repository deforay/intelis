# Installing InteLIS on Ubuntu 22.04 or above (only Ubuntu LTS)

This guide installs InteLIS on a fresh Ubuntu server.

**Prerequisites:** Ubuntu 22.04 LTS or a later LTS release. Non-LTS releases are not supported. The installer needs administrator rights and an internet connection.

## Installation steps

Open a terminal and run:

```bash
# Download the script to a file, then run it. Do NOT pipe it (curl ... | bash).
cd ~ && wget -O setup.sh "https://github.com/deforay/intelis/raw/master/scripts/setup.sh?v=$(date +%s)" && sudo bash setup.sh
```

The installer prompts for the MySQL password and the STS URL. Enter both correctly. A wrong STS URL stops InteLIS from syncing. When prompted for the testing lab, choose the lab this installation serves.

## Complete the setup

1. Open <http://intelis/> in a browser.
2. Complete the configuration InteLIS presents.
3. Create the administrator account.
4. Log in as that administrator at <http://intelis/>.

## After installing

Setup installs the `intelis` command, which is how the machine is run from then
on. Three things are worth doing straight away:

```bash
intelis check
```

Every line should say PASS. Anything that says FAIL prints the exact command
that fixes it.

```bash
intelis backup setup
```

A machine with no backup is one disk failure away from losing everything the
lab has entered. Set the destination now, not later.

Running `intelis` on its own shows a numbered menu of everything else: update,
back up, check the backups, restore. Run these without `sudo`. The command asks
for administrator rights only for the steps that need them.

To update this installation later, see
[Updating InteLIS on Ubuntu](updating-intelis-on-ubuntu.md).

## Restoring an existing lab onto this machine?

Do not follow this page. Run the restore first — it prints an install command
with the backup already attached, so the machine comes back with its data
instead of empty. See [Restoring from a backup](restoring-from-backup.md).
