<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\DatabaseService;
use InvalidArgumentException;

/**
 * Match an STS acknowledgment to the rows a lab sent, and mark only those synced.
 *
 * Older STS instances acknowledge by sample_code. That misses a sample the STS
 * saved under a numbered variant of its code, so the lab resends it forever.
 * A lab that sends ackFormat=unique_id gets its own identifiers back from an
 * updated STS instead, flagged by the X-Ack-Format response header. Either way
 * the acknowledgment only applies to rows sent in that request.
 *
 * @phpstan-type Row array<array-key, mixed>
 */
final class ResultSyncAcknowledgement
{
    /** Request key, and the X-Ack-Format header value an STS returns when it honours it. */
    public const UNIQUE_ID = 'unique_id';

    /**
     * Form rows from a results payload, flat or nested under form_data.
     *
     * @param array<array-key, mixed> $results
     * @return list<Row>
     */
    public static function sentRows(array $results): array
    {
        $rows = [];
        foreach ($results as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $row = is_array($entry['form_data'] ?? null) ? $entry['form_data'] : $entry;
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * The sent rows an acknowledgment covers. Under the unique_id format, a row
     * with a unique_id matches on it; a row without one matches on its sample code.
     * Under the legacy format every row matches on its sample code.
     *
     * @param list<Row> $sentRows
     * @param list<string> $acknowledged
     * @return list<Row>
     */
    public static function acknowledgedRows(array $sentRows, array $acknowledged, bool $byUniqueId): array
    {
        $ids = array_fill_keys($acknowledged, true);
        $matched = [];
        foreach ($sentRows as $row) {
            $uniqueId = self::text($row['unique_id'] ?? null);
            $key = $byUniqueId && $uniqueId !== '' ? $uniqueId : self::text($row['sample_code'] ?? null);
            if ($key !== '' && isset($ids[$key])) {
                $matched[] = $row;
            }
        }
        return $matched;
    }

    /**
     * Mark rows synced only if unchanged since they were read. A row edited while
     * its request was in flight keeps data_sync = 0, so the edit goes next run.
     *
     * @param list<Row> $rows Sent rows, each with the primary key and last_modified_datetime as read.
     * @return int Rows marked synced.
     */
    public static function markSynced(DatabaseService $db, string $table, string $primaryKey, array $rows): int
    {
        foreach ([$table, $primaryKey] as $identifier) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $identifier)) {
                throw new InvalidArgumentException('Expected a plain table or column name.');
            }
        }
        $ids = $unchanged = $unchangedParams = $neverModified = [];
        foreach ($rows as $row) {
            $id = $row[$primaryKey] ?? null;
            if (!is_int($id) && !is_string($id)) {
                continue;
            }
            $ids[] = $id;
            $modified = self::text($row['last_modified_datetime'] ?? null);
            if ($modified === '') {
                $neverModified[] = $id;
            } else {
                $unchanged[] = '(?, ?)';
                array_push($unchangedParams, $id, $modified);
            }
        }
        if ($ids === []) {
            return 0;
        }

        $conditions = [];
        if ($unchanged !== []) {
            $conditions[] = "(`$primaryKey`, `last_modified_datetime`) IN (" . implode(', ', $unchanged) . ')';
        }
        if ($neverModified !== []) {
            $conditions[] = "(`$primaryKey` IN (" . self::placeholders($neverModified)
                . ') AND `last_modified_datetime` IS NULL)';
        }
        // The plain IN list lets MySQL use the primary key; the row match does the filtering.
        $db->rawQuery(
            "UPDATE `$table` SET `data_sync` = 1, `result_sent_to_source` = 'sent'
                WHERE `$primaryKey` IN (" . self::placeholders($ids) . ')
                AND (' . implode(' OR ', $conditions) . ')',
            [...$ids, ...$unchangedParams, ...$neverModified]
        );
        return (int) $db->count;
    }

    /** @param list<mixed> $values */
    private static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
