# Fix illegal or mismatched collation errors

InteLIS shows an `Illegal mix of collations` error, usually after a database was
restored from another MySQL or MariaDB server.

1. Open a terminal on the InteLIS machine and take a backup. The repair rewrites
   every table.

    ```bash
    intelis backup
    ```

2. Run the collation repair:

    ```bash
    intelis db:collation
    ```

    It converts the InteLIS database and the interfacing database. It runs as
    the web server account and may ask for the administrator password first.

    ??? info "If `intelis` is not recognised"

        The install is older. Run the repair directly:

        ```bash
        cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools collation --all
        ```

        On older installs, use `/var/www/vlsm` in place of `/var/www/intelis`.

3. Check that nothing is left to convert:

    ```bash
    intelis db:collation -- --dry-run
    ```

    Each database reports `0 need conversion`.

    ??? warning "Keep the `--` before `--dry-run`"

        Without it, the `--dry-run` option is dropped and the command converts
        the tables instead of only reporting.

`intelis update` runs the same repair at the end of every update.

??? info "Convert step by step with db-tools"

    To review the changes before applying them, run db-tools directly from the
    InteLIS folder:

    ```bash
    cd /var/www/intelis
    ```

    List what would change, without changing anything:

    ```bash
    sudo -u www-data php vendor/bin/db-tools collation --all --dry-run
    ```

    Apply the conversion:

    ```bash
    sudo -u www-data php vendor/bin/db-tools collation --all
    ```

    `--all` covers both databases. Without it, only the InteLIS database is
    converted.

??? info "What this fixes"

    The error appears when a query compares text columns stored with different
    collations. The repair asks the server which collation to use and converts
    every table and column to it. MySQL 8 uses `utf8mb4_0900_ai_ci`. MariaDB
    does not support that collation and gets its own default.

    Do not set tables to `utf8mb4_general_ci` by hand, for example in
    phpMyAdmin. Tables that later updates create or change get the server's
    default collation, so the error comes back after the next update.
