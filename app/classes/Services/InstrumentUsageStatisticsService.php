<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\DateUtility;

/**
 * Stores the daily test volume the Interface Tool reports for its instruments.
 *
 * A summary is a running total for one day, one instrument and one test type. The
 * tool revises it as more tests complete and raises `revision` each time, so a
 * summary that arrives late cannot undo a newer one.
 *
 * Counts are absolute and are written as given -- never added to what is already
 * held. That is what makes a repeated delivery a no-op, which matters because a
 * summary can reach us twice: bin/interface.php reads it out of the tool's local
 * database and an installation can also post it over the API.
 *
 * The lab is always supplied by the caller from a trusted source -- the system
 * config for the importer, the credential for the API -- and never read from the
 * summary itself.
 */
final class InstrumentUsageStatisticsService
{
    public const VIA_API = 'api';
    public const VIA_IMPORTER = 'importer';

    /** A day's count far beyond any real analyzer, so a corrupt figure is caught. */
    private const MAX_COUNT = 1000000;

    /** The ceiling of the INT UNSIGNED column the revision is stored in. */
    private const MAX_REVISION = 4294967295;

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * Stores a batch of summaries and reports what happened to each one.
     *
     * @param array<int, array<string, mixed>> $summaries
     * @return array{
     *     stored: int, updated: int, duplicates: int, stale: int, rejected: int,
     *     summaries: list<array{aggregate_id: string, revision: int, outcome: string}>
     * }
     */
    public function store(
        array $summaries,
        int $labId,
        string $receivedVia = self::VIA_API,
        ?string $installationId = null
    ): array {
        $counts = ['stored' => 0, 'updated' => 0, 'duplicates' => 0, 'stale' => 0, 'rejected' => 0];
        $outcomes = [];

        foreach ($summaries as $summary) {
            $row = $this->normalize($summary, $labId, $receivedVia, $installationId);
            if ($row === null) {
                $counts['rejected']++;
                $outcomes[] = [
                    'aggregate_id' => trim((string) ($summary['aggregate_id'] ?? '')),
                    'revision' => (int) ($summary['revision'] ?? 0),
                    'outcome' => 'rejected',
                ];
                continue;
            }

            $outcome = $this->upsert($row);
            $counts[$this->counterFor($outcome)]++;
            $outcomes[] = [
                'aggregate_id' => $row['aggregate_uid'],
                'revision' => $row['revision'],
                'outcome' => $outcome,
            ];
        }

        return $counts + ['summaries' => $outcomes];
    }

