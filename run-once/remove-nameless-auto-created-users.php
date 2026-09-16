<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Utilities\RunOnceUtility;

/*
 * @run-once-background
 *
 * Removes the nameless users UsersService::getOrCreateUser() used to create.
 *
 * Given a blank name (an analyzer row with no tester, an import with no
 * reviewer) it matched no one and inserted a new user every time. Rows read
 * again inserted another, which left one lab with over half a million of them.
 * It no longer creates a user for a blank name.
 *
 * Only a row matching exactly what that insert wrote is removed: role 4, no
 * name, and no login, email, password, API token or interface names. A user
 * that any record still points to is kept, so no sample is touched. Every
 * column that can hold a user id is read once and its values set aside.
 *
 * Backgrounded because the scan reads every sample table and each delete
 * fires the audit trigger.
 */

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    ini_set('memory_limit', '2G');

    $candidates = [];
    $rows = $db->rawQueryGenerator(
        "SELECT user_id FROM user_details
          WHERE role_id = 4
            AND (user_name IS NULL OR TRIM(user_name) = '')
            AND login_id IS NULL
            AND (email IS NULL OR email = '')
            AND password IS NULL
            AND api_token IS NULL
            AND interface_user_name IS NULL"
    );
    foreach ($rows as $row) {
        $candidates[(string) $row['user_id']] = true;
    }

    if ($candidates === []) {
        MiscUtility::safeCliEcho("Nameless users… none to remove." . PHP_EOL);
        return;
    }

    $columns = $db->rawQuery(
        "SELECT table_name AS t, column_name AS c
           FROM information_schema.columns
           JOIN information_schema.tables USING (table_schema, table_name)
          WHERE table_schema = DATABASE()
            AND table_type = 'BASE TABLE'
            AND table_name <> 'user_details'
            AND table_name NOT LIKE 'audit\\_%'
            AND data_type IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json')
            AND (column_name LIKE '%\\_by' OR column_name LIKE '%user\\_id%')"
    );

    foreach ($columns as $column) {
        $table = '`' . str_replace('`', '``', (string) $column['t']) . '`';
        $name = '`' . str_replace('`', '``', (string) $column['c']) . '`';
        $values = $db->rawQueryGenerator(
            "SELECT DISTINCT $name AS v FROM $table WHERE $name IS NOT NULL"
        );
        foreach ($values as $value) {
            $value = trim((string) $value['v']);
            if ($value === '') {
                continue;
            }
            unset($candidates[$value]);
            // Lists and JSON (instrument approvers, reviewer lists) hold several ids.
            if (strpbrk($value, ',"[') !== false) {
                foreach (preg_split('/[\s,"\[\]{}:]+/', $value, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    unset($candidates[$part]);
                }
            }
        }
    }

    $removed = 0;
    foreach (array_chunk(array_keys($candidates), 1000) as $chunk) {
        $db->rawQuery("DELETE FROM user_details WHERE user_id IN (" . $db->inTextList($chunk) . ")");
        $removed += count($chunk);
    }

    MiscUtility::safeCliEcho("Nameless users… removed $removed no record points to." . PHP_EOL);
});
