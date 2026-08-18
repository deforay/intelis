<?php

namespace App\Services;

use App\Utilities\DateUtility;

/**
 * What a lab's own rejection reason id means here.
 *
 * `rejection_reason_id` is a per-install auto-increment and reference data only
 * ever flowed STS -> LIS, so a reason a lab types into the "Other" box on a
 * request or result form is minted locally and exists nowhere else. The id then
 * travels here on the sample and points at nothing: on one country's data 1,553
 * VL rows across 11 labs, 91% of the rejections in a recent nine-month window.
 * Reports INNER JOINed the reason table, so all of them silently vanished.
 *
 * The fix is a translation, not a rewrite. The lab keeps its own ids and its own
 * reports go on working exactly as before; this side records what each of those
 * ids means and swaps it for the canonical one as samples arrive.
 *
 * Merging is by normalized name, which is what makes the shared list converge:
 * eight labs independently typing "Tube cassé" end up on one canonical row, each
 * with its own mapping to it. A name nobody has used before becomes a new
 * canonical reason tagged with the lab that contributed it, so the national list
 * stays reviewable rather than quietly accumulating whatever was typed.
 *
 * Deliberately NOT deduplicating beyond exact normalized equality: "Tube casse"
 * and "Tube cassé" are left as two reasons. Fuzzy matching would silently merge
 * distinct reasons, and a wrong merge is unrecoverable while a duplicate is a
 * five-second edit by someone who can read both.
 */
final class RejectionReasonMappingService
{
    public const MAP_TABLE = 's_lab_rejection_reason_map';

    /**
     * The reason table behind each test type key. 'recency' shares form_vl and
     * therefore shares the VL reasons.
     */
    private const REASON_TABLES = [
        'vl'             => 'r_vl_sample_rejection_reasons',
        'recency'        => 'r_vl_sample_rejection_reasons',
        'eid'            => 'r_eid_sample_rejection_reasons',
        'covid19'        => 'r_covid19_sample_rejection_reasons',
        'hepatitis'      => 'r_hepatitis_sample_rejection_reasons',
        'tb'             => 'r_tb_sample_rejection_reasons',
        'cd4'            => 'r_cd4_sample_rejection_reasons',
        'generic-tests'  => 'r_generic_sample_rejection_reasons',
    ];

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /** The payload key a lab sends each reason table under. */
    public static function payloadKeyFor(string $testType): string
    {
        return 'rejectionReasons:' . $testType;
    }

    /** @return string[] test type keys that have a reason table, one entry per distinct table */
    public static function syncableTestTypes(): array
    {
        // 'recency' is excluded on purpose: same table as 'vl', so including it
        // would send the same rows twice under two keys.
        return array_values(array_diff(array_keys(self::REASON_TABLES), ['recency']));
    }

    public static function reasonTableFor(string $testType): ?string
    {
        return self::REASON_TABLES[$testType] ?? null;
    }

