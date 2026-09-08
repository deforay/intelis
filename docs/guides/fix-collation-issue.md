# Fix illegal or mismatched collation errors

A collation mismatch shows up as an "Illegal mix of collations" error when a
query joins two tables whose text columns were created with different
collations. It usually follows a restore from a dump taken on a server with a
different MySQL or MariaDB version.

## Convert with db-tools

`db-tools collation` is the supported repair. It detects the collation the server
recommends rather than forcing a fixed one, which matters because the shipped
schema uses `utf8mb4_0900_ai_ci` on MySQL 8 while MariaDB does not support that
collation at all.

1. Take a current backup first. This rewrites every table in the database.

   ```bash
   cd /var/www/intelis
   sudo -u www-data php vendor/bin/db-tools backup --all
   ```

2. Review what would change, without changing anything:

   ```bash
   sudo -u www-data php vendor/bin/db-tools collation --dry-run
   ```

3. Apply the conversion:

   ```bash
   sudo -u www-data php vendor/bin/db-tools collation
   ```

   Add `--all` to convert the interfacing database in the same run.

4. Confirm the database is now consistent:

   ```bash
   sudo -u www-data php vendor/bin/db-tools collation --dry-run
   ```

   A clean run reports nothing left to convert.

## Do not force utf8mb4_general_ci by hand

Setting every table and column to `utf8mb4_general_ci` through the phpMyAdmin
Operations tab, which older copies of this guide described, resolves the
immediate error and then creates a new one. The shipped schema in `sql/init.sql`
uses `utf8mb4_0900_ai_ci`, so every table a later migration creates or alters
arrives with the server's own collation and no longer matches the tables that
were forced to `general_ci`. The mismatch returns after the next update.

Where a machine genuinely has no CLI access, convert with the collation the
server reports as its default rather than a hard-coded one, and re-run the
`--dry-run` check above from any machine that can reach the database afterwards.
