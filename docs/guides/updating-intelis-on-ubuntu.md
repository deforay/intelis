# Updating InteLIS on Ubuntu 22.04 or above (only Ubuntu LTS)

This guide updates an existing InteLIS installation to the current release.

**Prerequisites:** Ubuntu 22.04 LTS or a later LTS release. An account with `sudo` rights. An internet connection.

## Update

Open a terminal on the InteLIS machine and run:

```bash
sudo intelis-update
```

That is the whole procedure. It fetches the current release, takes a snapshot it
can roll back to, puts the new files in place, applies database migrations, and
restarts the web server. Leave the window open until it finishes.

!!! warning "`intelis-update`, with a hyphen — not `intelis update`"

    On a machine that has not taken an upgrade since August 2026, `/usr/local/bin/intelis`
    is still a plain composer wrapper, so the spaced form runs `composer update`. That
    rewrites `composer.lock` to whatever upstream released today and installs the
    development toolchain on a server that runs a lab. Use the hyphenated command until
    every machine in the fleet has been updated at least once; after that the two are
    equivalent.

## If `intelis-update` is not recognised

Older installations do not have the command. Run these two lines once, and
`sudo intelis-update` works from then on:

```bash
sudo wget -O /usr/local/bin/intelis-update https://github.com/deforay/intelis/raw/master/scripts/upgrade.sh

sudo chmod +x /usr/local/bin/intelis-update && sudo intelis-update
```

The update prompts for the MySQL password and the STS URL. Enter both correctly.
Wrong entries can make the update fail.

## Before and after

| When | Do this |
| --- | --- |
| Before | Check backups are current: `intelis backup status` |
| Before | Tell the lab. Pages may not load for a short spell. |
| After | Run `intelis check`. Every line should say PASS. |
| After | Log in and open one page. |
| After | Check the version in the page footer has changed. |

If the update fails, it keeps its snapshot and reports what went wrong. Do not
repair the machine by hand. Run `intelis check` and send the whole output to
support. Re-running `sudo intelis-update` is safe.
