<?php

namespace App\Services;

/**
 * Finds the sample a row of an imported result file belongs to.
 *
 * A sample code is not unique: on a server shared by many labs the same code is used
 * by several of them, and a few codes repeat within one lab. A file is imported for
 * one lab, so its results may only reach that lab's samples, or a sample no lab has
 * been given yet (lab_id is set only once a lab takes the sample on). When the code
 * still points at more than one sample, none of them is chosen: writing the result to
 * one of them by guess, or to all of them, is worse than asking the user.
 */
final class ImportedSampleMatcher
{
    public const MATCHED = 'matched';
    public const NONE = 'none';
    public const AMBIGUOUS = 'ambiguous';
    public const OTHER_LAB = 'other-lab';

    // Stored in temp_sample_import.sample_details and shown on the review screen.
    public const DETAILS_NEW = 'New Sample';
    public const DETAILS_EXISTING = 'Existing Sample';
    public const DETAILS_HAS_RESULT = 'Result already exists';
    public const DETAILS_OTHER_LAB = 'Sample belongs to another lab';
    public const DETAILS_AMBIGUOUS = 'Sample ID matches more than one sample';

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * @return array{status: string, id: ?int, row: ?array<string, mixed>}
     */
    public function find(string $testType, mixed $sampleCode, mixed $labId): array
    {
        $sampleCode = trim((string) $sampleCode);
        if ($sampleCode === '') {
            return ['status' => self::NONE, 'id' => null, 'row' => null];
        }

        $table = TestsService::getTestTableName($testType);
        $primaryKey = TestsService::getPrimaryColumn($testType);
        $candidates = $this->db->rawQuery(
            "SELECT * FROM $table WHERE sample_code = ?",
            [$sampleCode]
        ) ?: [];
        if ($candidates === []) {
            return ['status' => self::NONE, 'id' => null, 'row' => null];
        }

        $labId = (int) $labId;
        if ($labId > 0) {
            $own = array_values(array_filter(
                $candidates,
                static fn(array $row): bool => (int) $row['lab_id'] === $labId
            ));
            $unassigned = array_values(array_filter(
                $candidates,
                static fn(array $row): bool => $row['lab_id'] === null
            ));
            $candidates = $own !== [] ? $own : $unassigned;
            if ($candidates === []) {
                return ['status' => self::OTHER_LAB, 'id' => null, 'row' => null];
            }
        }

        if (count($candidates) > 1) {
            return ['status' => self::AMBIGUOUS, 'id' => null, 'row' => null];
        }

        return ['status' => self::MATCHED, 'id' => (int) $candidates[0][$primaryKey], 'row' => $candidates[0]];
    }

    /**
     * Fills in what the review screen and the processing step need on a row about to
     * be staged in temp_sample_import: the matched sample, its facility and a note.
     * $data must carry sample_code and lab_id.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function stage(string $testType, array $data): array
    {
        $match = $this->find($testType, $data['sample_code'] ?? null, $data['lab_id'] ?? null);
        $data['matched_sample_id'] = $match['id'];
        $data['sample_details'] = self::details($match);
        if ($match['row'] !== null) {
            $data['facility_id'] = $match['row']['facility_id'];
        }
        return $data;
    }

    /** @param array{status: string, row: ?array<string, mixed>} $match */
    public static function details(array $match): string
    {
        return match ($match['status']) {
            self::MATCHED => self::hasResult($match['row']) ? self::DETAILS_HAS_RESULT : self::DETAILS_EXISTING,
            self::OTHER_LAB => self::DETAILS_OTHER_LAB,
            self::AMBIGUOUS => self::DETAILS_AMBIGUOUS,
            default => self::DETAILS_NEW,
        };
    }

    /** Hepatitis keeps its results in the HCV and HBV counts. */
    private static function hasResult(?array $row): bool
    {
        foreach (['result', 'hcv_vl_count', 'hbv_vl_count'] as $column) {
            if (trim((string) ($row[$column] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a staged result may be written: to the sample it matched, or as a new
     * sample only when its code is unknown everywhere. A code that belongs to another
     * lab, or to several samples, is left for the user to correct.
     *
     * @param array{status: string} $match
     */
    public static function writable(array $match, bool $importNonMatching): bool
    {
        return $match['status'] === self::MATCHED
            || ($match['status'] === self::NONE && $importNonMatching);
    }
}
