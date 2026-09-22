<?php

namespace App\Services;

/**
 * Writes one row of the metadata a lab pulls from the STS
 * (tasks/remote/sts-metadata-receiver.php): facilities, users, reference lists.
 *
 * The STS is the authority for these tables, but a lab can be on a newer release
 * with columns the STS does not send. Only the columns the STS sent are written,
 * so those are left alone rather than set to NULL on every sync.
 */
final class StsMetadataWriter
{
    /** A user's login, role, password and status are the lab's own. */
    private const array USER_OWNED = ['login_id', 'role_id', 'password', 'status'];

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * @param mixed $row the row as the STS sent it
     * @param array<string, mixed> $tableFields this table's columns (as keys)
     * @return array<string, mixed>|null what was written, or null for a row that is not one
     */
    public function upsertRow(string $tableName, mixed $row, array $tableFields, string $primaryKey): ?array
    {
        if (!is_array($row) || $row === []) {
            return null;
        }
        $tableData = array_intersect_key($row, $tableFields);
        if ($tableName === 'user_details') {
            foreach (self::USER_OWNED as $key) {
                unset($tableData[$key]);
            }
        }
        if ($tableData === []) {
            return null;
        }

        $this->db->upsert($tableName, $tableData, array_keys($tableData), [$primaryKey]);
        return $tableData;
    }
}