    /**
     * Two names are the same reason when they differ only by case, surrounding
     * space, or runs of whitespace. Accents, punctuation and spelling are left
     * alone -- see the class note on why fuzzy matching is refused.
     */
    public static function normalizeName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        return mb_strtolower($name, 'UTF-8');
    }

    /**
     * Record what one lab's reason rows mean here, creating canonical reasons for
     * wordings nobody has used before.
     *
     * Idempotent: a lab re-sends its whole (small) reason table on every metadata
     * sync, and a mapping that already exists is left as it is.
     *
     * @param array<int, array<string, mixed>> $labReasons rows as they exist on the lab
     * @return array{mapped: int, created: int, skipped: int}
     */
    public function ingestLabReasons(string $testType, int $labId, array $labReasons): array
    {
        $table = self::reasonTableFor($testType);
        $stats = ['mapped' => 0, 'created' => 0, 'skipped' => 0];
        if ($table === null || $labId <= 0 || $labReasons === []) {
            return $stats;
        }

        $canonicalByName = $this->canonicalIdsByName($table);
        $existingMap = $this->mappedSourceIds($testType, $labId);
        $now = DateUtility::getCurrentDateTime();

        foreach ($labReasons as $labReason) {
            $sourceId = (int) ($labReason['rejection_reason_id'] ?? 0);
            $name = (string) ($labReason['rejection_reason_name'] ?? '');
            $key = self::normalizeName($name);
            if ($sourceId <= 0 || $key === '') {
                $stats['skipped']++;
                continue;
            }
            if (isset($existingMap[$sourceId])) {
                $stats['skipped']++;
                continue;
            }

            $canonicalId = $canonicalByName[$key] ?? null;
            if ($canonicalId === null) {
                $canonicalId = $this->createCanonicalReason($table, $labReason, $labId, $now);
                if ($canonicalId === null) {
                    $stats['skipped']++;
                    continue;
                }
                $canonicalByName[$key] = $canonicalId;
                $stats['created']++;
            }

            $this->db->upsert(self::MAP_TABLE, [
                'test_type' => $testType,
                'lab_id' => $labId,
                'source_reason_id' => $sourceId,
                'rejection_reason_id' => $canonicalId,
                'source_reason_name' => mb_substr($name, 0, 255),
                'created_datetime' => $now,
                'updated_datetime' => $now,
            ], ['rejection_reason_id', 'source_reason_name', 'updated_datetime']);

            $existingMap[$sourceId] = $canonicalId;
            $stats['mapped']++;
        }

        return $stats;
    }

    /**
     * The canonical id for one lab's reason id, or null when nothing is known
     * about it -- in which case the caller must leave the value alone rather than
     * guess. A wrong reason is worse than an unresolved one.
     */
    public function canonicalId(string $testType, int $labId, $sourceReasonId): ?int
    {
        $sourceId = (int) $sourceReasonId;
        if ($labId <= 0 || $sourceId <= 0 || self::reasonTableFor($testType) === null) {
            return null;
        }
        $row = $this->db->rawQueryOne(
            'SELECT rejection_reason_id FROM `' . self::MAP_TABLE . '`
              WHERE test_type = ? AND lab_id = ? AND source_reason_id = ?',
            [$testType, $labId, $sourceId]
        );
        return ((int) ($row['rejection_reason_id'] ?? 0)) ?: null;
    }

    /**
     * Swap an arriving sample's reason id for the canonical one.
     *
     * A no-op unless the lab's id is both known and different, so a sample from a
     * lab that has never contributed a reason -- and every sample that predates
     * this mechanism -- passes through untouched.
     *
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public function translateIncoming(array $incoming, string $testType, $labId): array
    {
        $sourceId = (int) ($incoming['reason_for_sample_rejection'] ?? 0);
        if ($sourceId <= 0) {
            return $incoming;
        }
        $canonicalId = $this->canonicalId($testType, (int) $labId, $sourceId);
        if ($canonicalId !== null && $canonicalId !== $sourceId) {
            $incoming['reason_for_sample_rejection'] = $canonicalId;
        }
        return $incoming;
    }

    /** @return array<string, int> normalized name => canonical rejection_reason_id */
    private function canonicalIdsByName(string $table): array
    {
        $rows = $this->db->rawQuery(
            "SELECT rejection_reason_id, rejection_reason_name FROM `$table`"
        );
        $byName = [];
        foreach ($rows as $row) {
            $key = self::normalizeName($row['rejection_reason_name'] ?? null);
            if ($key === '' || isset($byName[$key])) {
                continue;   // first id wins, so repeated runs settle on the same row
            }
            $byName[$key] = (int) $row['rejection_reason_id'];
        }
        return $byName;
    }

    /** @return array<int, int> source_reason_id => canonical id already recorded for this lab */
    private function mappedSourceIds(string $testType, int $labId): array
    {
        $rows = $this->db->rawQuery(
            'SELECT source_reason_id, rejection_reason_id FROM `' . self::MAP_TABLE . '`
              WHERE test_type = ? AND lab_id = ?',
            [$testType, $labId]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['source_reason_id']] = (int) $row['rejection_reason_id'];
        }
        return $map;
    }

    /**
     * A wording nobody has used here before becomes a canonical reason of its own,
     * tagged with the lab it came from. The lab's id is deliberately NOT reused as
     * the new row's id: it belongs to that lab's numbering, and another lab is
     * free to have minted the same number for something else.
     */
    private function createCanonicalReason(string $table, array $labReason, int $labId, string $now): ?int
    {
        $inserted = $this->db->insert($table, [
            'rejection_reason_name' => mb_substr((string) $labReason['rejection_reason_name'], 0, 255),
            'rejection_type' => $labReason['rejection_type'] ?? 'general',
            'rejection_reason_status' => 'active',
            'contributed_by_lab_id' => $labId,
            'updated_datetime' => $now,
        ]);
        if (empty($inserted)) {
            return null;
        }
        return ((int) $this->db->getInsertId()) ?: null;
    }
}
