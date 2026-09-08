# Permission denied errors

InteLIS reports a permission denied error when the web server account cannot
write to one of the directories the application generates files in, such as
`var/cache`, `var/logs`, `var/temporary` or `public/uploads`. This usually
follows a manual file copy, a restore, or Composer having been run as root.

## Repair the installation's own directories

```bash
sudo intelis provision
```

This creates the runtime directories the application writes to and sets their
ownership and permissions. It touches only the InteLIS installation.

Then confirm the machine is healthy again:

```bash
intelis check
```

If `intelis check` still reports a permission problem, run the fuller repair,
which also restores ownership after a copy or a restore has changed it:

```bash
sudo intelis-refresh -p /var/www/intelis -m full
```

On an installation made before the rename, the path is `/var/www/vlsm`.

## Do not widen permissions across /var/www

A recursive ACL over the whole of `/var/www`, such as
`setfacl -R -m u:www-data:rwx /var/www`, grants the web server account write and
execute access to every application on the machine, including source files,
configuration, and any other site hosted there. A vulnerability in any one of
them then reaches all of them. It also masks the real fault rather than fixing
it, so the problem returns after the next update.

Where a specific directory genuinely needs to be shared with another account,
grant it on that directory alone, never on `/var/www`.
