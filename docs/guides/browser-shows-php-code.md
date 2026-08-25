# Browser Shows PHP Code Instead of InteLIS

Opening InteLIS shows the raw contents of `index.php` as plain text, starting
with `<?php declare(strict_types=1);`, instead of the sign-in page. On some
browsers the page is offered as a download rather than displayed.

This means Apache is serving the file instead of running it. It usually appears
right after an Ubuntu release upgrade, an `apt upgrade` that pulled in a
different PHP version, or an interrupted setup.

Nothing is lost when this happens. The database and the uploaded files are
untouched; only the web server needs putting right.

## Fix

Open a terminal on the InteLIS machine and run:

```bash
sudo intelis doctor
```

It checks what the browser actually gets, explains what it found in plain
language, and asks before changing anything. Reload the browser when it says the
site is working again.

On a machine too old to have that command, or one where InteLIS cannot be
updated to get it, the same script runs straight from the internet:

```bash
sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
```

Note that is `bash -c "$(…)"`, not `… | bash`. Piping hands the script its own
text as the answers to the questions it asks.

The doctor always writes a report and puts a copy named `site-report.txt` in the
operator's own folder, ready to attach to a message. Send that file when asking
for help.

## What it is actually fixing

Three different faults produce this same page, and they are not distinguishable
by looking at the screen.

**Apache has no PHP module.** The usual state after a release upgrade. Ubuntu
defaults to the `event` worker, and mod_php only runs under `prefork` — so
enabling the PHP module while `event` is active silently does nothing. The
module has to go on after the worker is switched, which is the order
`sudo switch-php 8.4` uses (`8.5` on Ubuntu 26.04 and later, which no longer
ships 8.4).

**The PHP packages are not available to install.** A release upgrade disables
the PHP repository, so there is nothing for apt to install and every repair
looks like it failed.

**Apache is configured correctly and never picked it up.** This one is worth
knowing about, because every check says the machine is fine:

```
Module mpm_prefork already enabled
Module php8.4 already enabled
```

All of that can be true while the running server still serves source, because
`apache2ctl -M` reports the configuration on disk rather than what the live
process loaded. A restart is the whole fix:

```bash
sudo systemctl restart apache2
```

A reboot does not reliably settle it, and neither does re-running the tool that
already wrote the correct configuration. This is why the doctor requests a page
over HTTP and reads what comes back, rather than trusting the configuration.

## If the site opens but shows an error

The web server is then doing its job and the fault is inside the application,
most often the database. `intelis doctor` detects this and offers to run the
database doctor itself. To go straight there:

```bash
sudo intelis fix-database
```

## After the fix

Run an update once the site loads again:

```bash
intelis update
```

An interrupted setup or release upgrade can leave more than the PHP module
behind — file ownership and pending database migrations among them — and the
update settles all of it. See [Updating InteLIS on Ubuntu](updating-intelis-on-ubuntu.md).
