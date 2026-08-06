# Fix Illegal/Mismatched Collation Issue

This guide resolves collation mismatches in the InteLIS database using phpMyAdmin. It sets every table and column to the `utf8mb4_general_ci` collation.

On a server with CLI access, `vendor/bin/db-tools collation` does the same job
without phpMyAdmin. See [Maintenance Scripts](maintenance.md#database-tools-db-tools).

## Steps

1. **Access phpMyAdmin** — Open your browser and navigate to the phpMyAdmin interface. Authenticate with your credentials.

2. **Navigate to the database** — Select the `vlsm` database from the left sidebar.

3. **Open Operations Tab** — Click the "Operations" tab at the top of the page.

4. **Locate Collation Settings** — Scroll down to find the "Collation" section.

5. **Configure Collation** — Select `utf8mb4_general_ci` from the collation dropdown menu.

6. **Enable Bulk Changes:**
   - Check "Change all tables collations" checkbox
   - Check "Change all tables columns collations" checkbox

7. **Execute Changes** — Click the "Go" button to apply the collation updates.

8. **Monitor Completion** — Allow phpMyAdmin to process the database modifications. Completion time depends on your database size.
