# Updating InteLIS on Ubuntu 22.04 or above (only Ubuntu LTS)

This guide updates an existing InteLIS installation to the current release.

**Prerequisites:** Ubuntu 22.04 LTS or a later LTS release. An account with `sudo` rights. An internet connection.

## Before updating

Two checks, in this order, before the update command.

**1. Install the current commands, once per machine.** This installs the current
`intelis` and `intelis-update` straight from master, and nothing else. It is safe
to run on a machine that is already current.

```bash
sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/deforay/intelis/master/scripts/bootstrap.sh)"
```

!!! warning "Required on any machine last updated before August 2026"

    On those, `/usr/local/bin/intelis` is still a plain composer wrapper, so
    `intelis update` runs `composer update`, rewriting `composer.lock` to whatever
    upstream released today and installing the development toolchain onto a server
    that runs a lab. Running the update before this line is what causes that, which
    is why this step comes first. The line above replaces the wrapper, after which
    `intelis update` is correct.

    Note it is `bash -c "$(curl …)"`, not `curl … | bash`. Piping makes the script
    bash's standard input, which is harmless for the bootstrap but ruins the two
    interactive scripts it installs.

**2. Confirm a current backup exists.** The update takes a code snapshot it can
roll back to, but that snapshot does not cover the database, and migrations are
not reversed by a rollback.

```bash
intelis backup
ls -lt /var/www/intelis/backups/db | head
```

The listing must show a dump from today.

## Update

Open a terminal on the InteLIS machine and run:

```bash
intelis update
```

That is the whole procedure. It fetches the current release, takes a snapshot it
can roll back to, puts the new files in place, applies database migrations, and
restarts the web server. Leave the window open until it finishes.

The update prompts for the MySQL password and the STS URL. Enter both correctly.
Wrong entries can make the update fail.

## What a lab upgrades to

Labs follow the `stable` branch, not the tip of `master`. CI fast-forwards
`stable` to any commit on `master` that passed the Verify workflow, so
publishing is automatic and a commit that fails a check reaches no installation.

Verify is what "stable" means here, and it is worth knowing exactly how much it
claims: every PHP file parses, the DI container compiles, the unit tests pass,
and a fresh install seeded from `sql/init.sql` migrates all the way up to the
current version. Nothing runs against real data and no browser opens.

There is no publish command to remember. A fix pushed to `master` is in labs on
their next update, usually within a few minutes of the build going green.

To hold something back, start its commit subject with `[hold]` and `stable`
skips it. It has to be the start of the subject, so a commit that merely
mentions the marker still ships. To publish a specific commit by hand, run the
Publish workflow from the Actions tab.

`stable` only ever fast-forwards. It is never forced, so it cannot be moved back
onto code an installation has already left behind; un-publishing means shipping
a revert.

### Version numbers

A version number is the schema and feature level, which is what `sc_version`
records and what preflight compares. It does not control whether an installation
receives the code: labs follow the `stable` branch, and a push is what ships.
A version is bumped when there is real DDL or a milestone worth naming, and
forgetting it costs an accurate version number, not delivery.

Bumping is a maintainer task performed in the repository, not on a lab machine,
so it is out of scope for this guide.

### Pinning a single machine

`INTELIS_TRACK` overrides what one installation follows:

| Value | Effect |
|-------|--------|
| unset / `latest` | `stable` (default) |
| `stable` | the same thing, named explicitly |
| `master` | branch tip, unverified, for hotfixing one lab ahead of everyone |
| `v5.7.1` | pinned to an exact release tag |

It has to be set on the command that runs the update, not exported beforehand:

```bash
sudo INTELIS_TRACK=master intelis update
```

Exporting it in the shell and then running the update does not work on its own.
`sudo` starts the update with a clean environment, so the variable is dropped on
the way to root. `intelis update` now carries it across that boundary if it is
set, but anything invoking `intelis-update` or `upgrade.sh` directly still needs
it on the command line.

If `stable` does not exist, a lab falls back to the newest release tag, and then
to `master`, so an installation is never stuck because a ref is missing.

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
