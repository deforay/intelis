<?php

namespace App\Utilities;

use App\Services\TestsService;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\NO_RESULT;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;
use const SAMPLE_STATUS\PENDING_APPROVAL;

/**
 * When a lab has tested a sample, or decided about it: the result is then that
 * lab's, and the sample is not moved or cancelled from under it.
 *
 * Tested means a decided status, a test date, any result entered -- including a
 * preliminary one, such as a TB Xpert result saved before the final
 * interpretation -- or a result on one of the sample's test rows.
 */
final class TestedSampleRule
{
    private const DECIDED_STATUSES = [REJECTED, TEST_FAILED, ACCEPTED, PENDING_APPROVAL, NO_RESULT];

    /**
     * Columns the lab writes a result into, on each sample table. Request-side
     * history (a previous or last result, the mother's) is not a test here;
     * erring the other way only refuses a move, never loses a result.
     */
    private const RESULT_COLUMNS = [
        'vl' => [
            'result', 'result_value_absolute', 'result_value_absolute_decimal', 'result_value_text',
            'result_value_log', 'result_value_hiv_detection', 'cphl_vl_result', 'failed_vl_result',
        ],
        'eid' => ['result', 'pcr_1_test_result', 'pcr_2_test_result', 'pcr_3_test_result'],
        'covid19' => ['result'],
        'hepatitis' => [
            'result', 'hcv_vl_result', 'hbv_vl_result', 'hbsag_result', 'anti_hcv_result',
            'hcv_vl_count', 'hbv_vl_count',
        ],
        'tb' => [
            'result', 'xpert_mtb_result', 'tb_lam_result', 'culture_result',
            'identification_result', 'drug_mgit_result', 'drug_lpa_result',
        ],
        'cd4' => ['cd4_result', 'cd4_result_percentage', 'crag_test_results'],
        'generic-tests' => ['result', 'final_result_interpretation'],
    ];

    /** Test rows: table, column naming the sample, result column. */
    private const TEST_ROWS = [
        'covid19' => ['covid19_tests', 'covid19_id', 'result'],
        'tb' => ['tb_tests', 'tb_id', 'test_result'],
        'generic-tests' => ['generic_test_results', 'generic_id', 'result'],
    ];

    /** SQL that is true for a tested sample; $alias names the sample table. */
    public static function sql(string $testType, string $alias): string
    {
        $parts = [
            "$alias.result_status IN (" . implode(',', self::DECIDED_STATUSES) . ')',
            "$alias.sample_tested_datetime IS NOT NULL",
        ];
        foreach (self::RESULT_COLUMNS[$testType] ?? ['result'] as $column) {
            $parts[] = "NULLIF(TRIM($alias.$column), '') IS NOT NULL";
        }
        if (isset(self::TEST_ROWS[$testType])) {
            [$table, $parent, $result] = self::TEST_ROWS[$testType];
            $primaryKey = TestsService::getPrimaryColumn($testType);
            $parts[] = "EXISTS (SELECT 1 FROM `$table` tr WHERE tr.$parent = $alias.$primaryKey"
                . " AND NULLIF(TRIM(tr.$result), '') IS NOT NULL)";
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    /**
     * Whether a record as a lab sends it is tested.
     *
     * @param array<string, mixed> $record
     * @param array<mixed>|null $testRows the record's test rows, as sent
     */
    public static function isTested(string $testType, array $record, ?array $testRows = null): bool
    {
        if (in_array((int) ($record['result_status'] ?? 0), self::DECIDED_STATUSES, true)) {
            return true;
        }
        if (trim((string) ($record['sample_tested_datetime'] ?? '')) !== '') {
            return true;
        }
        foreach (self::RESULT_COLUMNS[$testType] ?? ['result'] as $column) {
            if (trim((string) ($record[$column] ?? '')) !== '') {
                return true;
            }
        }
        if (isset(self::TEST_ROWS[$testType]) && is_array($testRows)) {
            $result = self::TEST_ROWS[$testType][2];
            $found = false;
            array_walk_recursive($testRows, static function ($value, $key) use ($result, &$found): void {
                $found = $found || ($key === $result && trim((string) $value) !== '');
            });
            if ($found) {
                return true;
            }
        }
        return false;
    }
}
