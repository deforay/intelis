# Browser Shows PHP Code Instead of InteLIS

Opening InteLIS shows the raw contents of `index.php` as plain text, starting
with `<?php declare(strict_types=1);`, instead of the sign-in page. On some
browsers the page is offered as a download rather than displayed.

This means Apache is serving the file instead of running it: the PHP module is
no longer active. It usually appears right after an Ubuntu release upgrade, an
`apt upgrade` that pulled in a different PHP version, or an interrupted setup —
any of which can leave Apache without a PHP handler, or switch it back to the
`mpm_event` worker, which mod_php cannot run under.

Nothing is lost when this happens. The database and the uploaded files are
untouched; only the web server needs its PHP module put back.

## Fix

Open a terminal on the InteLIS machine and run:

```bash
sudo switch-php 8.4
```

Use `8.5` instead of `8.4` on Ubuntu 26.04 or later, which no longer ships PHP
8.4. On Ubuntu 24.04 and earlier, use `8.4`. Check the release with
`lsb_release -rs` if unsure.

The command installs the matching PHP packages, re-enables `mpm_prefork` and the
`phpX.Y` Apache module, points the `php` command at that version, and restarts
Apache. It takes a few minutes on a slow connection. Reload the browser when it
finishes.

If the command is not found, install it first:

```bash
sudo curl -fsSL https://raw.githubusercontent.com/deforay/utility-scripts/master/php/switch-php -o /usr/local/bin/switch-php
sudo chmod +x /usr/local/bin/switch-php
```

## Confirm the fix

```bash
php -v                          # reports the expected version
apache2ctl -M | grep -E 'php|mpm'   # lists php8.4_module and mpm_prefork_module
```

Both lines have to be present. A `mpm_event_module` in that output with no
`php` line is the exact state that produces the raw source page.

## If the page still shows code

Work through these in order.

1. **Apache did not actually restart.** `sudo systemctl restart apache2`, then
   `systemctl status apache2 --no-pager`. A configuration error stops the
   restart and leaves the old process running; `sudo apache2ctl -t` names the
   offending file.

2. **The module is installed but not enabled.** Enable it by hand, matching the
   version reported by `php -v`:

   ```bash
   sudo a2dismod -f mpm_event mpm_worker
   sudo a2enmod mpm_prefork
   sudo a2enmod php8.4
   sudo systemctl restart apache2
   ```

   `a2enmod php8.4` failing with "module php8.4 does not exist" means the
   package is missing — `sudo apt install libapache2-mod-php8.4` — and enabling
   `mpm_prefork` first is not optional, because the module's `.load` file is not
   created while `mpm_event` is active.

3. **The browser cached the source.** Reload with Ctrl+Shift+R, or open the
   address in a private window, before concluding the fix did not work.

4. **Something else is wrong.** The last lines of
   `/var/log/apache2/error.log` and `/var/log/php-switch.log` say what failed.

## After the fix

Run an update once the site loads again:

```bash
intelis update
```

An interrupted setup or release upgrade can leave more than the PHP module
behind — file ownership and pending database migrations among them — and the
update settles all of it. See [Updating InteLIS on Ubuntu](updating-intelis-on-ubuntu.md).
