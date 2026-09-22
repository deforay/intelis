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
     * data_sync while a row's request is out: 0 is pending, 1 synced, 2 in flight.
     *
     * The sender marks rows 2 just before it reads them to send. Anything that
     * changes a row meanwhile sets data_sync = 0, as every writer already does, so
     * the acknowledgment marks a row synced only while it is still 2. That covers
     * every kind of change without comparing columns, and needs nothing from the
     * writers: a first print leaves last_modified_datetime alone, since the grids
     * list rows by last update, and is caught all the same.
     */
    public const int IN_FLIGHT = 2;

    /** @var array<string, string> Tables the results sender marks, each with its primary key. */
    public const array TABLES = [
        'form_generic' => 'sample_id',
        'form_vl' => 'vl_sample_id',
        'form_eid' => 'eid_id',
        'form_covid19' => 'covid19_id',
        'form_hepatitis' => 'hepatitis_id',
        'form_tb' => 'tb_id',
        'form_cd4' => 'cd4_id',
    ];

    /**
     * Mark rows in flight before they are read for sending.
     *
     * @param list<int|string> $ids
     */
    public static function markInFlight(DatabaseService $db, string $table, string $primaryKey, array $ids): void
    {
        self::assertIdentifiers($table, $primaryKey);
        if ($ids === []) {
            return;
        }
        $db->rawQuery(
            "UPDATE `$table` SET `data_sync` = " . self::IN_FLIGHT
                . " WHERE `$primaryKey` IN (" . self::placeholders($ids) . ')',
            $ids
        );
    }

    /**
     * Mark acknowledged rows synced, but only those still in flight. A row edited or
     * first printed while its request was out is back at data_sync = 0, so the
     * change goes next run.
     *
     * @param list<Row> $rows Acknowledged sent rows, each with its primary key.
     * @return int Rows marked synced.
     */
    public static function markSynced(DatabaseService $db, string $table, string $primaryKey, array $rows): int
    {
        self::assertIdentifiers($table, $primaryKey);
        $ids = [];
        foreach ($rows as $row) {
            $id = $row[$primaryKey] ?? null;
            if (is_int($id) || is_string($id)) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return 0;
        }
        // Every row that matches moves 2 -> 1, so the affected count is exact,
        // including on a resend of rows that were already synced.
        $db->rawQuery(
            "UPDATE `$table` SET `data_sync` = 1, `result_sent_to_source` = 'sent'
                WHERE `$primaryKey` IN (" . self::placeholders($ids) . ') AND `data_sync` = ' . self::IN_FLIGHT,
            $ids
        );
        return (int) $db->count;
    }

    /**
     * Put rows a previous run left in flight back to pending: it stopped before
     * their acknowledgment, so the STS may not have them. Only safe while holding
     * the single-sender lock, when no other run can have rows out.
     *
     * @return int Rows released.
     */
    public static function releaseInFlight(DatabaseService $db, string $table): int
    {
        self::assertIdentifiers($table);
        $db->rawQuery("UPDATE `$table` SET `data_sync` = 0 WHERE `data_sync` = " . self::IN_FLIGHT);
        return (int) $db->count;
    }

    private static function assertIdentifiers(string ...$identifiers): void
    {
        foreach ($identifiers as $identifier) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $identifier)) {
                throw new InvalidArgumentException('Expected a plain table or column name.');
            }
        }
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
