---
layout: default
title: Fix Illegal/Mismatched Collation Issue
---

# Fix Illegal/Mismatched Collation Issue

This guide provides steps to resolve collation mismatches in the VLSM database using phpMyAdmin by standardizing all tables and columns to use `utf8mb4_general_ci` collation.

## Steps

1. **Access phpMyAdmin** — Open your browser and navigate to the phpMyAdmin interface. Authenticate with your credentials.

2. **Navigate to VLSM Database** — Select the `vlsm` database from the left sidebar.

3. **Open Operations Tab** — Click the "Operations" tab at the top of the page.

4. **Locate Collation Settings** — Scroll down to find the "Collation" section.

5. **Configure Collation** — Select `utf8mb4_general_ci` from the collation dropdown menu.

6. **Enable Bulk Changes:**
   - Check "Change all tables collations" checkbox
   - Check "Change all tables columns collations" checkbox

7. **Execute Changes** — Click the "Go" button to apply the collation updates.

8. **Monitor Completion** — Allow phpMyAdmin to process the database modifications. Completion time depends on your database size.