    /**
     * @param array<string, mixed> $row
     * @return string stored, updated, duplicate or stale
     */
    private function upsert(array $row): string
    {
        $existing = $this->find($row);

        if ($existing === null) {
            // INSERT IGNORE rather than INSERT: if the importer and the API deliver the
            // same day at once, the unique key decides instead of one of them erroring.
            $columns = implode(', ', array_keys($row));
            $placeholders = implode(', ', array_fill(0, count($row), '?'));
            $this->db->connection('default')->rawQuery(
                "INSERT IGNORE INTO instrument_usage_statistics_daily ($columns) VALUES ($placeholders)",
                array_values($row)
            );

            if ((int) $this->db->connection('default')->count > 0) {
                return 'stored';
            }

            // Losing that race does not make this summary a duplicate -- it may well be
            // the newer of the two. Re-read what the winner wrote and compare revisions
            // as normal, or a higher revision would be acknowledged and then discarded.
            $existing = $this->find($row);
            if ($existing === null) {
                return 'duplicate';
            }
        }

        $heldRevision = (int) $existing['revision'];
        if ($row['revision'] < $heldRevision) {
            return 'stale';
        }
        // Equal revision means the same summary again -- a retry, or the same day
        // reaching us down both paths. What is held already describes that revision,
        // so there is nothing to write.
        if ($row['revision'] === $heldRevision) {
            return 'duplicate';
        }

        // Guarded by the revision as well as the id: if a newer summary landed between
        // the read above and this write, this update does nothing rather than going
        // backwards.
        $this->db->connection('default')->rawQuery(
            'UPDATE instrument_usage_statistics_daily
                SET installation_id = ?, received_via = ?, instrument_id = ?, machine_type = ?,
                    test_type = ?, total_tests = ?, successful_tests = ?, failed_tests = ?,
                    first_test_at = ?, last_test_at = ?, revision = ?, updated_at = ?
              WHERE usage_statistic_id = ? AND revision < ?',
            [
                $row['installation_id'],
                $row['received_via'],
                $row['instrument_id'],
                $row['machine_type'],
                $row['test_type'],
                $row['total_tests'],
                $row['successful_tests'],
                $row['failed_tests'],
                $row['first_test_at'],
                $row['last_test_at'],
                $row['revision'],
                $row['updated_at'],
                (int) $existing['usage_statistic_id'],
                $row['revision'],
            ]
        );

        // If a newer summary did land in between, the guard matched nothing and this one
        // has been overtaken. Reporting it as updated would claim a write that never
        // happened.
        return (int) $this->db->connection('default')->count > 0 ? 'updated' : 'stale';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function find(array $row): ?array
    {
        $existing = $this->db->connection('default')->rawQueryOne(
            'SELECT usage_statistic_id, revision
               FROM instrument_usage_statistics_daily
              WHERE lab_id = ? AND aggregate_uid = ?
              LIMIT 1',
            [$row['lab_id'], $row['aggregate_uid']]
        );

        return empty($existing) ? null : $existing;
    }

    private function counterFor(string $outcome): string
    {
        return match ($outcome) {
            'stored' => 'stored',
            'updated' => 'updated',
            'stale' => 'stale',
            default => 'duplicates',
        };
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>|null null when the summary is not usable
     */
    private function normalize(
        array $summary,
        int $labId,
        string $receivedVia,
        ?string $installationId
    ): ?array {
        $aggregateUid = trim((string) ($summary['aggregate_id'] ?? $summary['aggregate_uid'] ?? ''));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $aggregateUid) !== 1) {
            return null;
        }

        // Required to be an exact calendar date rather than anything Carbon can parse:
        // the day is the key these counts hang off, so a loose reading of it would
        // silently file a summary against the wrong day.
        $activityDate = trim((string) ($summary['activity_date'] ?? ''));
        if (!DateUtility::isDateFormatValid($activityDate, 'Y-m-d')) {
            return null;
        }

        // Bounded above as well as below: the column is INT UNSIGNED, and a revision past
        // its ceiling would be clamped on the way in and then make every later revision
        // look stale, freezing that day's counts for good.
        $revision = $this->integer($summary['revision'] ?? null, 1, self::MAX_REVISION);
        if ($revision === null) {
            return null;
        }

        $total = $this->integer($summary['total_tests'] ?? null, 0, self::MAX_COUNT);
        $successful = $this->integer($summary['successful_tests'] ?? null, 0, self::MAX_COUNT);
        $failed = $this->integer($summary['failed_tests'] ?? null, 0, self::MAX_COUNT);
        if ($total === null || $successful === null || $failed === null) {
            return null;
        }
        // A day is either successful or failed, nothing else. If the parts do not make
        // the whole, the summary is wrong in a way we cannot repair here.
        if ($total !== $successful + $failed) {
            return null;
        }

        // A timestamp that was sent but cannot be read is a fault worth reporting, not
        // something to quietly store as absent.
        $firstTestAt = $this->timestamp($summary['first_test_at'] ?? null);
        $lastTestAt = $this->timestamp($summary['last_test_at'] ?? null);
        if ($firstTestAt === false || $lastTestAt === false) {
            return null;
        }
        if ($firstTestAt !== null && $lastTestAt !== null && $firstTestAt > $lastTestAt) {
            return null;
        }

        return [
            'aggregate_uid' => $aggregateUid,
            'lab_id' => $labId,
            'installation_id' => $installationId,
            'received_via' => $receivedVia,
            'activity_date' => $activityDate,
            'instrument_id' => $this->nullableText($summary['instrument_id'] ?? null, 128),
            'machine_type' => $this->nullableText($summary['machine_type'] ?? null, 128),
            'test_type' => $this->nullableText($summary['test_type'] ?? null, 128),
            'total_tests' => $total,
            'successful_tests' => $successful,
            'failed_tests' => $failed,
            'first_test_at' => $firstTestAt,
            'last_test_at' => $lastTestAt,
            'revision' => $revision,
            // Stamped here rather than by the column: the table cannot use
            // DEFAULT/ON UPDATE CURRENT_TIMESTAMP, because migrate.php rewrites
            // "ON UPDATE CURRENT_TIMESTAMP" into a syntax error.
            'received_at' => DateUtility::getCurrentDateTime(),
            'updated_at' => DateUtility::getCurrentDateTime(),
        ];
    }

    /**
     * Whole numbers only. JSON sends them as integers and MySQL as digit strings, so
     * anything else -- "1abc", 1.5, "1e3", true -- is a fault rather than something to
     * round or truncate into shape.
     */
    private function integer(mixed $value, int $min, int $max): ?int
    {
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            $value = (int) trim($value);
        } elseif (!is_int($value)) {
            return null;
        }

        return ($value < $min || $value > $max) ? null : $value;
    }

    /**
     * @return string|false|null the timestamp when usable, null when not supplied at all,
     *                           false when supplied but unreadable
     */
    private function timestamp(mixed $value): string|false|null
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            return false;
        }

        // Required exactly as the tool sends it. A timestamp loosely re-read here would
        // be worse than an absent one, since these bound the working day.
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return DateUtility::isDateFormatValid($value, 'Y-m-d H:i:s') ? $value : false;
    }

    private function nullableText(mixed $value, int $length): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
