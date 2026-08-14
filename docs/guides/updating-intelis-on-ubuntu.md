# Updating InteLIS on Ubuntu 22.04 or above (only Ubuntu LTS)

This guide updates an existing InteLIS installation to the current release.

**Prerequisites:** Ubuntu 22.04 LTS or a later LTS release. An account with `sudo` rights. An internet connection.

## Update

Open a terminal on the InteLIS machine and run:

```bash
intelis update
```

That is the whole procedure. It fetches the current release, takes a snapshot it
can roll back to, puts the new files in place, applies database migrations, and
restarts the web server. Leave the window open until it finishes.

## First time on a given machine

Run this once per machine, before the command above. It installs the current
`intelis` and `intelis-update` straight from master, and nothing else.

```bash
sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/deforay/intelis/master/scripts/bootstrap.sh)"
```

!!! warning "Do this first on any machine last updated before August 2026"

    On those, `/usr/local/bin/intelis` is still a plain composer wrapper, so
    `intelis update` runs `composer update` — rewriting `composer.lock` to whatever
    upstream released today and installing the development toolchain onto a server
    that runs a lab. The line above replaces the wrapper, after which `intelis update`
    is correct. It is safe to run on a machine that is already current.

    Note it is `bash -c "$(curl …)"`, not `curl … | bash`. Piping makes the script
    bash's standard input, which is harmless for the bootstrap but ruins the two
    interactive scripts it installs.

The update prompts for the MySQL password and the STS URL. Enter both correctly.
Wrong entries can make the update fail.

## What a lab upgrades to

Labs follow the newest published release tag, not the tip of `master`. Nothing
reaches an installation until someone tags it, which is what separates merging a
change from shipping it.

Publishing is two commands after the version bump:

```bash
composer version patch -- -y      # bumps composer.json, version.php, migrations
git tag -a v5.7.1 -m "Release 5.7.1"
git push origin v5.7.1
```

An urgent fix therefore ships as fast as it always did — the tag is the
deliberate act, not a delay.

`INTELIS_TRACK` overrides this on a single machine:

| Value | Effect |
|-------|--------|
| unset / `latest` | newest `vN.N.N` tag (default) |
| `master` | branch tip, for hotfixing one lab ahead of a release |
| `v5.7.1` | pinned to an exact release |

If nothing has been tagged yet, a lab falls back to `master`, so an installation
is never stuck because no release exists.

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
