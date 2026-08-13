# Updating InteLIS on Ubuntu 22.04 or above (only Ubuntu LTS)

**Note:** This works on Ubuntu 22.04 or above, LTS versions only.

## Update

Open a terminal on the InteLIS machine and run:

```bash
sudo intelis update
```

That is the whole procedure. It fetches the current release, takes a snapshot it
can roll back to, puts the new files in place, applies database migrations, and
restarts the web server. Leave the window open until it finishes.

## If `intelis` is not recognised

Older installations do not have the command. Run these two lines once, and
`sudo intelis update` works from then on:

```bash
sudo wget -O /usr/local/bin/intelis-update https://github.com/deforay/intelis/raw/master/scripts/upgrade.sh

sudo chmod +x /usr/local/bin/intelis-update && sudo intelis-update
```

## Important requirements

The update prompts for two pieces of information:

1. MySQL password
2. STS URL

Enter the MySQL password and STS URL correctly when prompted. Incorrect entries
may cause the update to fail.

## Before and after

| When | Do this |
|------|---------|
| Before | Check backups are current: `sudo intelis backup status` |
| Before | Tell the lab. Pages may not load for a short spell. |
| After | Run `intelis check`. Every line should say PASS. |
| After | Log in and open one page. |

If the update fails, it keeps its snapshot and reports what went wrong. Do not
repair the machine by hand: run `intelis check` and send the whole output to
support. Re-running `sudo intelis update` is safe.
